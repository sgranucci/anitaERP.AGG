<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ValidacionDestino extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = (int) $this->route('id');

        return [
            'zonavta_id' => [
                'required',
                'integer',
                'exists:zonavta,id',
                Rule::unique('destino', 'zonavta_id')->ignore($id > 0 ? $id : null),
            ],
            'codigo' => [
                'nullable',
                'integer',
                'min:1',
                Rule::unique('destino', 'codigo')->ignore($id > 0 ? $id : null),
            ],
            'localidad' => 'required|string|max:80',
            'provincia' => 'nullable|string|max:80',
            'pais_codigo' => 'nullable|integer|min:1',
            'patagonico' => 'nullable|boolean',
            'codigo_localidad_senasa' => 'nullable|integer|min:1',
        ];
    }

    public function attributes(): array
    {
        return [
            'zonavta_id' => 'zona de venta',
            'codigo' => 'código de zona',
            'localidad' => 'localidad',
            'provincia' => 'provincia',
            'pais_codigo' => 'país',
            'codigo_localidad_senasa' => 'código de localidad SENASA',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'patagonico' => $this->boolean('patagonico'),
        ]);
    }
}
