<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ValidacionTipoarticulo extends FormRequest
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
            'nombre' => 'required|max:50|unique:tipoarticulo,nombre,' . $this->route('id'),
            'abreviatura' => 'required|max:10|unique:tipoarticulo,abreviatura,' . $this->route('id'),
            'usa_control_contable_cigarrillos' => 'sometimes|boolean',
            'letra_comprobante_compra' => 'nullable|string|size:1|regex:/^[A-Za-z]$/',
            'comprobante_compra_iva_indirecto' => 'sometimes|boolean',
        ];
    }

    protected function prepareForValidation(): void
    {
        $letra = strtoupper(trim((string) $this->input('letra_comprobante_compra', '')));

        $this->merge([
            'usa_control_contable_cigarrillos' => $this->boolean('usa_control_contable_cigarrillos'),
            'comprobante_compra_iva_indirecto' => $this->boolean('comprobante_compra_iva_indirecto'),
            'letra_comprobante_compra' => $letra === '' ? null : $letra,
        ]);
    }
}
