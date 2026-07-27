<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ValidacionGestioncompra extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $id = $this->route('id');

        return [
            'nombre' => 'required|max:150|unique:gestioncompra,nombre,'.$id,
            'codigo' => 'nullable|max:50',
            'codigo_interno_sifab' => 'nullable|integer|unique:gestioncompra,codigo_interno_sifab,'.$id,
            'habilitado' => 'nullable|boolean',
        ];
    }
}
