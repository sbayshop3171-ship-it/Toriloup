<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\NotificationAlert;
use App\Support\NotificationAlertCatalog;

class NotificationAlertTableSeeder extends Seeder
{
    public function run()
    {
        foreach (NotificationAlertCatalog::rows() as $notificationAlert) {
            NotificationAlert::updateOrCreate(
                ['language' => $notificationAlert['language']],
                $notificationAlert
            );
        }
    }
}
