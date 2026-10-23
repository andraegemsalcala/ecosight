<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation for adding an animal that the open-vocabulary scanner matched
 * but that is not in the EcoSight library yet.
 *
 * The identifier and match confidence come from the open-vocabulary matcher.
 * The user reviews and may edit the Wikipedia/Wikidata details before the
 * animal is created, so the reviewed fields travel with the request.
 */
class AnimalFromScanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'qid' => ['required', 'string', 'regex:/^Q\d+$/'],
            'confidence' => ['required', 'numeric', 'between:0,1'],
            'tier' => ['nullable', 'integer', Rule::in([1, 2])],
            'common_name' => ['required', 'string', 'max:100'],
            'scientific_name' => ['nullable', 'string', 'max:150'],
            'group_name' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1200'],
            'habitat' => ['nullable', 'string', 'max:255'],
            'climate_impact' => ['nullable', 'string', 'max:1200'],
            'conservation_status' => ['nullable', 'string', 'max:50'],
            'image_candidate_token' => ['nullable', 'string', 'max:4096'],
            'source_url' => ['nullable', 'url', 'max:2048'],
            'as_type_of' => ['nullable', 'integer', 'exists:animals,id'],
            'kind' => ['nullable', Rule::in(['species'])],
        ];
    }
}
