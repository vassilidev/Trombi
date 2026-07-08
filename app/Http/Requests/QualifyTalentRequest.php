<?php

namespace App\Http\Requests;

use App\Support\Taxonomy;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class QualifyTalentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // POC : accès interne, pas d'auth (PRD §12).
    }

    /**
     * Chaque attribut n'accepte que des valeurs de la taxonomie (vocabulaire contractuel).
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'age_min' => ['nullable', 'integer', 'min:0', 'max:120'],
            'age_max' => ['nullable', 'integer', 'min:0', 'max:120', 'gte:age_min'],
            'description_fr' => ['nullable', 'string', 'max:2000'],
        ];

        foreach (Taxonomy::singleAttributes() as $field => $enum) {
            $rules[$field] = ['nullable', Rule::in($enum::values())];
        }

        foreach (Taxonomy::multiAttributes() as $field => $enum) {
            $rules[$field] = ['nullable', 'array'];
            $rules["{$field}.*"] = [Rule::in($enum::values())];
        }

        return $rules;
    }

    /**
     * Le payload d'annotation, dans la même forme que la sortie vision.
     *
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        $fields = [
            ...array_keys(Taxonomy::singleAttributes()),
            ...array_keys(Taxonomy::multiAttributes()),
            'age_min',
            'age_max',
            'description_fr',
        ];

        return $this->only($fields);
    }
}
