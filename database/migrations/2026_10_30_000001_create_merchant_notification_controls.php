<?php

use App\Models\NotificationSetting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merchant_notification_preferences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('event_key', 120);
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'user_id', 'event_key'], 'merchant_notification_preference_unique');
            $table->index(['user_id', 'tenant_id']);
        });

        Schema::create('push_device_tokens', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->uuid('installation_id');
            $table->string('platform', 20);
            $table->text('token');
            $table->char('token_hash', 64)->unique();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'installation_id'], 'push_device_user_installation_unique');
            $table->index(['user_id', 'platform']);
        });

        Schema::create('firebase_service_accounts', function (Blueprint $table): void {
            $table->unsignedTinyInteger('id')->primary();
            $table->longText('encrypted_json')->nullable();
            $table->timestamps();
        });

        DB::table('firebase_service_accounts')->insert([
            'id' => 1,
            'encrypted_json' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->backfillLegacyDeviceTokens();
        $this->secureLegacyServiceAccountUpload();
    }

    public function down(): void
    {
        Schema::dropIfExists('firebase_service_accounts');
        Schema::dropIfExists('push_device_tokens');
        Schema::dropIfExists('merchant_notification_preferences');
    }

    private function backfillLegacyDeviceTokens(): void
    {
        if (!Schema::hasTable('users')) {
            return;
        }

        DB::table('users')
            ->select(['id', 'device_token', 'web_token'])
            ->orderBy('id')
            ->chunkById(200, function ($users): void {
                foreach ($users as $user) {
                    foreach ([
                        ['token' => $user->device_token, 'platform' => 'android'],
                        ['token' => $user->web_token, 'platform' => 'web'],
                    ] as $registration) {
                        $token = trim((string) ($registration['token'] ?? ''));
                        if ($token === '') {
                            continue;
                        }

                        $hash = hash('sha256', $token);
                        $installationId = $this->uuidFromHash($hash);
                        DB::table('push_device_tokens')->updateOrInsert(
                            ['token_hash' => $hash],
                            [
                                'user_id' => $user->id,
                                'installation_id' => $installationId,
                                'platform' => $registration['platform'],
                                'token' => $token,
                                'last_seen_at' => now(),
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]
                        );
                    }
                }
            });
    }

    private function secureLegacyServiceAccountUpload(): void
    {
        if (!Schema::hasTable('settings') || !Schema::hasTable('media')) {
            return;
        }

        $setting = NotificationSetting::query()
            ->where('key', 'notification_fcm_json_file')
            ->first();

        if (!$setting) {
            return;
        }

        $mediaItems = Media::query()
            ->where('model_type', $setting->getMorphClass())
            ->where('model_id', $setting->getKey())
            ->where('collection_name', 'notification-file')
            ->orderByDesc('id')
            ->get();

        if ($mediaItems->isEmpty()) {
            return;
        }

        $media = $mediaItems->first();
        $disk = Storage::disk($media->disk);
        $path = $media->getPathRelativeToRoot();
        if (!$disk->exists($path)) {
            throw new RuntimeException('The existing Firebase service-account upload could not be moved to private storage.');
        }

        $json = $disk->get($path);
        $credentials = json_decode($json, true);
        if (!is_array($credentials)
            || ($credentials['type'] ?? null) !== 'service_account'
            || empty($credentials['project_id'])
            || empty($credentials['client_email'])
            || empty($credentials['private_key'])) {
            throw new RuntimeException('The existing Firebase service-account upload is invalid; migration stopped before deleting it.');
        }

        DB::table('firebase_service_accounts')->where('id', 1)->update([
            'encrypted_json' => Crypt::encryptString($json),
            'updated_at' => now(),
        ]);

        foreach ($mediaItems as $oldMedia) {
            $oldMedia->delete();
        }
    }

    private function uuidFromHash(string $hash): string
    {
        return substr($hash, 0, 8).'-'.substr($hash, 8, 4).'-4'.substr($hash, 13, 3)
            .'-a'.substr($hash, 17, 3).'-'.substr($hash, 20, 12);
    }
};
