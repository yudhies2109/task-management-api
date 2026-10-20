<?php

namespace App\Http\Requests\Auth;

// use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
     /**
     * Registrasi dapat diakses tanpa login.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Aturan validasi data registrasi.
     *
     * @return array<string, mixed>
     */
     public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],

            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                'unique:users,email',
            ],

            'password' => [
                'required',
                'string',
                'confirmed',
                Password::min(8)
                    ->mixedCase()
                    ->numbers()
                    ->symbols(),
            ],

            'role' => ['prohibited'],
        ];
    }
}
