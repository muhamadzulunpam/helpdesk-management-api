<?php

namespace App\Http\Requests\Category;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('category'));
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:100',
                // abaikan kategori ini sendiri, supaya boleh disimpan dengan nama yang sama
                Rule::unique('categories', 'name')->ignore($this->route('category')->id),
            ],
        ];
    }
}