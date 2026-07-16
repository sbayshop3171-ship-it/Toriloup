<?php

namespace App\Http\Requests\Saas;

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

        if (str_contains($hostname, '://')) {
            $hostname = (string) parse_url($hostname, PHP_URL_HOST);
        }

        $hostname = preg_replace('/\/.*$/', '', $hostname) ?? $hostname;
        $hostname = preg_replace('/:\d+$/', '', $hostname) ?? $hostname;

        $this->merge([
            'hostname' => trim($hostname, '. '),
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
                    $reservedHosts = array_map(
                        static fn (?string $host): string => strtolower((string) $host),
                        array_filter([
                            config('saas.marketing_host'),
                            config('saas.owner_host'),
                            config('saas.merchant_host'),
                        ])
                    );

                    if (in_array(strtolower((string) $value), $reservedHosts, true)) {
                        $fail('The hostname is reserved by the platform.');
                    }
                },
            ],
            'dns_provider' => ['nullable', 'string', 'max:50'],
        ];
    }
}
