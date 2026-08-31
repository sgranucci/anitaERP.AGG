<?php

namespace App\Http\Requests;

use App\Support\Sueldos\ConceptoTipo;
use App\Support\Sueldos\RubroCostoLaboral;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ValidacionConcepto_Sueldos extends FormRequest
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
                Rule::unique('concepto_sueldos', 'codigo')->ignore($id),
            ],
            'descripcion' => 'required|string|max:60',
            'tipo' => ['required', 'string', Rule::in(ConceptoTipo::tiposPermitidos())],
            'suma_a' => ['nullable', 'string', Rule::in(ConceptoTipo::basesPermitidas())],
            'momento' => ['required', 'string', Rule::in(ConceptoTipo::momentosPermitidos())],
            'factor' => 'nullable|numeric',
            'formula' => 'nullable|string|max:2000',
            'formula_cantidad' => 'nullable|string|max:2000',
            'formula_valor' => 'nullable|string|max:2000',
            'va_recibo' => 'nullable|boolean',
            'mes_retroactivo' => 'nullable|integer|min:-99|max:12',
            'leyenda_recibo' => 'nullable|string|max:2000',
            'concepto_afip' => 'nullable|string|max:6',
            'concepto_afip_libre' => 'nullable|string|max:6',
            'codigo_lsd_empleador' => 'nullable|string|max:10',
            'lsd_repetible' => 'nullable|boolean',
            'lsd_subsistemas' => 'nullable|array',
            'lsd_subsistemas.*' => 'nullable',
            'lsd_bases' => 'nullable|array',
            'lsd_bases.*' => 'nullable|integer|in:-1,0,1',
            'rubro_costo_laboral' => ['nullable', 'string', Rule::in(RubroCostoLaboral::todos())],
            'unidad_medida' => 'nullable|string|max:4',
            'activo' => 'nullable|boolean',
            'orden' => 'nullable|integer|min:0',
            'acumuladores_override' => 'nullable|array',
            'acumuladores_override.*.accion' => 'nullable|string|in:auto,incluir,excluir',
            'acumuladores_override.*.signo' => 'nullable|integer|in:1,-1',
        ];
    }

    public function attributes()
    {
        return [
            'codigo' => 'código',
            'descripcion' => 'descripción',
            'tipo' => 'tipo de concepto',
            'suma_a' => 'base / acumulador',
            'momento' => 'momento de liquidación',
            'factor' => 'factor',
            'mes_retroactivo' => 'mes retroactivo',
            'concepto_afip' => 'concepto AFIP',
            'codigo_lsd_empleador' => 'código empleador LSD',
        ];
    }

    protected function prepareForValidation(): void
    {
        $libre = preg_replace('/\D+/', '', (string) $this->input('concepto_afip_libre', '')) ?? '';
        $merge = [
            'va_recibo' => $this->boolean('va_recibo'),
            'lsd_repetible' => $this->boolean('lsd_repetible'),
            'activo' => $this->has('activo') ? $this->boolean('activo') : true,
        ];
        if ($libre !== '') {
            $merge['concepto_afip'] = str_pad(substr($libre, -6), 6, '0', STR_PAD_LEFT);
        }
        $this->merge($merge);
    }
}
