<?php

namespace App\Http\Controllers;

use App\Models\HostingServer;
use App\Services\Hosting\HostingProviderManager;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class HostingServerController extends Controller
{
    public function index() { return response()->json(['data' => HostingServer::query()->withCount(['websites','accounts'])->with('packages')->latest()->get()->map(fn($s)=>[...$s->toArray(),'credential_username'=>$s->credentials['username']??null,'has_token'=>!empty($s->credentials['token'])])]); }
    public function store(Request $request) { $server = HostingServer::create($this->validated($request)); return response()->json(['data' => $server], 201); }
    public function update(Request $request, HostingServer $hostingServer) { $data=$this->validated($request, true); if(isset($data['credentials'])&&empty($data['credentials']['token']))$data['credentials']['token']=$hostingServer->credentials['token']??null; $hostingServer->update($data); return response()->json(['data' => $hostingServer->fresh()]); }
    public function destroy(HostingServer $hostingServer) { $hostingServer->delete(); return response()->json(['message' => 'Hosting server deleted.']); }
    public function test(HostingServer $hostingServer, HostingProviderManager $providers) { return response()->json(['data' => $providers->for($hostingServer)->testConnection($hostingServer)]); }
    private function validated(Request $request, bool $sometimes = false): array
    {
        $required = $sometimes ? 'sometimes' : 'required';
        return $request->validate(['name' => [$required, 'string', 'max:255'], 'provider' => ['sometimes', 'string', 'max:50'], 'hostname' => ['nullable', 'string', 'max:255'], 'server_type' => ['nullable', 'string', 'max:100'], 'api_type' => ['sometimes', Rule::in(['mock', 'cpanel', 'whm'])], 'credentials' => ['sometimes', 'nullable', 'array'], 'credentials.username' => ['nullable', 'string'], 'credentials.token' => ['nullable', 'string'], 'status' => ['sometimes', Rule::in(['active', 'paused'])], 'metadata' => ['sometimes', 'nullable', 'array']]);
    }
}
