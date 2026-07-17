<?php

namespace App\Http\Requests\Saas;

use App\Enums\Ask;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MerchantRegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'owner_name' => ['required', 'string', 'max:255'],
            'store_name' => ['required', 'string', 'max:160'],
            'store_slug' => ['nullable', 'string', 'max:120', 'alpha_dash'],
            'legal_name' => ['nullable', 'string', 'max:190'],
            'email' => request('phone') ? ['nullable', 'string', 'email', 'max:255', Rule::unique('users', 'email')->where('is_guest', Ask::NO)] : ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->where('is_guest', Ask::NO)],
            'phone' => request('email') ? ['nullable', 'string', 'max:20'] : ['required', 'string', 'max:20'],
            'country_code' => request('email') ? ['nullable', 'string', 'max:10'] : ['required', 'string', 'max:10'],
            'password' => ['required', 'string', 'min:6'],
            'primary_locale' => ['nullable', 'string', 'max:10'],
            'primary_currency_code' => ['nullable', 'string', 'max:10'],
            'timezone' => ['nullable', 'string', 'max:60'],
            'plan_code' => ['nullable', 'string', 'max:60'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if (!blank(request('phone')) && !blank(request('country_code'))) {
                $existingUser = User::query()
                    ->where('country_code', request('country_code'))
                    ->where('phone', request('phone'))
                    ->where('is_guest', Ask::NO)
                    ->first();

                if ($existingUser !== null) {
                    $validator->errors()->add('phone', 'The phone has already been taken.');
                }
            }
        });
    }
}
