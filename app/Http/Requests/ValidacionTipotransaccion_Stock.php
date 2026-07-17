<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ValidacionTipotransaccion_Stock extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'nombre' => 'required|max:255|unique:tipotransaccion_stock,nombre,'.$this->route('id'),
            'abreviatura' => 'required|max:15|unique:tipotransaccion_stock,abreviatura,'.$this->route('id'),
            'operacion' => ['required', Rule::in(array_keys(\App\Traits\Stock\Tipotransaccion_StockTrait::$enumOperacion))],
            'signo' => ['required', Rule::in(array_keys(\App\Traits\Stock\Tipotransaccion_StockTrait::$enumSigno))],
            'estado' => ['required', Rule::in(array_keys(\App\Traits\Stock\Tipotransaccion_StockTrait::$enumEstado))],
            'requiere_aprobacion' => 'sometimes|boolean',
            'aviso_opcional' => 'sometimes|boolean',
            'maneja_contabilidad' => 'sometimes|boolean',
            'destino_bien_uso' => 'sometimes|boolean',
            'origen_bien_uso' => 'sometimes|boolean',
            'baja_npu' => 'sometimes|boolean',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->boolean('aviso_opcional') && $this->input('operacion') !== 'T') {
                $validator->errors()->add('aviso_opcional', 'El aviso opcional solo aplica a tipos de operación Transferencia (T).');
            }

            if (! $this->boolean('baja_npu')) {
                return;
            }

            if ($this->input('operacion') !== 'S') {
                $validator->errors()->add('baja_npu', 'La baja de NPU solo aplica a tipos de salida de stock.');
            }

            if ($this->input('signo') !== 'R') {
                $validator->errors()->add('baja_npu', 'La baja de NPU requiere signo Resta.');
            }
        });
    }
}
