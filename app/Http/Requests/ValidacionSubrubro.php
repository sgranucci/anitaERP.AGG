<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ValidacionSubrubro extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $id = $this->route('id');

        return [
            'nombre' => 'required|max:150',
            'codigo' => 'nullable|max:30',
            'codigo_interno_sifab' => 'nullable|integer|unique:subrubro,codigo_interno_sifab,'.$id,
            'rubro_id' => 'nullable|exists:rubro,id',
            'habilitado' => 'nullable|boolean',
        ];
    }
}
