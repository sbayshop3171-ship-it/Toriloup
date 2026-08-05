<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UserNotificationResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'user_id' => $this->user_id,
            'audience' => $this->audience,
            'event_key' => $this->event_key,
            'title' => $this->title,
            'body' => $this->body,
            'target_type' => $this->target_type,
            'target_value' => $this->target_value,
            'route_url' => $this->route_url,
            'data' => $this->data ?: [],
            'read' => $this->read_at !== null,
            'read_at' => optional($this->read_at)->toIso8601String(),
            'pushed_at' => optional($this->pushed_at)->toIso8601String(),
            'created_at' => optional($this->created_at)->toIso8601String(),
        ];
    }
}
