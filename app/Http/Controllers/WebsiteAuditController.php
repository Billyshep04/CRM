<?php

namespace App\Http\Controllers;

use App\Actions\WebsiteAudits\StartWebsiteAudit;
use App\Contracts\WebsiteAuditRepository;
use App\Http\Requests\StoreWebsiteAuditRequest;
use App\Http\Resources\WebsiteAuditResource;
use App\Models\WebsiteAudit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class WebsiteAuditController extends Controller
{
    public function index(Request $request, WebsiteAuditRepository $audits): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'website_id' => ['nullable', 'integer', 'exists:websites,id'],
            'status' => ['nullable', 'in:pending,running,completed,failed'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        return WebsiteAuditResource::collection($audits->paginate($validated));
    }

    public function store(StoreWebsiteAuditRequest $request, StartWebsiteAudit $action): JsonResponse
    {
        $audit = $action->execute(
            $request->string('url')->toString(),
            $request->integer('website_id') ?: null,
            $request->integer('business_id') ?: null,
            $request->user()->id,
        );

        return (new WebsiteAuditResource($audit))
            ->response()
            ->setStatusCode(Response::HTTP_ACCEPTED);
    }

    public function show(WebsiteAudit $websiteAudit): WebsiteAuditResource
    {
        return new WebsiteAuditResource($websiteAudit->load('findings'));
    }
}
