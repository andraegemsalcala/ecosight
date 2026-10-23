<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation for adding a variety to an animal.
 *
 * A variety is a named member of an animal: a breed, or a species that was
 * matched as a type of a library animal.
 */
class AnimalVarietyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'label' => ['required', 'string', 'max:100'],
            'kind' => ['required', Rule::in(['breed', 'species'])],
            'qid' => ['nullable', 'string', 'regex:/^Q\d+$/'],
            'scientific_name' => ['nullable', 'string', 'max:150'],
            'clip_names' => ['nullable', 'array', 'max:10'],
            'clip_names.*' => ['string', 'max:150'],
        ];
    }
}
