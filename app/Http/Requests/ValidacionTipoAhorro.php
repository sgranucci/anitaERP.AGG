<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ValidacionTipoAhorro extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'nombre' => 'required|max:120|unique:tipo_ahorro,nombre,'.$this->route('id'),
            'orden' => 'nullable|integer|min:0|max:65000',
        ];
    }
}
