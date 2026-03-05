<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ValidacionCuentacontable extends FormRequest
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
            'empresa_id' => 'required|integer',
            'rubrocontable_id' => 'required|integer',
            'nombre' => 'required|max:100|unique:cuentacontable,nombre,' . $this->route('id'),
            'codigo' => 'required|max:50|unique:cuentacontable,codigo,' . $this->route('id'),
            'tipocuenta' => 'required|min:1|max:2,',
            'nivel' => 'required|min:1|max:9,',
            'monetaria' => 'required|min:1|max:2,',
            'manejaccosto' => 'required|min:1|max:2,',
        ];
    }
}

