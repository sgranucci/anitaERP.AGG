<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ValidacionRubro extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $id = $this->route('id');

        return [
            'nombre' => 'required|max:150|unique:rubro,nombre,'.$id,
            'codigo' => 'nullable|max:30',
            'codigo_interno_sifab' => 'nullable|integer|unique:rubro,codigo_interno_sifab,'.$id,
            'codigo_interno_cuenta_compra' => 'nullable|integer',
            'codigo_interno_cuenta_gasto' => 'nullable|integer',
            'codigo_interno_cuenta_variacion' => 'nullable|integer',
            'subrubro_obligatorio' => 'nullable|boolean',
            'habilitado' => 'nullable|boolean',
        ];
    }
}
