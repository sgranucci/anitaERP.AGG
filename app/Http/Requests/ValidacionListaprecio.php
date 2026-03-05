<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ValidacionListaprecio extends FormRequest
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
            'nombre' => 'required|max:100|unique:listaprecio,nombre,' . $this->route('id'),
            'formula' => 'required|max:255',
            'incluyeimpuesto' => 'required|min:1|max:2',
            'codigo' => 'required|numeric|unique:listaprecio,codigo,'. $this->route('id')
        ];
    }
}
