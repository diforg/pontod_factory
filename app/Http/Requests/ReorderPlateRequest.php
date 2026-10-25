<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReorderPlateRequest extends FormRequest
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
            'order' => ['required', 'array', 'min:1'],
            'order.*' => ['required', 'integer', 'exists:plates,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'order.required' => 'Informe a nova ordem das placas.',
            'order.array' => 'A ordem deve ser enviada em lista.',
            'order.min' => 'Informe ao menos uma placa para reordenar.',
            'order.*.required' => 'Cada posicao deve conter um ID de placa.',
            'order.*.integer' => 'O ID da placa deve ser inteiro.',
            'order.*.exists' => 'Uma das placas informadas nao existe.',
        ];
    }
}
