<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ValidacionCuentacaja extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'nombre' => 'required|max:255|unique:cuentacaja,nombre,' . $this->route('id'),
            'descripcion_operaciones' => 'nullable|max:60',
            'banco_id' => ['integer', 'nullable'],
            'cuentacontable_id' => 'required|integer',
            'empresa_id' => ['integer', 'nullable'],
            // CBU opcional: cuentas de valores/caja (ej. Total Coin) pueden tener banco
            // de referencia sin CBU; no exigir CBU por el solo hecho de cargar banco_id.
            'cbu' => ['nullable', 'max:50'],
            'cuenta_interbanking' => 'nullable|max:255',
            'usocuentacaja_ids' => 'nullable|array',
            'usocuentacaja_ids.*' => 'integer|exists:usocuentacaja,id',
        ];
    }

    public function attributes()
    {
        return [
            'cbu' => 'CBU',
            'banco_id' => 'banco',
            'descripcion_operaciones' => 'descripción para operaciones',
        ];
    }
}
