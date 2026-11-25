<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ValidacionGrupo_Liq_Final_Sueldos extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'empresa_id' => ['required', 'integer', 'exists:empresa,id'],
            'sindicato_id' => ['required', 'integer', 'exists:sindicato_sueldos,id'],
            'motivoegreso_id' => ['nullable', 'integer', 'exists:motivoegreso_sueldos,id'],
            'grupo_concepto_id' => [
                'required',
                'integer',
                Rule::exists('grupo_concepto_sueldos', 'id')->where(function ($q) {
                    $q->where('activo', true);
                    if ($this->filled('empresa_id')) {
                        $q->where(function ($w) {
                            $w->whereNull('empresa_id')
                                ->orWhere('empresa_id', (int) $this->input('empresa_id'));
                        });
                    }
                }),
            ],
            'activo' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            if ($v->errors()->isNotEmpty()) {
                return;
            }
            $empresaId = (int) $this->input('empresa_id');
            $sindicatoId = (int) $this->input('sindicato_id');
            $motivoId = $this->filled('motivoegreso_id') ? (int) $this->input('motivoegreso_id') : null;
            $ignoreId = (int) $this->route('id');

            $q = \App\Models\Sueldos\Grupo_Liq_Final_Sueldos::query()
                ->where('empresa_id', $empresaId)
                ->where('sindicato_id', $sindicatoId);
            if ($motivoId === null) {
                $q->whereNull('motivoegreso_id');
            } else {
                $q->where('motivoegreso_id', $motivoId);
            }
            if ($ignoreId > 0) {
                $q->where('id', '!=', $ignoreId);
            }
            if ($q->exists()) {
                $v->errors()->add(
                    'motivoegreso_id',
                    'Ya existe una regla para esa empresa, sindicato y motivo.'
                );
            }
        });
    }

    public function messages(): array
    {
        return [
            'empresa_id.required' => 'Seleccioná la empresa.',
            'sindicato_id.required' => 'Seleccioná el sindicato.',
            'grupo_concepto_id.required' => 'Seleccioná el grupo de conceptos.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'activo' => $this->boolean('activo'),
            'motivoegreso_id' => $this->filled('motivoegreso_id') ? $this->input('motivoegreso_id') : null,
        ]);
    }
}
