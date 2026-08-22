<?php

namespace App\Http\Requests;

use App\Support\Sueldos\EmpleadoSancionSupport;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ValidacionEmpleadoSancion_Sueldos extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'tipo_sancion_id' => 'required|integer|exists:tipo_sancion_sueldos,id',
            'motivo_sancion_id' => 'required|integer|exists:motivo_sancion_sueldos,id',
            'fecha_hecho' => 'required|date',
            'fecha_desde' => 'nullable|date',
            'fecha_hasta' => 'nullable|date|after_or_equal:fecha_desde',
            'cant_dias' => 'nullable|integer|min:0|max:999',
            'tipo_dias' => 'nullable|string|in:corridos,habiles',
            'importe_perdida' => 'nullable|numeric|min:0',
            'fecha_notificacion' => 'nullable|date',
            'fecha_recepcion' => 'nullable|date',
            'estado' => ['nullable', 'string', Rule::in(EmpleadoSancionSupport::estadosPermitidos())],
            'comentario' => 'required|string|min:3|max:4000',
            'descargo_texto' => 'nullable|string|max:4000',
            'descargo_fecha' => 'nullable|date',
            'resolucion_texto' => 'nullable|string|max:4000',
            'resolucion_fecha' => 'nullable|date',
        ];
    }

    public function attributes()
    {
        return [
            'tipo_sancion_id' => 'tipo de sanción',
            'motivo_sancion_id' => 'motivo',
            'fecha_hecho' => 'fecha del hecho',
            'comentario' => 'comentario',
        ];
    }
}
