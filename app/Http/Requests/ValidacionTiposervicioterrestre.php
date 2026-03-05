<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ValidacionTiposervicioterrestre extends FormRequest
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
            'nombre' => 'required|max:255|unique:tiposervicioterrestre,nombre,' . $this->route('id'),
            'abreviatura' => 'sometimes|max:10|unique:tiposervicioterrestre,abreviatura,' . $this->route('id'),
        ];
    }
}
