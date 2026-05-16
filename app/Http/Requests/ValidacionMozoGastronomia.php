<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ValidacionMozoGastronomia extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $id = $this->route('id');

        return [
            'nombre' => 'required|max:255',
            'codigo' => 'nullable|max:50|unique:mozo_gastronomia,codigo,'.$id,
            'empresa_id' => 'required|exists:empresa,id',
        ];
    }
}
