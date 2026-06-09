<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ValidacionSalida extends FormRequest
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
            'nombre' => 'required|max:255|unique:salida,nombre,'.$this->route('id'),
            'ubicacion_impresora_id' => 'required|exists:ubicacion_impresora,id',
            'comando' => 'required|max:255,',
            'uso_salida_impresora_ids' => 'nullable|array',
            'uso_salida_impresora_ids.*' => 'integer|exists:uso_salida_impresora,id',
        ];
    }
}
