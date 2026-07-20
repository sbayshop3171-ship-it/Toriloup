<?php

namespace App\Http\Resources;


use App\Libraries\AppLibrary;
use Illuminate\Http\Resources\Json\JsonResource;

class CurrencyResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param \Illuminate\Http\Request $request
     * @return array
     */
    public function toArray($request): array
    {
        $code = strtoupper((string) $this->code);

        return [
            "id"                => $this->id,
            "name"              => $this->name,
            "name_symbol"       => $code . ' - ' . $this->name . ' (' . $this->symbol . ')',
            "symbol"            => $this->symbol,
            "code"              => $code,
            "minor_unit"        => (int) ($this->minor_unit ?? 2),
            "is_cryptocurrency" => $this->is_cryptocurrency,
            "exchange_rate"     => AppLibrary::convertAmountFormat($this->exchange_rate),
            "is_auto_managed"   => (bool) ($this->is_auto_managed ?? false),
            "is_enabled"        => (bool) ($this->is_enabled ?? true),
            "rate_source"       => $this->rate_source,
            "rate_synced_at"    => optional($this->rate_synced_at)->toDateTimeString(),
        ];
    }
}
