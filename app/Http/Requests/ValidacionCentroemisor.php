<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ValidacionCentroemisor extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $id = $this->route('id');

        return [
            'nombre' => 'required|max:150|unique:centroemisor,nombre,'.$id,
            'codigo' => 'nullable|max:30',
            'codigo_interno_sifab' => 'nullable|integer|unique:centroemisor,codigo_interno_sifab,'.$id,
            'calle' => 'nullable|max:100',
            'numero' => 'nullable|max:20',
            'piso' => 'nullable|max:20',
            'departamento' => 'nullable|max:20',
            'codigo_postal' => 'nullable|max:20',
            'barrio' => 'nullable|max:100',
            'oficinacompra_id' => 'nullable|exists:oficinacompra,id',
            'habilitado' => 'nullable|boolean',
        ];
    }
}
