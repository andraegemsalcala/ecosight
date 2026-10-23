<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AnimalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $clipNames = $this->has('clip_names') && is_string($this->input('clip_names'))
            ? array_values(array_filter(array_map('trim', explode(',', $this->input('clip_names')))))
            : (array) $this->input('clip_names', []);

        $this->merge([
            'model_labels' => $this->has('model_labels') && is_string($this->input('model_labels'))
                ? array_values(array_filter(array_map('trim', explode(',', $this->input('model_labels')))))
                : $this->input('model_labels', []),
            'clip_names' => $clipNames === [] ? null : array_values($clipNames),
        ]);
    }

    public function rules(): array
    {
        $animalId = $this->route('animal')?->id;

        return [
            'common_name' => ['required', 'string', 'max:100'],
            'scientific_name' => ['required', 'string', 'max:150'],
            'coco_label' => [
                'required',
                'string',
                'max:50',
                Rule::unique('animals', 'coco_label')->ignore($animalId),
            ],
            'model_source' => [
                'required',
                'string',
                Rule::in(['coco-ssd', 'mobilenet', 'clip']),
            ],
            'model_labels' => ['nullable', 'array'],
            'model_labels.*' => ['string', 'max:100'],
            'clip_names' => ['nullable', 'array', 'max:10'],
            'clip_names.*' => ['string', 'max:150'],
            'is_scannable' => ['sometimes', 'boolean'],
            'is_reviewed' => ['sometimes', 'boolean'],
            'description' => ['required', 'string', 'max:3000'],
            'habitat' => ['nullable', 'string', 'max:2000'],
            'climate_impact' => ['nullable', 'string', 'max:3000'],
            'conservation_status' => ['required', 'string', 'max:150'],
            'image_url' => ['nullable', 'url', 'max:1000'],
            'actions' => ['nullable', 'array'],
            'actions.*.id' => ['nullable', 'integer', 'exists:conservation_actions,id'],
            'actions.*.title' => ['required_with:actions.*.description', 'nullable', 'string', 'max:150'],
            'actions.*.description' => ['required_with:actions.*.title', 'nullable', 'string', 'max:1000'],
            'actions.*.difficulty' => ['required_with:actions.*.title', 'nullable', Rule::in(['easy', 'medium', 'hard'])],
            'actions.*.category' => ['required_with:actions.*.title', 'nullable', 'string', 'max:100'],
        ];
    }
}
