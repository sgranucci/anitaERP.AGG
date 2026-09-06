<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ValidacionUsoarticulo extends FormRequest
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
            'nombre' => 'required|max:100|unique:usoarticulo,nombre,'.$this->route('id'),
            'aprobacion_modo' => 'nullable|in:auto,arbol,default',
            'arbolaprobacion_id' => 'nullable|integer|exists:arbolaprobacion,id',
        ];
    }
}
