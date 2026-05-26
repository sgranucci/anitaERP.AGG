<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ValidacionDepositoAdministrador extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('id');

        return [
            'deposito_id' => [
                'required', 'integer',
                Rule::unique('deposito_administrador')
                    ->where(fn ($q) => $q->where('usuario_id', $this->input('usuario_id')))
                    ->ignore($id),
            ],
            'usuario_id' => 'required|integer',
            'principal' => 'sometimes|boolean',
            'recibe_avisos' => 'sometimes|boolean',
            'aprueba_recepcion' => 'sometimes|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'deposito_id.unique' => 'Ese usuario ya está asignado como administrador de ese depósito.',
        ];
    }
}
