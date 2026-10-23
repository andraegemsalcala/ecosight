<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class WikipediaImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'candidate_token' => ['required', 'string', 'max:5000'],
            'image_alt' => ['nullable', 'string', 'max:255'],
        ];
    }
}
