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
        ];
    }
}
