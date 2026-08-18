<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ValidacionLocalidad extends FormRequest
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
        if ($this->isMethod('post')) {
            // Alta: el código lo asigna el servidor (max Anita/MySQL + 1).
            return [
                'nombre' => 'required|max:255',
                'codigopostal' => 'sometimes|max:50',
                'codigo' => 'nullable|max:50',
                'provincia_id' => 'integer',
            ];
        }

        return [
            'nombre' => 'required|max:255',
            'codigopostal' => 'sometimes|max:50',
            'codigo' => 'required|max:50|unique:localidad,codigo,'.($this->route('id') ?? 'NULL'),
            'provincia_id' => 'integer',
        ];
    }
}
