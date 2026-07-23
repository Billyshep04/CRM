<?php

namespace App\Http\Controllers;

use App\Http\Resources\AuditFindingResource;
use App\Models\AuditFinding;
use Illuminate\Http\Request;

class AuditFindingController extends Controller
{
    public function update(Request $request, AuditFinding $auditFinding): AuditFindingResource
    {
        $data = $request->validate([
            'resolved' => ['required', 'boolean'],
        ]);

        $auditFinding->update([
            'status' => $data['resolved'] ? 'resolved' : 'failed',
        ]);

        return new AuditFindingResource($auditFinding->fresh());
    }
}
