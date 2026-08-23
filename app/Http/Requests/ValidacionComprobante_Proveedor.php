<?php

namespace App\Http\Requests;

use App\Support\Compras\ComprobanteProveedorFechaContableSupport;
use App\Support\Compras\ComprobanteProveedorModoCarga;
use App\Support\Compras\ComprobanteProveedorTipoAutorizacion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use RuntimeException;

class ValidacionComprobante_Proveedor extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'empresa_id' => 'required|integer|min:1',
            'proveedor_id' => 'required|integer|min:1',
            'tipotransaccion_compra_id' => 'required|integer|min:1',
            'letra' => 'required|string|size:1',
            'sucursal' => 'required|integer|min:0',
            'numerocomprobante' => 'required|integer|min:1',
            'fechacomprobante' => 'required|date|before_or_equal:'.ComprobanteProveedorFechaContableSupport::fechaComprobanteMaximaYmd(),
            'fechaiva' => 'nullable|date',
            'moneda_id' => 'required|integer|min:1',
            'subtotal' => 'nullable|numeric',
            'total' => 'nullable|numeric',
            'cotizacion' => 'nullable|numeric',
            'numerocae' => 'nullable|string|max:30',
            'tipo_autorizacion' => 'nullable|string|in:'.implode(',', ComprobanteProveedorTipoAutorizacion::todos()),
            'modo_carga' => 'nullable|string|in:'.implode(',', ComprobanteProveedorModoCarga::todos()),
            'recepcion_proveedor_ids' => 'nullable|array',
            'recepcion_proveedor_ids.*' => 'integer|min:1',
            'concepto_ivacompra_ids' => 'nullable|array',
            'concepto_ivacompra_ids.*' => 'nullable|integer',
            'montos' => 'nullable|array',
            'montos.*' => 'nullable|numeric',
            'cuentacontabledebe_ids' => 'nullable|array',
            'cuentacontabledebe_ids.*' => 'nullable|integer',
        ];
    }

    public function messages(): array
    {
        $dias = ComprobanteProveedorFechaContableSupport::maxDiasFuturoComprobante();

        return [
            'fechacomprobante.before_or_equal' => 'La fecha del comprobante no puede ser más de '
                .$dias.' días posterior a hoy. Revisá el año o el mes: parece un error de carga.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->has('fechacomprobante')) {
                return;
            }
            try {
                ComprobanteProveedorFechaContableSupport::assertFechaComprobanteNoExcesivamenteFutura(
                    $this->input('fechacomprobante')
                );
            } catch (RuntimeException $e) {
                $validator->errors()->add('fechacomprobante', $e->getMessage());
            }
        });
    }
}
