<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ValidacionEstacionamientoCategoriaAutomovil extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'nombre' => 'required|max:255|unique:categoria_automovil_estacionamiento,nombre,'.$this->route('id'),
        ];
    }
}
