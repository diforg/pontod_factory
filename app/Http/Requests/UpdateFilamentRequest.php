<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateFilamentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'brand' => ['sometimes', 'string', 'max:64'],
            'color' => ['sometimes', 'string', 'max:32'],
            'price' => ['sometimes', 'numeric', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'brand.string' => 'A marca deve ser um texto.',
            'brand.max' => 'A marca deve ter no máximo 64 caracteres.',
            'color.string' => 'A cor deve ser um texto.',
            'color.max' => 'A cor deve ter no máximo 32 caracteres.',
            'price.numeric' => 'O preço deve ser numérico.',
            'price.min' => 'O preço não pode ser negativo.',
            'is_active.boolean' => 'O status de ativação deve ser verdadeiro ou falso.',
        ];
    }
}
