<?php

namespace App\Http\Requests;

use App\Models\Sueldos\Empleado_Ausencia_Sueldos;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ValidacionEmpleadoAusencia_Sueldos extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'tipo_ausencia_id' => 'required|integer|exists:tipo_ausencia_sueldos,id',
            'anio_imputacion' => 'nullable|integer|min:1990|max:2100',
            'fecha_desde' => 'required|date',
            'fecha_hasta' => 'required|date|after_or_equal:fecha_desde',
            'dias' => 'nullable|numeric|min:0|max:999',
            'tipo_dias' => ['nullable', 'string', Rule::in(['corridos', 'habiles'])],
            'estado' => ['required', Rule::in(array_keys(Empleado_Ausencia_Sueldos::ESTADOS))],
            'observacion' => 'nullable|string|max:255',
        ];
    }

    public function attributes()
    {
        return [
            'tipo_ausencia_id' => 'tipo de ausencia',
            'anio_imputacion' => 'período a imputar',
            'fecha_desde' => 'fecha desde',
            'fecha_hasta' => 'fecha hasta',
            'tipo_dias' => 'tipo de días',
        ];
    }
}
