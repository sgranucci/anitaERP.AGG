<?php

namespace App\Http\Requests;

use App\Support\Contable\AsientoReferenciaAnitaSupport;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
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

        $oc = $this->idReferenciaOpcional('ordencompra_id');
        $cp = $this->idReferenciaOpcional('comprobante_proveedor_id');
        $venta = $this->idReferenciaOpcional('venta_id');

        // La referencia es opcional. Un tipo elegido sin comprobante no puede
        // rechazar el asiento (el alta responde 422 y la pantalla lo muestra
        // como "Error del servidor").
        if ($tipo === AsientoReferenciaAnitaSupport::TIPO_ORDENCOMPRA && $oc === null) {
            $tipo = AsientoReferenciaAnitaSupport::TIPO_NINGUNA;
        } elseif ($tipo === AsientoReferenciaAnitaSupport::TIPO_COMPROBANTE_PROVEEDOR && $cp === null) {
            $tipo = AsientoReferenciaAnitaSupport::TIPO_NINGUNA;
        } elseif ($tipo === AsientoReferenciaAnitaSupport::TIPO_VENTA && $venta === null) {
            $tipo = AsientoReferenciaAnitaSupport::TIPO_NINGUNA;
        } elseif ($tipo === AsientoReferenciaAnitaSupport::TIPO_OC_Y_COMPROBANTE && ($oc === null || $cp === null)) {
            if ($oc !== null) {
                $tipo = AsientoReferenciaAnitaSupport::TIPO_ORDENCOMPRA;
                $cp = null;
            } elseif ($cp !== null) {
                $tipo = AsientoReferenciaAnitaSupport::TIPO_COMPROBANTE_PROVEEDOR;
                $oc = null;
            } else {
                $tipo = AsientoReferenciaAnitaSupport::TIPO_NINGUNA;
            }
        }

        if ($tipo === AsientoReferenciaAnitaSupport::TIPO_NINGUNA) {
            $oc = null;
            $cp = null;
            $venta = null;
        }

        $this->merge([
            'referencia_tipo' => $tipo,
            'ordencompra_id' => $oc,
            'comprobante_proveedor_id' => $cp,
            'venta_id' => $venta,
        ]);
    }

    private function idReferenciaOpcional(string $campo): ?int
    {
        if (! $this->filled($campo)) {
            return null;
        }

        $id = (int) $this->input($campo);

        return $id > 0 ? $id : null;
    }

    protected function failedValidation(Validator $validator): void
    {
        $mensajes = collect($validator->errors()->all())->filter()->unique()->implode(' ');

        throw new HttpResponseException(response()->json([
            'errores' => $mensajes !== '' ? $mensajes : 'No se pudo validar el asiento.',
        ]));
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
            'ordencompra_id.exists' => 'La orden de compra de referencia no existe.',
            'comprobante_proveedor_id.required' => 'Seleccione la factura de proveedor de referencia.',
            'comprobante_proveedor_id.exists' => 'La factura de proveedor de referencia no existe.',
            'venta_id.required' => 'Seleccione la factura de venta de referencia.',
            'venta_id.exists' => 'La factura de venta de referencia no existe.',
        ];
    }
}
