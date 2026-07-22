<?php

namespace App\Http\Requests\Saas;

use App\Services\Tenancy\TenantResolver;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TenantDomainStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $hostname = trim(strtolower((string) $this->input('hostname')));
        $setupMode = trim(strtolower((string) $this->input('dns_setup_mode', 'cname')));

        if (str_contains($hostname, '://')) {
            $hostname = (string) parse_url($hostname, PHP_URL_HOST);
        }

        $hostname = preg_replace('/\/.*$/', '', $hostname) ?? $hostname;
        $hostname = preg_replace('/:\d+$/', '', $hostname) ?? $hostname;
        $hostname = trim($hostname, '. ');

        if ($setupMode === 'full_zone' && str_starts_with($hostname, 'www.')) {
            $hostname = substr($hostname, 4);
        }

        $this->merge([
            'hostname' => $hostname,
            'dns_setup_mode' => in_array($setupMode, ['cname', 'full_zone'], true) ? $setupMode : 'cname',
        ]);
    }

    public function rules(): array
    {
        return [
            'hostname' => [
                'required',
                'string',
                'max:255',
                'regex:/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/',
                Rule::unique('tenant_domains', 'hostname'),
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (app(TenantResolver::class)->isReservedHost((string) $value)) {
                        $fail('The hostname is reserved by the platform.');
                    }
                },
            ],
            'dns_provider' => ['nullable', 'string', 'max:50'],
            'dns_setup_mode' => ['nullable', Rule::in(['cname', 'full_zone'])],
        ];
    }
}
