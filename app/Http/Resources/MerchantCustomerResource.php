<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class MerchantCustomerResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'legacy_user_id' => $this->legacy_user_id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'country_code' => $this->country_code,
            'status' => $this->status,
            'image' => $this->legacyUser?->image,
            'last_login_at' => optional($this->last_login_at)?->toDateTimeString(),
        ];
    }
}
