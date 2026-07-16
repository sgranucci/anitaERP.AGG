<?php

namespace App\Http\Requests;

use App\Support\Solicitudpago\ConceptoSolicitudpagoEstados;
use App\Support\Solicitudpago\ConceptoSolicitudpagoFormaPago;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ValidacionConcepto_Solicitudpago extends FormRequest
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
                Rule::unique('concepto_solicitudpago', 'codigo')->ignore($id),
            ],
            'nombre' => 'required|string|max:50',
            'sector_solicitudpago_id' => 'nullable|exists:sector_solicitudpago,id',
            'forma_pago' => [
                'required',
                Rule::in([ConceptoSolicitudpagoFormaPago::SIN_CUOTAS, ConceptoSolicitudpagoFormaPago::CUOTAS]),
            ],
            'estado' => [
                'required',
                Rule::in([ConceptoSolicitudpagoEstados::ACTIVO, ConceptoSolicitudpagoEstados::SUSPENDIDO]),
            ],
            'niveles' => 'nullable|array',
            'niveles.*' => 'nullable|integer|min:1',
            'usuario_ids' => 'nullable|array',
            'usuario_ids.*' => 'nullable|exists:usuario,id',
            'desdemontos' => 'nullable|array',
            'desdemontos.*' => 'nullable|numeric|min:0',
            'empresa_ids' => 'nullable|array',
            'empresa_ids.*' => 'nullable|exists:empresa,id',
            'cuentacontable_ids' => 'nullable|array',
            'cuentacontable_ids.*' => 'nullable|exists:cuentacontable,id',
            'centrocosto_ids' => 'nullable|array',
            'centrocosto_ids.*' => 'nullable|exists:centrocosto,id',
            'debe_haberes' => 'nullable|array',
            'debe_haberes.*' => 'nullable|in:D,H',
            'formapagosol_ids' => 'nullable|array',
            'formapagosol_ids.*' => 'nullable|exists:formapagosol,id',
        ];
    }

    public function attributes()
    {
        return [
            'codigo' => 'código',
            'nombre' => 'descripción',
            'sector_solicitudpago_id' => 'sector',
            'forma_pago' => 'forma de pago',
            'estado' => 'estado',
        ];
    }
}
