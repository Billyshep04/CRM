<?php

namespace App\Services\EmailMarketing;

use App\Models\EmailList;
use App\Models\EmailSubscriber;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Adds people to a list, manually or from a CSV, applying the same rules either way:
 * one subscriber per email (case-insensitive), valid addresses only, and never
 * re-adding anyone on the suppression list.
 */
class SubscriberImporter
{
    public const ADDED = 'added';

    public const DUPLICATE = 'duplicate';

    public const SKIPPED = 'skipped';

    public const INVALID = 'invalid';

    private const IMPORT_DIRECTORY = 'email-imports';

    private const DETAIL_LIMIT = 200;

    public function __construct(private readonly SuppressionService $suppressions)
    {
    }

    public static function isValidEmail(string $email): bool
    {
        return $email !== '' && !Validator::make(['email' => $email], ['email' => 'email:rfc,filter|max:254'])->fails();
    }

    /**
     * @param  array{email: string, first_name?: ?string, last_name?: ?string}  $person
     * @return array{outcome: string, reason: ?string, subscriber: ?EmailSubscriber}
     */
    public function addOne(EmailList $list, array $person, string $consentSource, ?string $consentNote, string $addedVia, ?User $by): array
    {
        $result = $this->addMany($list, [$person], $consentSource, $consentNote, $addedVia, $by);
        $detail = $result['details'][0] ?? null;
        $outcome = collect([self::ADDED, self::DUPLICATE, self::SKIPPED, self::INVALID])->first(fn ($key) => $result[$key] > 0);

        return [
            'outcome' => $outcome,
            'reason' => $detail['reason'] ?? null,
            'subscriber' => EmailSubscriber::where('email', EmailSubscriber::normaliseEmail($person['email']))->first(),
        ];
    }

    /**
     * @param  list<array{email: string, first_name?: ?string, last_name?: ?string, row?: int}>  $people
     * @return array{added: int, duplicate: int, skipped: int, invalid: int, details: list<array{row: ?int, email: string, outcome: string, reason: string}>}
     */
    public function addMany(EmailList $list, array $people, string $consentSource, ?string $consentNote, string $addedVia, ?User $by): array
    {
        $summary = [self::ADDED => 0, self::DUPLICATE => 0, self::SKIPPED => 0, self::INVALID => 0, 'details' => []];
        $note = function (array $person, string $outcome, string $reason) use (&$summary): void {
            $summary[$outcome]++;
            if (count($summary['details']) < self::DETAIL_LIMIT) {
                $summary['details'][] = ['row' => $person['row'] ?? null, 'email' => $person['email'], 'outcome' => $outcome, 'reason' => $reason];
            }
        };

        $seen = [];
        $valid = [];
        foreach ($people as $person) {
            $person['email'] = EmailSubscriber::normaliseEmail((string) ($person['email'] ?? ''));
            if ($person['email'] === '') {
                $note($person, self::INVALID, 'No email address');
            } elseif (!self::isValidEmail($person['email'])) {
                $note($person, self::INVALID, 'Not a valid email address');
            } elseif (isset($seen[$person['email']])) {
                $note($person, self::DUPLICATE, 'Appears more than once in this import');
            } else {
                $seen[$person['email']] = true;
                $valid[] = $person;
            }
        }

        foreach (array_chunk($valid, 500) as $chunk) {
            $emails = array_column($chunk, 'email');
            $existing = EmailSubscriber::whereIn('email', $emails)->get()->keyBy('email');
            $suppressed = $this->suppressions->suppressedAmong($emails);
            $onList = DB::table('email_list_subscriber')
                ->where('email_list_id', $list->id)
                ->whereIn('email_subscriber_id', $existing->pluck('id'))
                ->pluck('email_subscriber_id')
                ->flip();

            DB::transaction(function () use ($chunk, $list, $existing, $suppressed, $onList, $consentSource, $consentNote, $addedVia, $by, $note): void {
                foreach ($chunk as $person) {
                    $subscriber = $existing->get($person['email']);

                    // Suppression is checked first so unsubscribed people are always reported as such.
                    if (isset($suppressed[$person['email']]) || ($subscriber && $subscriber->status !== EmailSubscriber::STATUS_SUBSCRIBED)) {
                        $note($person, self::SKIPPED, $subscriber?->status === EmailSubscriber::STATUS_BOUNCED ? 'Previously bounced' : 'Previously unsubscribed');

                        continue;
                    }

                    if ($subscriber && isset($onList[$subscriber->id])) {
                        $note($person, self::DUPLICATE, 'Already on this list');

                        continue;
                    }

                    $firstName = $this->clean($person['first_name'] ?? null);
                    $lastName = $this->clean($person['last_name'] ?? null);

                    if ($subscriber) {
                        // Existing people keep their original consent record; only blank names are filled in.
                        $subscriber->fill(array_filter([
                            'first_name' => $subscriber->first_name ? null : $firstName,
                            'last_name' => $subscriber->last_name ? null : $lastName,
                        ]))->save();
                    } else {
                        $subscriber = EmailSubscriber::create([
                            'email' => $person['email'],
                            'first_name' => $firstName,
                            'last_name' => $lastName,
                            'status' => EmailSubscriber::STATUS_SUBSCRIBED,
                            'consent_source' => $consentSource,
                            'consent_note' => $consentNote,
                            'created_by_user_id' => $by?->id,
                        ]);
                    }

                    $list->subscribers()->attach($subscriber->id, ['added_via' => $addedVia]);
                    $note($person, self::ADDED, $subscriber->wasRecentlyCreated ? 'New subscriber' : 'Existing subscriber added to this list');
                }
            });
        }

        return $summary;
    }

