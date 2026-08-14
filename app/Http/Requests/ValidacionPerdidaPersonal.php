<?php

namespace App\Http\Requests;

use App\Models\Caja\ConceptoPerdida;
use App\Models\Caja\PerdidaPersonal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ValidacionPerdidaPersonal extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $exceptoId = (int) $this->route('id');

        return [
            'empresa_id' => 'required|integer|exists:empresa,id',
            'numero' => [
                'nullable',
                'integer',
                'min:1',
                Rule::unique('perdida_personal', 'numero')->ignore($exceptoId),
            ],
            'fecha' => 'required|date',
            'centrocosto_id' => 'required|integer|exists:centrocosto,id',
            'imputacion_perdida_id' => 'required|integer|exists:imputacion_perdida,id',
            'concepto_perdida_id' => 'required|integer|exists:concepto_perdida,id',
            'empleado_sueldos_id' => 'required|integer|exists:empleado_sueldos,id',
            'supervisor_empleado_sueldos_id' => 'required|integer|exists:empleado_sueldos,id',
            'turno' => ['required', Rule::in([
                PerdidaPersonal::TURNO_MANIANA,
                PerdidaPersonal::TURNO_TARDE,
                PerdidaPersonal::TURNO_NOCHE,
            ])],
            'importe' => 'required|numeric|gt:0',
            'leyenda' => 'nullable|string|max:80',
            'maquina' => 'nullable|string|max:10',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $conceptoId = (int) $this->input('concepto_perdida_id', 0);
            if ($conceptoId <= 0) {
                return;
            }

            $codigo = (int) ConceptoPerdida::query()->whereKey($conceptoId)->value('codigo');
            if (in_array($codigo, PerdidaPersonal::CONCEPTOS_CON_MAQUINA, true)) {
                $maquina = trim((string) $this->input('maquina', ''));
                if ($maquina === '') {
                    $validator->errors()->add(
                        'maquina',
                        'La máquina es obligatoria para el concepto '.$codigo.'.'
                    );
                }
            }
        });
    }

    public function messages()
    {
        return [
            'empresa_id.required' => 'La empresa es obligatoria.',
            'fecha.required' => 'La fecha es obligatoria.',
            'centrocosto_id.required' => 'El centro de costo es obligatorio.',
            'imputacion_perdida_id.required' => 'La imputación es obligatoria.',
            'concepto_perdida_id.required' => 'El concepto es obligatorio.',
            'empleado_sueldos_id.required' => 'El empleado es obligatorio.',
            'supervisor_empleado_sueldos_id.required' => 'El supervisor es obligatorio.',
            'turno.required' => 'El turno es obligatorio.',
            'turno.in' => 'El turno debe ser Mañana, Tarde o Noche.',
            'importe.required' => 'El importe es obligatorio.',
            'importe.gt' => 'El importe debe ser mayor que cero.',
            'leyenda.max' => 'La leyenda no puede superar 80 caracteres.',
            'maquina.max' => 'La máquina no puede superar 10 caracteres.',
            'numero.unique' => 'Ya existe una pérdida de personal con ese número.',
        ];
    }
}
