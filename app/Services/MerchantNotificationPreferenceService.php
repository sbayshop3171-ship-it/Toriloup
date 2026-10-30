<?php

namespace App\Services;

use App\Enums\SwitchBox;
use App\Models\MerchantNotificationPreference;
use App\Models\NotificationAlert;
use App\Models\Tenant;
use App\Models\User;
use App\Support\MerchantNotificationEventCatalog;

class MerchantNotificationPreferenceService
{
    /**
     * @return array<int, array{event_key: string, label: string, description: string, enabled: bool, owner_enabled: bool}>
     */
    public function listFor(Tenant $tenant, User $user): array
    {
        $saved = MerchantNotificationPreference::query()
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $user->id)
            ->pluck('enabled', 'event_key');

        $ownerSwitches = NotificationAlert::query()
            ->whereIn('language', MerchantNotificationEventCatalog::keys())
            ->pluck('push_notification', 'language');

        return array_map(function (array $event) use ($saved, $ownerSwitches): array {
            $ownerEnabled = !array_key_exists($event['key'], $ownerSwitches->all())
                || (int) $ownerSwitches->get($event['key']) === SwitchBox::ON;

            return [
                'event_key' => $event['key'],
                'label' => $event['label'],
                'description' => $event['description'],
                'enabled' => (bool) $saved->get($event['key'], true),
                'owner_enabled' => $ownerEnabled,
            ];
        }, MerchantNotificationEventCatalog::events());
    }

    public function enabledFor(Tenant $tenant, User $user, string $eventKey): bool
    {
        if (MerchantNotificationEventCatalog::find($eventKey) === null) {
            return true;
        }

        $enabled = MerchantNotificationPreference::query()
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $user->id)
            ->where('event_key', $eventKey)
            ->value('enabled');

        return $enabled === null || (bool) $enabled;
    }

    public function update(Tenant $tenant, User $user, string $eventKey, bool $enabled): MerchantNotificationPreference
    {
        if (MerchantNotificationEventCatalog::find($eventKey) === null) {
            throw new \InvalidArgumentException('Unsupported merchant notification event.');
        }

        return MerchantNotificationPreference::query()->updateOrCreate(
            [
                'tenant_id' => $tenant->id,
                'user_id' => $user->id,
                'event_key' => $eventKey,
            ],
            ['enabled' => $enabled]
        );
    }
}
