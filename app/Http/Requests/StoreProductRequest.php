<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
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
            'code' => ['required', 'string', 'max:64', Rule::unique('products', 'code')],
            'name' => ['required', 'string', 'max:128'],
            'description' => ['nullable', 'string', 'max:255'],
            'size' => ['nullable', 'string', 'max:16'],
            'version' => ['nullable', 'string', 'max:16'],
            'sale_price' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
            'parts' => ['sometimes', 'array'],
            'parts.*.id' => ['exists:parts,id'],
            'parts.*.quantity' => ['required_with:parts.*.id', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.required' => 'Informe o codigo do produto.',
            'code.string' => 'O codigo do produto deve ser um texto.',
            'code.max' => 'O codigo do produto deve ter no maximo 64 caracteres.',
            'code.unique' => 'Ja existe um produto com este codigo.',
            'name.required' => 'Informe o nome do produto.',
            'name.string' => 'O nome do produto deve ser um texto.',
            'name.max' => 'O nome do produto deve ter no maximo 128 caracteres.',
            'description.string' => 'A descricao deve ser um texto.',
            'description.max' => 'A descricao deve ter no maximo 255 caracteres.',
            'size.string' => 'O tamanho deve ser um texto.',
            'size.max' => 'O tamanho deve ter no maximo 16 caracteres.',
            'version.string' => 'A versao deve ser um texto.',
            'version.max' => 'A versao deve ter no maximo 16 caracteres.',
            'sale_price.numeric' => 'O preco de venda deve ser numerico.',
            'sale_price.min' => 'O preco de venda nao pode ser negativo.',
            'is_active.boolean' => 'O status de ativacao deve ser verdadeiro ou falso.',
            'parts.array' => 'As pecas devem ser enviadas em lista.',
            'parts.*.id.exists' => 'A peca informada nao existe.',
            'parts.*.quantity.required_with' => 'Informe a quantidade da peca.',
            'parts.*.quantity.integer' => 'A quantidade da peca deve ser inteira.',
            'parts.*.quantity.min' => 'A quantidade da peca deve ser maior que zero.',
        ];
    }
}
