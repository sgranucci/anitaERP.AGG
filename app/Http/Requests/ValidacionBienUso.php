<?php

namespace App\Http\Requests;

use App\Support\Contable\BienUsoAmortizacionUnidad;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class ValidacionBienUso extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('id');
        $tipoBien = (string) $this->input('tipo_bien', '');
        $empresaId = $this->input('empresa_id');
        $tieneContable = Schema::hasColumn('bien_uso', 'codigo_bien');

        $rules = [
            'empresa_id' => [
                Rule::requiredIf(in_array($tipoBien, ['M', 'I'], true)),
                'nullable',
                'integer',
                'exists:empresa,id',
            ],
            'codigo_inventario' => [
                'nullable',
                'integer',
                'min:1',
                Rule::unique('bien_uso', 'codigo_inventario')
                    ->where(static fn ($q) => $empresaId
                        ? $q->where('empresa_id', (int) $empresaId)
                        : $q->whereNull('empresa_id'))
                    ->ignore($id),
            ],
            'uid' => [
                Rule::requiredIf($tipoBien === 'M'),
                'nullable',
                'max:20',
                Rule::unique('bien_uso', 'uid')->ignore($id),
            ],
            'hostname' => [
                Rule::requiredIf($tipoBien === 'P'),
                'nullable',
                'max:255',
            ],
            'ip' => 'nullable|max:45',
            'modelo' => 'nullable|max:255',
            'vendor' => 'nullable|max:255',
            'tema' => 'nullable|max:255',
            'numero_serie' => 'nullable|max:100',
            'estado' => 'required|in:A,I',
            'centrocosto_id' => 'required|integer|exists:centrocosto,id',
            'tipo_bien' => 'required|in:I,M,P',
            'observaciones' => 'nullable|max:2000',
        ];

        if (! $tieneContable) {
            return $rules;
        }

        return array_merge($rules, [
            'codigo_bien' => [
                'nullable',
                'integer',
                'min:1',
                Rule::unique('bien_uso', 'codigo_bien')
                    ->where(static fn ($q) => $empresaId
                        ? $q->where('empresa_id', (int) $empresaId)
                        : $q->whereNull('empresa_id'))
                    ->ignore($id),
            ],
            'descripcion' => 'nullable|max:80',
            'bien_uso_rubro_id' => Schema::hasTable('bien_uso_rubro')
                ? 'nullable|integer|exists:bien_uso_rubro,id'
                : 'nullable|integer',
            'bien_uso_subrubro_id' => Schema::hasTable('bien_uso_subrubro')
                ? 'nullable|integer|exists:bien_uso_subrubro,id'
                : 'nullable|integer',
            'fecha_alta' => 'nullable|date',
            'fecha_compra' => 'nullable|date',
            'fecha_baja' => 'nullable|date',
            'fecha_baja_reemplazo' => 'nullable|date',
            'vida_util' => 'nullable|integer|min:0',
            'valor_origen' => 'nullable|numeric|min:0',
            'valor_baja' => 'nullable|numeric|min:0',
            'cuentacontable_origen_id' => 'nullable|integer|exists:cuentacontable,id',
            'cuentacontable_amort_id' => 'nullable|integer|exists:cuentacontable,id',
            'cuentacontable_contrap_id' => 'nullable|integer|exists:cuentacontable,id',
            'moneda_id' => 'nullable|integer|exists:moneda,id',
            'fl_amort_unid' => ['nullable', 'string', 'size:1', Rule::in(BienUsoAmortizacionUnidad::todas())],
            'marca' => 'nullable|max:20',
            'proveedor_id' => 'nullable|integer|exists:proveedor,id',
            'tipo_factura' => 'nullable|max:3',
            'letra_factura' => 'nullable|max:1',
            'sucursal_factura' => 'nullable|integer|min:0',
            'nro_factura' => 'nullable|integer|min:0',
            'monto_factura' => 'nullable|numeric|min:0',
            'fecha_factura' => 'nullable|date',
            'bien_reemplazo_id' => [
                'nullable',
                'integer',
                'exists:bien_uso,id',
                Rule::notIn([(int) $id]),
            ],
        ]);
    }

    public function attributes(): array
    {
        return [
            'empresa_id' => 'empresa',
            'codigo_bien' => 'código de bien',
            'descripcion' => 'descripción',
            'bien_uso_rubro_id' => 'rubro',
            'bien_uso_subrubro_id' => 'subrubro',
            'cuentacontable_origen_id' => 'cuenta origen',
            'cuentacontable_amort_id' => 'cuenta amortización',
            'cuentacontable_contrap_id' => 'cuenta contrapartida',
            'fl_amort_unid' => 'unidad de amortización',
            'bien_reemplazo_id' => 'bien de reemplazo',
            'codigo_inventario' => 'código de inventario',
            'uid' => 'UID',
            'numero_serie' => 'número de serie',
            'centrocosto_id' => 'centro de costo',
            'tipo_bien' => 'tipo de bien',
            'vendor' => 'fabricante / vendor',
            'tema' => 'tema / juego',
            'proveedor_id' => 'proveedor',
        ];
    }
}
