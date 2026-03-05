<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ValidacionActividad_Arca extends FormRequest
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
            'nombre' => 'required|max:255|unique:actividad_arca,nombre,' . $this->route('id'),
            'codigoarca' => 'required|max:255|unique:actividad_arca,codigoarca,' . $this->route('id'),
        ];
    }
}
