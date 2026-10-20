<?php

namespace App\Http\Requests\Task;

class UpdateTaskRequest extends StoreTaskRequest
{
    public function rules(): array
    {
        $rules = parent::rules();

        // Saat update, judul hanya divalidasi jika dikirim.
        $rules['title'] = ['sometimes', 'required', 'string', 'max:255'];

        return $rules;
    }
}