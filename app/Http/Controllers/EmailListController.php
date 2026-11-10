<?php

namespace App\Http\Controllers;

use App\Models\EmailList;
use App\Models\EmailSubscriber;
use Illuminate\Http\Request;

class EmailListController extends Controller
{
    public function index(Request $request)
    {
        $query = $this->withCounts(EmailList::query())->orderBy('name');

        if ($search = trim((string) $request->query('search'))) {
            $query->where('name', 'like', "%{$search}%");
        }

        return response()->json($query->paginate($request->integer('per_page', 20)));
    }

    public function store(Request $request)
    {
        $list = EmailList::create([
            ...$this->validated($request),
            'created_by_user_id' => $request->user()?->id,
        ]);

        return response()->json(['data' => $this->withCounts(EmailList::query())->find($list->id)], 201);
    }

    public function show(EmailList $emailList)
    {
        return response()->json(['data' => $this->withCounts(EmailList::query())->find($emailList->id)]);
    }

    public function update(Request $request, EmailList $emailList)
    {
        $emailList->update($this->validated($request));

        return response()->json(['data' => $this->withCounts(EmailList::query())->find($emailList->id)]);
    }

    /**
     * Deleting a list keeps its subscribers (they may be on other lists) and the suppression list.
     */
    public function destroy(EmailList $emailList)
    {
        $emailList->delete();

        return response()->json(['message' => 'List deleted.']);
    }

    private function withCounts($query)
    {
        return $query->withCount([
            'subscribers',
            'subscribers as subscribed_count' => fn ($q) => $q->where('status', EmailSubscriber::STATUS_SUBSCRIBED),
            'subscribers as unsubscribed_count' => fn ($q) => $q->where('status', EmailSubscriber::STATUS_UNSUBSCRIBED),
            'subscribers as bounced_count' => fn ($q) => $q->where('status', EmailSubscriber::STATUS_BOUNCED),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);
    }
}
