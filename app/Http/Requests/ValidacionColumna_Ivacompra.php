<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ValidacionColumna_Ivacompra extends FormRequest
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
            'nombre' => 'required|max:255|unique:columna_ivacompra,nombre,' . $this->route('id'),
            'nombrecolumna' => 'required|max:20|unique:columna_ivacompra,nombrecolumna,' . $this->route('id'),
            'numerocolumna' => 'required|max:10|unique:columna_ivacompra,numerocolumna,' . $this->route('id'),
        ];
    }
}
