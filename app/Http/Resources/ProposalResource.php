<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property \App\Models\Proposal $resource
 */
class ProposalResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'customer_id' => $this->customer_id,
            'job_id' => $this->job_id,
            'created_by_user_id' => $this->created_by_user_id,
            'parent_proposal_id' => $this->parent_proposal_id,
            'proposal_number' => $this->proposal_number,
            'version' => $this->version,
            'title' => $this->title,
            'issue_date' => $this->issue_date,
            'expiry_date' => $this->expiry_date,
            'status' => $this->status,
            'effective_status' => $this->effectiveStatus(),
            'notes' => $this->notes,
            'terms' => $this->terms,
            'subtotal' => $this->subtotal,
            'total' => $this->total,
            'sent_at' => $this->sent_at,
            'accepted_at' => $this->accepted_at,
            'rejected_at' => $this->rejected_at,
            'locked_at' => $this->locked_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'customer' => new CustomerResource($this->whenLoaded('customer')),
            'job' => new JobResource($this->whenLoaded('job')),
            'line_items' => ProposalLineItemResource::collection($this->whenLoaded('lineItems')),
            'pdf_file' => new StoredFileResource($this->whenLoaded('pdfFile')),
        ];
    }
}
