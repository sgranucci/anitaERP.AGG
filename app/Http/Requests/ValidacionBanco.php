<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Rules\Caja\RuleBanco;

class ValidacionBanco extends FormRequest
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
            'nombre' => 'required|max:50|unique:banco,nombre,' . $this->route('id'),
            'domicilio' => 'required|max:255',
            'provincia_id' => 'sometimes|integer',
            'localidad_id' => 'sometimes|integer',
            'email' => 'nullable|email|max:255|unique:banco,email,' . $this->route('id'),
            'nroinscripcion' => ['required', new RuleBanco('nroinscripcion')],
            'condicioniva_id' => ['integer', 'nullable'],
        ];
    }
}
