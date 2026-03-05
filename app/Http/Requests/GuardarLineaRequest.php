<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GuardarLineaRequest extends FormRequest
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
            'nombre' => 'required|max:100|unique:linea,nombre,' . $this->route('id'),
            'codigo' => 'required|max:6|unique:linea,codigo,' . $this->route('id'),
            'tiponumeracion_id' => 'required|min:1|max:4',
            'maxhorma' => 'required|integer|max:999999',
            'numeracion_id' => 'required|integer'
        ];
    }
}
