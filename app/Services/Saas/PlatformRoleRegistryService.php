<?php

namespace App\Services\Saas;

use App\Models\PlatformRole;

class PlatformRoleRegistryService
{
    public function merchantOwnerRole(): PlatformRole
    {
        return PlatformRole::query()->firstOrCreate(
            ['code' => 'merchant_owner'],
            [
                'name' => 'Merchant Owner',
                'scope' => 'merchant',
                'is_system' => true,
            ]
        );
    }

    public function merchantStaffRole(): PlatformRole
    {
        return PlatformRole::query()->firstOrCreate(
            ['code' => 'merchant_staff'],
            [
                'name' => 'Merchant Staff',
                'scope' => 'merchant',
                'is_system' => true,
            ]
        );
    }

    public function platformOwnerRole(): PlatformRole
    {
        return PlatformRole::query()->firstOrCreate(
            ['code' => 'platform_owner'],
            [
                'name' => 'Platform Owner',
                'scope' => 'platform',
                'is_system' => true,
            ]
        );
    }
}
