<?php

namespace App\Http\Requests;

use App\Models\Contable\Cuentacontable;
use App\Models\Sueldos\Concepto_Sueldos;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Support\Sueldos\ConceptoTipo;
use App\Support\Sueldos\RubroCostoLaboral;
use App\Support\Sueldos\SueldosAsientoMapeoSupport;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ValidacionConceptoImputacion_Sueldos extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $id = $this->route('id');
        $empresaId = (int) $this->input('empresa_id');
        $alcance = (string) $this->input('alcance');
        $clave = SueldosAsientoMapeoSupport::clavePara(
            $alcance,
            $this->filled('concepto_id') ? (int) $this->input('concepto_id') : null,
            $this->input('rubro'),
            $this->input('tipo')
        );

        return [
            'empresa_id' => ['required', 'integer', 'exists:empresa,id'],
            'alcance' => ['required', 'string', Rule::in(array_keys(SueldosAsientoMapeoSupport::ALCANCES))],
            'concepto_id' => [
                Rule::requiredIf($alcance === SueldosAsientoMapeoSupport::ALCANCE_CONCEPTO),
                'nullable',
                'integer',
                'exists:concepto_sueldos,id',
            ],
            'rubro' => [
                Rule::requiredIf($alcance === SueldosAsientoMapeoSupport::ALCANCE_RUBRO),
                'nullable',
                'string',
                Rule::in(RubroCostoLaboral::todos()),
            ],
            'tipo' => [
                Rule::requiredIf($alcance === SueldosAsientoMapeoSupport::ALCANCE_TIPO),
                'nullable',
                'string',
                Rule::in(SueldosAsientoMapeoSupport::tiposImputables()),
            ],
            'clave' => [
                'required',
                'string',
                'max:64',
                Rule::unique('concepto_imputacion_sueldos', 'clave')
                    ->where(fn ($q) => $q->where('empresa_id', $empresaId)->where('alcance', $alcance))
                    ->ignore($id),
            ],
            'cuenta_debe_id' => ['nullable', 'integer', 'exists:cuentacontable,id'],
            'cuenta_haber_id' => ['nullable', 'integer', 'exists:cuentacontable,id'],
            'observacion' => ['nullable', 'string', 'max:160'],
        ];
    }

    public function attributes()
    {
        return [
            'empresa_id' => 'empresa',
            'alcance' => 'alcance',
            'concepto_id' => 'concepto',
            'rubro' => 'rubro de costo laboral',
            'tipo' => 'tipo de concepto',
            'cuenta_debe_id' => 'cuenta debe',
            'cuenta_haber_id' => 'cuenta haber',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $empresaId = (int) $this->input('empresa_id');
            if ($empresaId > 0 && ! app(EmpresaRepositoryInterface::class)->empresaIdPermitida($empresaId)) {
                $v->errors()->add('empresa_id', 'No tiene permiso para configurar esa empresa.');
            }

            $debe = (int) $this->input('cuenta_debe_id');
            $haber = (int) $this->input('cuenta_haber_id');
            if ($debe <= 0 && $haber <= 0) {
                $v->errors()->add('cuenta_debe_id', 'Indique al menos una cuenta (debe o haber).');
            }

            $this->assertCuentaDeEmpresa($v, $empresaId, $debe, 'cuenta_debe_id');
            $this->assertCuentaDeEmpresa($v, $empresaId, $haber, 'cuenta_haber_id');

            $alcance = (string) $this->input('alcance');
            if ($alcance === SueldosAsientoMapeoSupport::ALCANCE_CONCEPTO) {
                $tipoConcepto = ConceptoTipo::normalizarTipo(
                    (string) (Concepto_Sueldos::query()
                        ->whereKey((int) $this->input('concepto_id'))
                        ->value('tipo') ?? '')
                );
                if ($tipoConcepto === 'neto') {
                    $v->errors()->add('concepto_id', 'El concepto neto no imputa en el asiento.');
                }
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $alcance = (string) $this->input('alcance');
        $conceptoId = $alcance === SueldosAsientoMapeoSupport::ALCANCE_CONCEPTO
            ? (int) $this->input('concepto_id')
            : null;
        $rubro = $alcance === SueldosAsientoMapeoSupport::ALCANCE_RUBRO
            ? trim((string) $this->input('rubro'))
            : null;
        $tipo = $alcance === SueldosAsientoMapeoSupport::ALCANCE_TIPO
            ? trim((string) $this->input('tipo'))
            : null;

        $this->merge([
            'clave' => SueldosAsientoMapeoSupport::clavePara($alcance, $conceptoId, $rubro, $tipo),
            'cuenta_debe_id' => $this->filled('cuenta_debe_id') ? $this->input('cuenta_debe_id') : null,
            'cuenta_haber_id' => $this->filled('cuenta_haber_id') ? $this->input('cuenta_haber_id') : null,
        ]);
    }

    private function assertCuentaDeEmpresa(Validator $v, int $empresaId, int $cuentaId, string $campo): void
    {
        if ($empresaId <= 0 || $cuentaId <= 0) {
            return;
        }

        $ok = Cuentacontable::query()
            ->whereKey($cuentaId)
            ->where('empresa_id', $empresaId)
            ->exists();
        if (! $ok) {
            $v->errors()->add($campo, 'La cuenta no pertenece a la empresa del mapeo.');
        }
    }
}
