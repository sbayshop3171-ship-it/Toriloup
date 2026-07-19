<?php

namespace App\Http\Resources;


use Illuminate\Http\Resources\Json\JsonResource;
use JetBrains\PhpStorm\Pure;

class CountryCodeResource extends JsonResource
{

    /**
     * Transform the resource into an array.
     *
     * @param \Illuminate\Http\Request $request
     * @return array
     */
    public function toArray($request)
    {
        if ($this->resource === null) {
            return [
                "calling_code"  => null,
                "flag_emoji"    => "",
                "flag_svg"      => "",
                "flag_svg_path" => "",
                "capital"       => null,
                "nationality"   => null,
            ];
        }

        $callingCodes = (array) data_get($this->resource, 'calling_codes', []);
        $callingCode = $callingCodes[0] ?? null;

        return [
            "calling_code"  => $callingCode == '+1201' ? '+1' : $callingCode,
            "flag_emoji"    => data_get($this->resource, 'flag.emoji', ''),
            "flag_svg"      => data_get($this->resource, 'flag.svg', ''),
            "flag_svg_path" => data_get($this->resource, 'flag.svg_path', ''),
            "capital"       => data_get($this->resource, 'capital_rinvex'),
            "nationality"   => data_get($this->resource, 'demonym'),
        ];
    }
}
