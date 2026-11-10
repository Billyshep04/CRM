<?php

namespace App\Services\EmailMarketing;

use App\Models\EmailCampaign;
use App\Models\EmailSendAttempt;
use App\Models\User;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\HtmlString;
use Throwable;

/**
 * Sends one campaign email to one person. The HTML goes out exactly as stored, apart
 * from merge tags, with an auto-generated plain-text alternative.
 */
class CampaignMailer
{
    /**
     * @param  array<string, ?string>  $mergeValues
     * @param  array<string, string>  $headers
     * @return array{ok: bool, message_id: ?string, error: ?string}
     */
    public function send(EmailCampaign $campaign, string $toEmail, array $mergeValues, string $type, array $headers = [], ?string $subjectPrefix = null, ?User $by = null, ?int $recipientId = null): array
    {
        $html = MergeTags::render(self::withPreviewText((string) $campaign->html_body, $campaign->preview_text), $mergeValues);
        $text = MergeTags::render(HtmlToText::convert((string) $campaign->html_body), $mergeValues, false);
        $subject = trim(($subjectPrefix ? $subjectPrefix.' ' : '').MergeTags::render((string) $campaign->subject, $mergeValues, false));

        try {
            $sent = Mail::mailer(config('email-marketing.mailer'))->send(
                ['html' => new HtmlString($html), 'text' => new HtmlString($text)],
                [],
                function (Message $message) use ($campaign, $toEmail, $subject, $headers): void {
                    // Always one recipient per message: the list is never put in To or CC.
                    $message->to($toEmail)
                        ->from((string) $campaign->from_email, (string) $campaign->from_name)
                        ->subject($subject);

                    if ($campaign->reply_to) {
                        $message->replyTo($campaign->reply_to);
                    }

                    $symfonyHeaders = $message->getSymfonyMessage()->getHeaders();
                    foreach ($headers as $name => $value) {
                        $symfonyHeaders->addTextHeader($name, $value);
                    }
                },
            );

            $result = ['ok' => true, 'message_id' => $sent?->getMessageId(), 'error' => null];
        } catch (Throwable $exception) {
            $result = ['ok' => false, 'message_id' => null, 'error' => mb_substr($exception->getMessage(), 0, 1000)];
        }

        EmailSendAttempt::create([
            'email_campaign_id' => $campaign->id,
            'email_campaign_recipient_id' => $recipientId,
            'type' => $type,
            'email' => $toEmail,
            'status' => $result['ok'] ? 'sent' : 'failed',
            'error' => $result['error'],
            'provider_message_id' => $result['message_id'],
            'created_by_user_id' => $by?->id,
        ]);

        return $result;
    }

    /**
     * Adds the inbox preview line as hidden text straight after <body>. Only used when the
     * Preview text field is filled in; templates with their own preheader leave it blank.
     */
    public static function withPreviewText(string $html, ?string $previewText): string
    {
        $previewText = trim((string) $previewText);
        if ($previewText === '') {
            return $html;
        }

        $hidden = '<div style="display:none;max-height:0;overflow:hidden;mso-hide:all;font-size:1px;line-height:1px;">'
            .htmlspecialchars($previewText, ENT_QUOTES | ENT_HTML5, 'UTF-8').str_repeat('&#8199;&#847;', 40).'</div>';

        $withBody = preg_replace('/(<body\b[^>]*>)/i', '$1'.$hidden, $html, 1, $count);

        return $count > 0 ? $withBody : $hidden.$html;
    }
}
