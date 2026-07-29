<?php

namespace App\Services\Sales;

use App\Enums\LeadPipelineStage;
use App\Enums\RevenueOpportunityStatus;
use Illuminate\Validation\ValidationException;

class NextActionValidator
{
    public function business(array $data, ?string $current = null): void
    {
        $stage = LeadPipelineStage::tryFrom($data['status'] ?? $current ?? 'new');
        if ($stage?->isActive() && in_array($stage, [LeadPipelineStage::Contacted, LeadPipelineStage::Discovery, LeadPipelineStage::Proposal, LeadPipelineStage::Negotiation], true) && empty($data['next_action_at'])) {
            throw ValidationException::withMessages(['next_action_at' => ['An active contacted lead must have a next action date and time.']]);
        }
    }

    public function opportunity(array $data, RevenueOpportunityStatus|string|null $current = null): void
    {
        $status = $data['status'] ?? ($current instanceof RevenueOpportunityStatus ? $current->value : $current);
        if (in_array($status, ['identified', 'qualified', 'proposed'], true) && empty($data['next_action_at'])) {
            throw ValidationException::withMessages(['next_action_at' => ['An open revenue opportunity must have a next action date and time.']]);
        }
    }
}
