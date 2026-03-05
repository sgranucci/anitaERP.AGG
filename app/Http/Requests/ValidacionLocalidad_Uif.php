<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ValidacionLocalidad_Uif extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'nombre' => 'required|max:255|unique:localidad_uif,nombre,' . $this->route('id'),
            'codigopostal' => 'sometimes|max:50' ,
            'codigo' => 'sometimes|max:10' ,
            'provincia_id' => 'integer' 
        ];
    }
}
