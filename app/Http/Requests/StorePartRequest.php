<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePartRequest extends FormRequest
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
            'code' => ['required', 'string', 'max:64', Rule::unique('parts', 'code')],
            'description' => ['nullable', 'string', 'max:255'],
            'size' => ['nullable', 'string', 'max:16'],
            'filament_g' => ['required', 'numeric', 'min:0.01'],
            'production_time_min' => ['required', 'integer', 'min:1'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.required' => 'Informe o codigo da peca.',
            'code.string' => 'O codigo da peca deve ser um texto.',
            'code.max' => 'O codigo da peca deve ter no maximo 64 caracteres.',
            'code.unique' => 'Ja existe uma peca com este codigo.',
            'description.string' => 'A descricao deve ser um texto.',
            'description.max' => 'A descricao deve ter no maximo 255 caracteres.',
            'size.string' => 'O tamanho deve ser um texto.',
            'size.max' => 'O tamanho deve ter no maximo 16 caracteres.',
            'filament_g.required' => 'Informe o consumo de filamento.',
            'filament_g.numeric' => 'O consumo de filamento deve ser numerico.',
            'filament_g.min' => 'O consumo de filamento deve ser maior que zero.',
            'production_time_min.required' => 'Informe o tempo de producao.',
            'production_time_min.integer' => 'O tempo de producao deve ser um numero inteiro.',
            'production_time_min.min' => 'O tempo de producao deve ser maior que zero.',
            'is_active.boolean' => 'O status de ativacao deve ser verdadeiro ou falso.',
        ];
    }
}
