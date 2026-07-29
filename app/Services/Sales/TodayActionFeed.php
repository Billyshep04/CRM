<?php

namespace App\Services\Sales;

use App\Models\Business;
use App\Models\CrmTask;
use App\Models\Invoice;
use App\Models\Proposal;
use App\Models\RevenueOpportunity;
use App\Models\User;

class TodayActionFeed
{
    public function for(User $user): array
    {
        $admin = $user->hasRole('admin');
        $owner = fn ($q) => $admin ? $q : $q->where('owner_user_id', $user->id);
        $items = collect();
        CrmTask::with(['assignedTo', 'revenueOpportunity.customer'])->where('status', '!=', 'completed')->where(fn ($q) => $admin ? $q : $q->where('assigned_to_user_id', $user->id))->whereNotNull('due_date')->get()->each(fn ($t) => $items->push($this->item('task:'.$t->id, 'task', $t->title, $t->description, $t->priority, $t->due_at ?? $t->due_date, $t->revenue_opportunity_id ? 'revenue_opportunity' : 'task', $t->revenue_opportunity_id ?? $t->id, $t->revenueOpportunity?->customer?->name, $t->assignedTo?->name, ['open', 'complete', 'reschedule', 'snooze'])));
        ($owner(Business::with('owner')->whereIn('status', ['contacted', 'discovery', 'proposal', 'negotiation'])))->get()->each(fn ($b) => $items->push($this->item('business:'.$b->id, 'lead_follow_up', 'Follow up: '.$b->name, $b->next_action_notes, 'high', $b->next_action_at, 'business', $b->public_id, $b->name, $b->owner?->name, ['open', 'call', 'email', 'reschedule'])));
        ($owner(RevenueOpportunity::with(['owner', 'customer'])->whereIn('status', ['identified', 'qualified', 'proposed'])))->get()->each(fn ($o) => $items->push($this->item('opportunity:'.$o->id, 'opportunity_follow_up', $o->title, $o->next_action_notes ?? $o->recommendation, 'high', $o->next_action_at, 'revenue_opportunity', $o->public_id, $o->customer?->name, $o->owner?->name, ['open', 'call', 'email', 'reschedule'])));
        if ($admin) {
            Proposal::with('customer')->whereIn('status', ['pending', 'sent'])->get()->each(fn ($p) => $items->push($this->item('proposal:'.$p->id, 'proposal_follow_up', 'Proposal awaiting follow-up: '.$p->title, $p->proposal_number, 'normal', $p->sent_at?->addDays(2), 'proposal', $p->id, $p->customer?->name, null, ['open', 'email', 'reschedule'])));
            Invoice::with('customer')->whereNotIn('status', ['paid', 'cancelled', 'draft'])->whereDate('due_date', '<=', today()->addDays(7))->get()->each(fn ($i) => $items->push($this->item('invoice:'.$i->id, 'invoice_due', 'Invoice '.$i->invoice_number, $i->status, 'high', $i->due_date, 'invoice', $i->id, $i->customer?->name, null, ['open', 'email'])));
        }
        $groups = ['overdue' => [], 'today' => [], 'upcoming' => [], 'missing_next_action' => []];
        foreach ($items->sortBy('due_at') as $i) {
            $due = $i['due_at'];
            $key = ! $due ? 'missing_next_action' : ($due->isPast() && ! $due->isToday() ? 'overdue' : ($due->isToday() ? 'today' : 'upcoming'));
            $groups[$key][] = $i;
        }

return ['generated_at' => now()->toIso8601String(), 'groups' => $groups, 'counts' => array_map('count', $groups)];
    }

    private function item($id, $type, $title, $context, $priority, $due, $entity, $entityId, $name, $owner, $actions): array
    {
        return compact('id','type','title','context','priority','entity','entityId','name','owner','actions') + ['due_at' => $due, 'related_entity_type' => $entity, 'related_entity_id' => $entityId, 'customer_or_business_name' => $name];
    }
}
