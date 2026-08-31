<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Support\Ventas\ConceptoVentaPlantillaMotor;
use App\Support\Ventas\ContratoVentaSupport;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ValidacionContrato_Venta extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('id');
        $empresaId = (int) $this->input('empresa_id', 0);

        return [
            'codigo' => [
                'required',
                'string',
                'max:20',
                Rule::unique('contrato_venta', 'codigo')
                    ->where(fn ($q) => $q->where('empresa_id', $empresaId > 0 ? $empresaId : 0))
                    ->ignore($id),
            ],
            'empresa_id' => ['required', 'integer', 'exists:empresa,id'],
            'cliente_id' => ['required', 'integer', 'exists:cliente,id'],
            'concepto_venta_id' => ['required', 'integer', 'exists:concepto_venta,id'],
            'estado' => ['required', 'string', Rule::in(ContratoVentaSupport::ESTADOS)],
            'vigencia_desde' => ['required', 'date'],
            'vigencia_hasta' => ['nullable', 'date', 'after_or_equal:vigencia_desde'],
            'periodicidad' => ['required', 'string', Rule::in(ContratoVentaSupport::PERIODICIDADES)],
            'dia_facturacion' => ['required', 'integer', 'min:1', 'max:28'],
            'precio' => ['nullable', 'numeric', 'min:0'],
            'moneda_id' => ['nullable', 'integer', 'exists:moneda,id'],
            'condicionventa_id' => ['nullable', 'integer', 'exists:condicionventa,id'],
            'observacion' => ['nullable', 'string', 'max:255'],
            'dato_claves' => ['nullable', 'array'],
            'dato_claves.*' => ['nullable', 'string', 'max:40'],
            'dato_valores' => ['nullable', 'array'],
            'dato_valores.*' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return [
            'codigo' => 'código',
            'empresa_id' => 'empresa',
            'cliente_id' => 'cliente',
            'concepto_venta_id' => 'concepto de venta',
            'estado' => 'estado',
            'vigencia_desde' => 'vigencia desde',
            'vigencia_hasta' => 'vigencia hasta',
            'periodicidad' => 'periodicidad',
            'dia_facturacion' => 'día de facturación',
            'precio' => 'precio',
            'observacion' => 'observación',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function (Validator $validator) {
            $empresaRepository = app(EmpresaRepositoryInterface::class);
            $empresaId = (int) $this->input('empresa_id', 0);
            if ($empresaId > 0 && ! $empresaRepository->empresaIdPermitida($empresaId)) {
                $validator->errors()->add('empresa_id', 'No tiene acceso a la empresa seleccionada.');
            }

            $vistos = [];
            foreach ((array) $this->input('dato_claves', []) as $i => $claveRaw) {
                $clave = ConceptoVentaPlantillaMotor::normalizarClave((string) $claveRaw);
                if ($clave === '') {
                    continue;
                }
                if (! ConceptoVentaPlantillaMotor::esClaveValida($clave)) {
                    $validator->errors()->add(
                        'dato_claves.'.$i,
                        'La clave del dato debe empezar con letra y solo usar a-z, 0-9 y _ (máx. 40).'
                    );
                    continue;
                }
                if (isset($vistos[$clave])) {
                    $validator->errors()->add('dato_claves.'.$i, 'La clave @'.$clave.'@ está duplicada.');
                    continue;
                }
                $vistos[$clave] = true;
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'codigo' => strtoupper(trim((string) $this->input('codigo', ''))),
            'estado' => ContratoVentaSupport::normalizarEstado((string) $this->input('estado', ContratoVentaSupport::ESTADO_ACTIVO)),
            'periodicidad' => ContratoVentaSupport::normalizarPeriodicidad(
                (string) $this->input('periodicidad', ContratoVentaSupport::PERIODICIDAD_MENSUAL)
            ),
        ]);
    }
}
