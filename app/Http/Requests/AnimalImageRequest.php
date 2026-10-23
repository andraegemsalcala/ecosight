<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AnimalImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'image' => [
                'required',
                'file',
                'max:4096',
                Rule::file()->types(['jpg', 'jpeg', 'png', 'webp']),
                'dimensions:min_width=400,min_height=400',
            ],
            'image_alt' => ['nullable', 'string', 'max:255'],
            'image_credit' => ['nullable', 'string', 'max:255'],
            'image_license' => ['nullable', 'string', 'max:100'],
        ];
    }
}
