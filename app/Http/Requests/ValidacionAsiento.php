<?php

namespace App\Http\Requests;

use App\Support\Contable\AsientoReferenciaAnitaSupport;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ValidacionAsiento extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $tipo = (string) $this->input('referencia_tipo', AsientoReferenciaAnitaSupport::TIPO_NINGUNA);
        if (! in_array($tipo, AsientoReferenciaAnitaSupport::tiposValidos(), true)) {
            $tipo = AsientoReferenciaAnitaSupport::TIPO_NINGUNA;
        }

        $this->merge([
            'referencia_tipo' => $tipo,
            'ordencompra_id' => $this->filled('ordencompra_id') ? (int) $this->input('ordencompra_id') : null,
            'comprobante_proveedor_id' => $this->filled('comprobante_proveedor_id') ? (int) $this->input('comprobante_proveedor_id') : null,
            'venta_id' => $this->filled('venta_id') ? (int) $this->input('venta_id') : null,
        ]);
    }

    public function rules()
    {
        $tipo = (string) $this->input('referencia_tipo', AsientoReferenciaAnitaSupport::TIPO_NINGUNA);

        $rules = [
            'referencia_tipo' => ['nullable', Rule::in(AsientoReferenciaAnitaSupport::tiposValidos())],
            'ordencompra_id' => ['nullable', 'integer', 'exists:ordencompra,id'],
            'comprobante_proveedor_id' => ['nullable', 'integer', 'exists:comprobante_proveedor,id'],
            'venta_id' => ['nullable', 'integer', 'exists:venta,id'],
        ];

        if ($tipo === AsientoReferenciaAnitaSupport::TIPO_ORDENCOMPRA) {
            $rules['ordencompra_id'] = ['required', 'integer', 'exists:ordencompra,id'];
        } elseif ($tipo === AsientoReferenciaAnitaSupport::TIPO_COMPROBANTE_PROVEEDOR) {
            $rules['comprobante_proveedor_id'] = ['required', 'integer', 'exists:comprobante_proveedor,id'];
        } elseif ($tipo === AsientoReferenciaAnitaSupport::TIPO_VENTA) {
            $rules['venta_id'] = ['required', 'integer', 'exists:venta,id'];
        } elseif ($tipo === AsientoReferenciaAnitaSupport::TIPO_OC_Y_COMPROBANTE) {
            $rules['ordencompra_id'] = ['required', 'integer', 'exists:ordencompra,id'];
            $rules['comprobante_proveedor_id'] = ['required', 'integer', 'exists:comprobante_proveedor,id'];
        }

        return $rules;
    }

    public function messages()
    {
        return [
            'ordencompra_id.required' => 'Seleccione la orden de compra de referencia.',
            'comprobante_proveedor_id.required' => 'Seleccione la factura de proveedor de referencia.',
            'venta_id.required' => 'Seleccione la factura de venta de referencia.',
        ];
    }
}
