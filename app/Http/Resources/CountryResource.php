<?php

namespace App\Http\Resources;

use App\Services\CountryMetadataService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CountryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $currencyCode   = $this->currency_code;
        $currencySymbol = $this->currency_symbol;

        // Avoid expensive metadata hydration when database values already exist.
        if (blank($currencyCode) || blank($currencySymbol)) {
            $countryMetadata = app(CountryMetadataService::class)->byCountryCode($this->code);
            $currencyCode    = $currencyCode ?: $countryMetadata['currency_code'];
            $currencySymbol  = $currencySymbol ?: $countryMetadata['currency_symbol'];
        }

        return [
            'id'              => $this->id,
            'code'            => $this->code,
            'name'            => $this->name,
            'currency_code'   => $currencyCode,
            'currency_symbol' => $currencySymbol,
            'status'          => $this->status
        ];
    }
}
