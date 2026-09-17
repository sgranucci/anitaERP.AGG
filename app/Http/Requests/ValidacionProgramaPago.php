<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ValidacionProgramaPago extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        if ($this->isMethod('post') && ! $this->route('id')) {
            return [
                'empresa_id' => 'required|integer|exists:empresa,id',
                'titulo' => 'nullable|string|max:120',
                'fecha_base' => 'required|date',
                'anio_mes_inicio' => ['required', 'regex:/^\d{4}-\d{2}$/'],
                'cantidad_meses' => 'required|integer|min:1|max:12',
                'incluye_transf' => 'nullable|boolean',
                'detalle' => 'nullable|string|max:2000',
                'sembrar_deuda' => 'nullable|boolean',
            ];
        }

        return [
            'titulo' => 'nullable|string|max:120',
            'detalle' => 'nullable|string|max:2000',
            'estado' => 'nullable|in:BORRADOR,CERRADO',
            'lineas' => 'nullable|array',
            'lineas.*.id' => 'nullable|integer',
            'lineas.*.proveedor_id' => 'nullable|integer',
            'lineas.*.saldo_adeudado' => 'nullable|numeric',
            'lineas.*.observacion' => 'nullable|string|max:2000',
            'lineas.*.asignaciones' => 'nullable|array',
            'lineas.*.asignaciones.*' => 'nullable|numeric',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('incluye_transf')) {
            $this->merge([
                'incluye_transf' => filter_var($this->input('incluye_transf'), FILTER_VALIDATE_BOOLEAN),
            ]);
        }
        if ($this->has('sembrar_deuda')) {
            $this->merge([
                'sembrar_deuda' => filter_var($this->input('sembrar_deuda'), FILTER_VALIDATE_BOOLEAN),
            ]);
        }
    }
}
