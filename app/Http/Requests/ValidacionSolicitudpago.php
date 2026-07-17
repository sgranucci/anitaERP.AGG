<?php

namespace App\Http\Requests;

use App\Support\Solicitudpago\SolicitudpagoEstados;
use App\Support\Solicitudpago\SolicitudpagoTratamientos;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ValidacionSolicitudpago extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $id = $this->route('id');

        return [
            'codigo' => [
                'nullable',
                'integer',
                'min:1',
                Rule::unique('solicitudpago', 'codigo')->ignore($id),
            ],
            'empresa_id' => 'required|exists:empresa,id',
            'fecha' => 'required|date',
            'tratamiento' => ['required', Rule::in(array_column(SolicitudpagoTratamientos::opciones(), 'valor'))],
            'proveedor_id' => 'nullable|exists:proveedor,id',
            'concepto_solicitudpago_id' => 'nullable|exists:concepto_solicitudpago,id',
            'formapagosol_id' => 'nullable|exists:formapagosol,id',
            'moneda_id' => 'nullable|exists:moneda,id',
            'beneficiario' => 'nullable|string|max:80',
            'endoso' => 'nullable|string|max:80',
            'fecha_entrega' => 'nullable|date',
            'fecha_vencimiento' => 'nullable|date',
            'monto' => 'required|numeric|min:0',
            'observacion' => 'nullable|string|max:160',
            'estado' => ['nullable', Rule::in(array_column(SolicitudpagoEstados::opciones(), 'valor'))],
            'sector_solicitudpago_id' => 'nullable|exists:sector_solicitudpago,id',
            'detalle' => 'nullable|string|max:180',
            'solicitudpago_madre_id' => 'nullable|exists:solicitudpago,id',
            'empresa_ids' => 'nullable|array',
            'empresa_ids.*' => 'nullable|exists:empresa,id',
            'cuentacontable_ids' => 'nullable|array',
            'cuentacontable_ids.*' => 'nullable|exists:cuentacontable,id',
            'centrocosto_ids' => 'nullable|array',
            'centrocosto_ids.*' => 'nullable|exists:centrocosto,id',
            'debe_haberes' => 'nullable|array',
            'debe_haberes.*' => 'nullable|in:D,H',
            'montos_cuenta' => 'nullable|array',
            'montos_cuenta.*' => 'nullable|numeric',
            'nro_cuotas' => 'nullable|array',
            'nro_cuotas.*' => 'nullable|integer|min:1',
            'fecha_vencimientos_cuota' => 'nullable|array',
            'fecha_vencimientos_cuota.*' => 'nullable|date',
            'montos_cuota' => 'nullable|array',
            'montos_cuota.*' => 'nullable|numeric',
            'solicitudpago_hija_ids' => 'nullable|array',
            'solicitudpago_hija_ids.*' => 'nullable|exists:solicitudpago,id',
            'archivo_ids_existentes' => 'nullable|array',
            'archivo_ids_existentes.*' => 'nullable|integer',
            'archivos_nuevos' => 'nullable|array',
            'archivos_nuevos.*' => 'nullable|file|max:10240',
        ];
    }

    public function attributes()
    {
        return [
            'empresa_id' => 'empresa',
            'concepto_solicitudpago_id' => 'concepto',
            'formapagosol_id' => 'forma de pago',
            'sector_solicitudpago_id' => 'sector',
            'moneda_id' => 'moneda',
            'proveedor_id' => 'proveedor',
        ];
    }
}
