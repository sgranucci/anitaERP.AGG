<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ValidacionLocalidad_Uif extends FormRequest
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

    protected function prepareForValidation(): void
    {
        if ($this->input('provincia_uif_id') === '' || $this->input('provincia_uif_id') === null) {
            $this->merge(['provincia_uif_id' => null]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        if ($this->isMethod('post')) {
            // Alta: el código Anita lo asigna el servidor (max Anita/MySQL + 1).
            return [
                'nombre' => 'required|max:255|unique:localidad_uif,nombre',
                'codigopostal' => 'sometimes|max:50',
                'codigo' => 'nullable|max:10',
                'provincia_uif_id' => 'nullable|integer|exists:provincia_uif,id',
            ];
        }

        return [
            'nombre' => 'required|max:255|unique:localidad_uif,nombre,' . $this->route('id'),
            'codigopostal' => 'sometimes|max:50',
            'codigo' => 'nullable|max:10',
            'provincia_uif_id' => 'nullable|integer|exists:provincia_uif,id',
        ];
    }
}
