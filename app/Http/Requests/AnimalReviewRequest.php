<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation for reviewing an animal that a user added from a scan.
 */
class AnimalReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'is_reviewed' => ['required', 'boolean'],
        ];
    }
}
