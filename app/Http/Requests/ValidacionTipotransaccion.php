<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ValidacionTipotransaccion extends FormRequest
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
            'nombre' => 'required|max:255|unique:tipotransaccion,nombre,' . $this->route('id'),
            'abreviatura' => 'required|max:5|unique:tipotransaccion,abreviatura,' . $this->route('id'),
            'codigo' => 'required|string|max:50',
            'operacion' => ['required', Rule::in(array_keys(\App\Traits\Ventas\TipotransaccionTrait::$enumOperacion))],
            'operacionstock' => ['required', Rule::in(array_keys(\App\Traits\Ventas\TipotransaccionTrait::$enumOperacionStock))],
            'concepto_venta_id' => ['nullable', 'integer', 'exists:concepto_venta,id'],
            'iva_ventas' => 'sometimes|boolean',
        ];
    }

    protected function prepareForValidation(): void
    {
        $conceptoId = $this->input('concepto_venta_id');
        if ($conceptoId === '' || $conceptoId === '0' || $conceptoId === 0) {
            $this->merge(['concepto_venta_id' => null]);
        }

        $this->merge([
            'iva_ventas' => $this->boolean('iva_ventas'),
        ]);
    }
}
