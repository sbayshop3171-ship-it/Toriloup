<?php

namespace App\Http\Requests;

use App\Models\FirebaseServiceAccount;
use Illuminate\Foundation\Http\FormRequest;

class NotificationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'notification_fcm_public_vapid_key'    => ['required', 'string', 'max:900'],
            'notification_fcm_api_key'             => ['required', 'string', 'max:500'],
            'notification_fcm_auth_domain'         => ['required', 'string', 'max:500'],
            'notification_fcm_project_id'          => ['required', 'string', 'max:500'],
            'notification_fcm_storage_bucket'      => ['required', 'string', 'max:500'],
            'notification_fcm_messaging_sender_id' => ['required', 'string', 'max:500'],
            'notification_fcm_app_id'              => ['required', 'string', 'max:500'],
            'notification_fcm_android_app_id'      => ['nullable', 'string', 'max:500'],
            'notification_fcm_measurement_id'      => ['required', 'string', 'max:500'],
            'notification_fcm_json_file'           => ['nullable', 'file', 'mimes:json', 'max:2048']
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if (!request()->file('notification_fcm_json_file')
                && !FirebaseServiceAccount::query()->whereNotNull('encrypted_json')->exists()) {
                $validator->errors()->add('notification_fcm_json_file', 'The Firebase service-account JSON file is required.');
                return;
            }

            if (!request()->file('notification_fcm_json_file')) {
                $credentials = app(\App\Services\FirebaseServiceAccountService::class)->credentials();
                if (($credentials['project_id'] ?? null) !== request('notification_fcm_project_id')) {
                    $validator->errors()->add('notification_fcm_project_id', 'Upload a service account for this Firebase project before changing the project ID.');
                }
            }
        });
    }
}
