<?php

namespace App\Http\Requests;

use App\Enums\ProductCondition;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorefrontSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'condition' => ['nullable', Rule::enum(ProductCondition::class)],
            'make' => ['nullable', 'integer', 'min:1'],
            'model' => ['nullable', 'integer', 'min:1'],
            'variant' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
