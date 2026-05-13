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
        $countryMetadata = app(CountryMetadataService::class)->byCountryCode($this->code);

        return [
            'id'              => $this->id,
            'code'            => $this->code,
            'name'            => $this->name,
            'currency_code'   => $this->currency_code ?? $countryMetadata['currency_code'],
            'currency_symbol' => $this->currency_symbol ?? $countryMetadata['currency_symbol'],
            'status'          => $this->status
        ];
    }
}
