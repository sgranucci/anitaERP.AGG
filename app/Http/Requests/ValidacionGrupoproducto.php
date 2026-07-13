<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ValidacionGrupoproducto extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $id = $this->route('id');

        return [
            'nombre' => 'required|max:150|unique:grupoproducto,nombre,'.$id,
            'codigo' => 'nullable|max:30',
            'codigo_interno_sifab' => 'nullable|integer|unique:grupoproducto,codigo_interno_sifab,'.$id,
            'linea_id' => 'nullable|exists:linea,id',
            'habilitado' => 'nullable|boolean',
        ];
    }
}
