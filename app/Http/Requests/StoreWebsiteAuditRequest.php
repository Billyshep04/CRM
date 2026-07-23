<?php

namespace App\Http\Requests;

use App\Exceptions\UnsafeWebsiteUrl;
use App\Services\WebsiteAnalysis\SafeUrlGuard;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreWebsiteAuditRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['admin', 'staff']) ?? false;
    }

    public function rules(): array
    {
        return [
            'url' => ['required', 'url:http,https', 'max:2048'],
            'website_id' => ['nullable', 'integer', 'exists:websites,id'],
            'business_id' => ['nullable', 'integer', 'exists:businesses,id'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->has('url')) {
                return;
            }
            try {
                app(SafeUrlGuard::class)->assertSafe((string) $this->input('url'));
            } catch (UnsafeWebsiteUrl $exception) {
                $validator->errors()->add('url', $exception->getMessage());
            }
        }];
    }
}