    /**
     * Stores an uploaded CSV and returns what's needed for the column-mapping step.
     *
     * @return array{token: string, columns: list<string>, sample: list<list<string>>, rows: int, mapping: array{email: ?int, first_name: ?int, last_name: ?int}}
     */
    public function preview(UploadedFile $file): array
    {
        $this->purgeOldUploads();

        $token = (string) Str::uuid();
        Storage::disk('local')->putFileAs(self::IMPORT_DIRECTORY, $file, "{$token}.csv");
        $rows = $this->readRows($token);

        if ($rows === []) {
            Storage::disk('local')->delete($this->path($token));

            throw new InvalidArgumentException('That file is empty.');
        }

        $columns = array_map(fn ($value, $index) => $value !== '' ? $value : 'Column '.($index + 1), $rows[0], array_keys($rows[0]));

        return [
            'token' => $token,
            'columns' => $columns,
            'sample' => array_slice($rows, 1, 5),
            'rows' => count($rows) - 1,
            'mapping' => $this->guessMapping($columns),
        ];
    }

    /**
     * @param  array{email: int, first_name?: ?int, last_name?: ?int}  $mapping  Column indexes.
     * @return array{added: int, duplicate: int, skipped: int, invalid: int, details: list<array>}
     */
    public function import(EmailList $list, string $token, array $mapping, bool $hasHeader, string $consentSource, ?string $consentNote, ?User $by): array
    {
        if (!Str::isUuid($token) || !Storage::disk('local')->exists($this->path($token))) {
            throw new InvalidArgumentException('That upload has expired. Please choose the file again.');
        }

        $rows = $this->readRows($token);
        Storage::disk('local')->delete($this->path($token));

        $people = [];
        foreach ($rows as $index => $row) {
            if ($hasHeader && $index === 0) {
                continue;
            }
            if (count(array_filter($row, fn ($cell) => $cell !== '')) === 0) {
                continue;
            }

            $column = fn (?int $position): ?string => $position === null ? null : ($row[$position] ?? null);
            $people[] = [
                'row' => $index + 1,
                'email' => (string) $column($mapping['email']),
                'first_name' => $column($mapping['first_name'] ?? null),
                'last_name' => $column($mapping['last_name'] ?? null),
            ];
        }

        return $this->addMany($list, $people, $consentSource, $consentNote, 'csv_import', $by);
    }

    /**
     * @return list<list<string>>
     */
    private function readRows(string $token): array
    {
        $contents = (string) Storage::disk('local')->get($this->path($token));
        $contents = preg_replace('/^\xEF\xBB\xBF/', '', $contents);
        if (!mb_check_encoding($contents, 'UTF-8')) {
            $contents = mb_convert_encoding($contents, 'UTF-8', 'Windows-1252');
        }

        $firstLine = strtok($contents, "\r\n") ?: '';
        $delimiter = collect([',', ';', "\t"])->sortByDesc(fn ($candidate) => substr_count($firstLine, $candidate))->first();

        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $contents);
        rewind($handle);

        $rows = [];
        while (($row = fgetcsv($handle, 0, $delimiter, '"', '')) !== false) {
            if ($row === [null]) {
                continue;
            }
            $rows[] = array_map(fn ($cell) => trim((string) $cell), $row);
        }
        fclose($handle);

        return $rows;
    }

    /**
     * @param  list<string>  $columns
     * @return array{email: ?int, first_name: ?int, last_name: ?int}
     */
    private function guessMapping(array $columns): array
    {
        $find = function (array $patterns) use ($columns): ?int {
            foreach ($columns as $index => $column) {
                $name = preg_replace('/[^a-z]/', '', mb_strtolower($column));
                if (in_array($name, $patterns, true)) {
                    return $index;
                }
            }

            return null;
        };

        return [
            'email' => $find(['email', 'emailaddress', 'email1', 'mail', 'emailaddr']),
            'first_name' => $find(['firstname', 'first', 'forename', 'givenname', 'fname']),
            'last_name' => $find(['lastname', 'last', 'surname', 'familyname', 'lname']),
        ];
    }

    private function clean(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : mb_substr($value, 0, 255);
    }

    private function path(string $token): string
    {
        return self::IMPORT_DIRECTORY."/{$token}.csv";
    }

    private function purgeOldUploads(): void
    {
        $disk = Storage::disk('local');
        foreach ($disk->files(self::IMPORT_DIRECTORY) as $file) {
            if ($disk->lastModified($file) < now()->subDay()->getTimestamp()) {
                $disk->delete($file);
            }
        }
    }
}
