<?php

namespace App\Http\Requests\Saas;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PlatformDomainVerifyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'verification_status' => ['required', Rule::in(['verified', 'failed'])],
            'ssl_status' => ['nullable', Rule::in(['pending', 'active', 'failed'])],
            'check_type' => ['nullable', Rule::in(['dns', 'ssl', 'hostname'])],
            'message' => ['nullable', 'string'],
            'payload_json' => ['nullable', 'array'],
            'dns_provider' => ['nullable', 'string', 'max:50'],
            'dns_setup_mode' => ['nullable', Rule::in(['cname', 'full_zone'])],
            'cloudflare_zone_id' => ['nullable', 'string', 'max:80'],
            'cloudflare_hostname_id' => ['nullable', 'string', 'max:120'],
            'cloudflare_zone_status' => ['nullable', 'string', 'max:30'],
            'cloudflare_name_servers' => ['nullable', 'array'],
            'cloudflare_dns_records' => ['nullable', 'array'],
            'cloudflare_activation_checked_at' => ['nullable', 'date'],
        ];
    }
}
