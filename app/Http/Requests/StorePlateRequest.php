<?php

namespace App\Http\Requests;

use App\Models\Filament;
use Illuminate\Foundation\Http\FormRequest;

class StorePlateRequest extends FormRequest
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
            'filament_code' => ['required', 'string', 'exists:filaments,code'],
            'parts' => ['required', 'array', 'min:1'],
            'parts.*.id' => ['required', 'exists:parts,id'],
            'parts.*.quantity' => ['required', 'integer', 'min:1'],
        ];
    }

    public function after(): array
    {
        return [function ($validator): void {
            $code = $this->input('filament_code');

            if (! is_string($code) || $code === '') {
                return;
            }

            $isActive = Filament::query()
                ->where('code', $code)
                ->value('is_active');

            if ($isActive === null) {
                return;
            }

            if (! (bool) $isActive) {
                $validator->errors()->add('filament_code', 'O filamento informado esta inativo.');
            }
        }];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'filament_code.required' => 'Informe o codigo do filamento.',
            'filament_code.string' => 'O codigo do filamento deve ser um texto.',
            'filament_code.exists' => 'O filamento informado nao existe.',
            'parts.required' => 'Informe ao menos uma peca para a placa.',
            'parts.array' => 'As pecas devem ser enviadas em lista.',
            'parts.min' => 'Informe ao menos uma peca para a placa.',
            'parts.*.id.required' => 'Informe o ID da peca.',
            'parts.*.id.exists' => 'A peca informada nao existe.',
            'parts.*.quantity.required' => 'Informe a quantidade da peca.',
            'parts.*.quantity.integer' => 'A quantidade da peca deve ser inteira.',
            'parts.*.quantity.min' => 'A quantidade da peca deve ser maior que zero.',
        ];
    }
}
