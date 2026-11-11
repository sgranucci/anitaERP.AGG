<?php

namespace App\Http\Requests;

use App\Models\Sueldos\Liquidacion_Sueldos;
use App\Support\Sueldos\LiquidacionAlcanceRecibo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ValidacionLiquidacion_Sueldos extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $id = $this->route('id');
        $esAlta = $id === null;
        // En edición la empresa no cambia: unicidad del código Anita por empresa del registro.
        $empresaId = $esAlta
            ? $this->input('empresa_id')
            : (Liquidacion_Sueldos::query()->whereKey($id)->value('empresa_id') ?? $this->input('empresa_id'));

        return [
            'empresa_id' => [Rule::requiredIf($esAlta), 'integer', 'exists:empresa,id'],
            // Código Anita (mael_liquidacion). Vacío en alta => próximo automático.
            'numero' => [
                Rule::requiredIf(! $esAlta),
                'nullable',
                'integer',
                'min:1',
                Rule::unique('liquidacion_sueldos', 'numero')
                    ->where(fn ($q) => $q->where('empresa_id', $empresaId))
                    ->ignore($id),
            ],
            'alcance' => ['nullable', 'string', Rule::in(LiquidacionAlcanceRecibo::permitidos())],
            'descripcion' => 'required|string|max:60',
            'tipo' => ['required', 'string', Rule::in(array_keys(Liquidacion_Sueldos::TIPOS))],
            'tipo_vacaciones' => [
                Rule::requiredIf(fn () => (string) $this->input('tipo') === 'vacaciones'),
                'nullable',
                'integer',
                Rule::in(array_keys(Liquidacion_Sueldos::TIPOS_VACACIONES)),
            ],
            'motivoegreso_id' => 'nullable|integer|exists:motivoegreso_sueldos,id',
            'periodo' => ['required', 'regex:/^\d{4}-?\d{2}$/'],
            'periodo_desde' => 'nullable|date',
            'periodo_hasta' => 'nullable|date|after_or_equal:periodo_desde',
            'fecha_liquidacion' => 'nullable|date',
            'fecha_pago' => 'nullable|date',
            'lugar_pago' => 'nullable|string|max:60',
            'simulacion' => 'nullable|boolean',
            'acumula_novedades' => 'nullable|boolean',
            'banco_deposito' => 'nullable|string|max:60',
            'periodo_deposito' => 'nullable|string|max:15',
            'fecha_ultimo_deposito' => 'nullable|date',
            'observacion' => 'nullable|string|max:2000',
        ];
    }

    public function attributes()
    {
        return [
            'empresa_id' => 'empresa',
            'numero' => 'código Anita',
            'descripcion' => 'descripción',
            'tipo' => 'tipo de liquidación',
            'tipo_vacaciones' => 'tipo de vacaciones (plus/adelanto)',
            'periodo' => 'período',
            'fecha_pago' => 'fecha de pago',
        ];
    }

    protected function prepareForValidation(): void
    {
        $numero = $this->input('numero');
        $tipo = (string) $this->input('tipo');
        $tipoVac = $this->input('tipo_vacaciones');
        $this->merge([
            'periodo' => preg_replace('/\D+/', '', (string) $this->input('periodo')),
            'numero' => ($numero === null || $numero === '') ? null : (int) preg_replace('/\D+/', '', (string) $numero),
            'tipo_vacaciones' => $tipo === 'vacaciones'
                ? (($tipoVac === null || $tipoVac === '') ? null : (int) $tipoVac)
                : null,
            'simulacion' => $this->boolean('simulacion'),
            'acumula_novedades' => $this->has('acumula_novedades') ? $this->boolean('acumula_novedades') : true,
            'alcance' => LiquidacionAlcanceRecibo::normalizar($this->input('alcance')),
        ]);
    }
}
