<?php

namespace App\Http\Requests\Task;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Pemeriksaan Policy dilakukan di controller.
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],

            'status' => [
                'sometimes',
                'required',
                Rule::in([
                    'todo',
                    'in_progress',
                    'done',
                    'on_hold',
                    'cancelled',
                ]),
            ],

            'priority' => [
                'sometimes',
                'required',
                Rule::in(['low', 'medium', 'high', 'urgent']),
            ],

            'due_date' => ['nullable', 'date_format:Y-m-d'],

            // Pemilik ditentukan backend dari pengguna yang login.
            'user_id' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Judul task wajib diisi.',
            'title.string' => 'Judul task harus berupa teks.',
            'title.max' => 'Judul task maksimal 255 karakter.',
            'description.string' => 'Deskripsi harus berupa teks.',
            'description.max' => 'Deskripsi maksimal 10000 karakter.',
            'status.required' => 'Status tidak boleh kosong.',
            'status.in' => 'Status task tidak valid.',
            'priority.required' => 'Prioritas tidak boleh kosong.',
            'priority.in' => 'Prioritas task tidak valid.',
            'due_date.date_format' => 'Tanggal harus menggunakan format YYYY-MM-DD.',
            'user_id.prohibited' => 'Pemilik task tidak boleh ditentukan melalui input.',
        ];
    }
}