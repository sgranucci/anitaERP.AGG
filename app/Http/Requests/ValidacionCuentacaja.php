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
            'banco_id' => ['integer', 'nullable'],
            'cuentacontable_id' => 'required|integer',
            'empresa_id' => ['integer', 'nullable'],
            'cbu' => 'nullable|max:50|required_with:banco_id',
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
        ];
    }
}
