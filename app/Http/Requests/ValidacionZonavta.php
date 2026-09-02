<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ValidacionZonavta extends FormRequest
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
        $rules = [
            'nombre' => 'required|max:50|unique:zonavta,nombre,' . $this->route('id'),
        ];

        if (\App\Support\Configuracion\EntornoEmpresaSupport::esElBierzo()) {
            $rules['dest_localidad'] = 'nullable|string|max:80';
            $rules['dest_provincia'] = 'nullable|string|max:80';
            $rules['dest_pais_codigo'] = 'nullable|integer|min:1';
            $rules['dest_patagonico'] = 'nullable|boolean';
            $rules['dest_codigo_localidad_senasa'] = 'nullable|integer|min:1';
        }

        return $rules;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('dest_patagonico')) {
            $this->merge(['dest_patagonico' => $this->boolean('dest_patagonico')]);
        }
    }
}
