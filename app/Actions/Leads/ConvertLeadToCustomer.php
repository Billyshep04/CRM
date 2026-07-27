<?php

namespace App\Actions\Leads;

use App\Models\Business;
use App\Models\Customer;
use App\Models\Website;
use Illuminate\Support\Facades\DB;

class ConvertLeadToCustomer
{
    public function execute(Business $lead, int $userId): Customer
    {
        return DB::transaction(function () use ($lead, $userId): Customer {
            $lead->refresh();
            if ($lead->customer_id) {
                return Customer::query()->findOrFail($lead->customer_id);
            }

            $customer = Customer::query()->create([
                'name' => $lead->name,
                'email' => 'lead-'.strtolower($lead->public_id).'@placeholder.invalid',
                'phone' => $lead->phone,
                'billing_address' => $lead->address ?: 'Address not provided',
                'notes' => trim(implode("\n", array_filter([
                    'Converted from Lead Discovery.',
                    $lead->google_maps_url ? 'Google Maps: '.$lead->google_maps_url : null,
                    'Email is a placeholder and must be updated when a contact email is obtained.',
                ]))),
                'created_by_user_id' => $userId,
            ]);

            if ($lead->website_url) {
                Website::query()->create([
                    'customer_id' => $customer->id,
                    'name' => $lead->name,
                    'login_url' => $lead->website_url,
                    'notes' => 'Imported from Lead Discovery. Website login details have not been supplied.',
                ]);
            }

            $lead->update(['customer_id' => $customer->id, 'status' => 'converted']);

            return $customer;
        });
    }
}
