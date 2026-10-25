<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreFilamentRequest extends FormRequest
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
            'material' => ['required', 'string', 'max:32'],
            'brand' => ['required', 'string', 'max:64'],
            'color' => ['required', 'string', 'max:32'],
            'weight' => ['required', 'numeric', 'min:0.01'],
            'price' => ['required', 'numeric', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'material.required' => 'Informe o material.',
            'material.string' => 'O material deve ser um texto.',
            'material.max' => 'O material deve ter no máximo 32 caracteres.',
            'brand.required' => 'Informe a marca.',
            'brand.string' => 'A marca deve ser um texto.',
            'brand.max' => 'A marca deve ter no máximo 64 caracteres.',
            'color.required' => 'Informe a cor.',
            'color.string' => 'A cor deve ser um texto.',
            'color.max' => 'A cor deve ter no máximo 32 caracteres.',
            'weight.required' => 'Informe o peso.',
            'weight.numeric' => 'O peso deve ser numérico.',
            'weight.min' => 'O peso deve ser maior que zero.',
            'price.required' => 'Informe o preço.',
            'price.numeric' => 'O preço deve ser numérico.',
            'price.min' => 'O preço não pode ser negativo.',
        ];
    }
}
