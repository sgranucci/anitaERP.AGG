<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ValidacionCobrador extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'nombre' => 'required|max:30|unique:cobrador,nombre,'.$this->route('id'),
            'comision' => 'nullable|numeric|max:100',
            'empresa_id' => 'nullable|integer|exists:empresa,id',
            'legajo_id' => 'nullable|integer|min:0',
            'codigo' => 'nullable|max:50',
        ];
    }
}
