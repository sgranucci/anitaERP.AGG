<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ValidacionTiposervicio_Proveedor extends FormRequest
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
            'nombre' => 'required|max:255|unique:tiposervicio_proveedor,nombre,'.$this->route('id'),
            'controla_unicidad_cuit' => 'required|in:CONTROLA,NO CONTROLA',
        ];
    }
}
