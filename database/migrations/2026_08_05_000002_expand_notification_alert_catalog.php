<?php

use App\Support\NotificationAlertCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        foreach (NotificationAlertCatalog::rows() as $row) {
            $existing = DB::table('notification_alerts')
                ->where('language', $row['language'])
                ->first();

            if ($existing) {
                DB::table('notification_alerts')
                    ->where('id', $existing->id)
                    ->update([
                        'name' => $row['name'],
                        'mail_message' => $existing->mail_message ?: $row['mail_message'],
                        'sms_message' => $existing->sms_message ?: $row['sms_message'],
                        'push_notification_message' => $existing->push_notification_message ?: $row['push_notification_message'],
                        'push_notification' => $row['push_notification'],
                        'updated_at' => $now,
                    ]);

                continue;
            }

            DB::table('notification_alerts')->insert($row + [
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        $legacy = [
            'order_pending_message',
            'order_confirmation_message',
            'order_on_the_way_message',
            'order_delivered_message',
            'order_canceled_message',
            'order_rejected_message',
            'admin_and_manager_new_order_message',
        ];

        $languages = collect(NotificationAlertCatalog::rows())
            ->pluck('language')
            ->reject(fn (string $language): bool => in_array($language, $legacy, true))
            ->all();

        DB::table('notification_alerts')->whereIn('language', $languages)->delete();
    }
};
