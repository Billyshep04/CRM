<?php

namespace App\Http\Controllers;

use App\Services\AdminInvoiceSettings;
use Illuminate\Http\Request;

class AdminInvoiceSettingController extends Controller
{
    public function __construct(private readonly AdminInvoiceSettings $settings)
    {
    }

    public function show()
    {
        return response()->json([
            'data' => $this->settings->adminPayload(),
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'account_name' => ['required', 'string', 'max:255'],
            'sort_code' => ['required', 'string', 'max:32'],
            'account_number' => ['required', 'string', 'max:32'],
        ]);

        return response()->json([
            'data' => $this->settings->updatePaymentDetails(
                (string) $validated['account_name'],
                (string) $validated['sort_code'],
                (string) $validated['account_number']
            ),
        ]);
    }
}
