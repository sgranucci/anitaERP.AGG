<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ValidacionClasematerial extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $id = $this->route('id');

        return [
            'nombre' => 'required|max:150|unique:clasematerial,nombre,'.$id,
            'codigo' => 'nullable|max:50',
            'codigo_interno_sifab' => 'nullable|integer|unique:clasematerial,codigo_interno_sifab,'.$id,
            'habilitado' => 'nullable|boolean',
        ];
    }
}
