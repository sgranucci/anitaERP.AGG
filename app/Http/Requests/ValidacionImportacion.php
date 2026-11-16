<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Support\Compras\ImportacionEstados;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ValidacionImportacion extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'codigo' => 'nullable|string|max:40',
            'empresa_id' => 'required|integer|exists:empresa,id',
            'proveedor_id' => 'nullable|integer|exists:proveedor,id',
            'moneda_id' => 'nullable|integer|exists:moneda,id',
            'cotizacion' => 'nullable|numeric|min:0',
            'incoterm_id' => 'nullable|integer|exists:incoterm,id',
            'estado' => ['nullable', Rule::in(array_keys(ImportacionEstados::ETIQUETAS))],
            'fecha_apertura' => 'nullable|date',
            'fecha_etd' => 'nullable|date',
            'fecha_eta' => 'nullable|date',
            'fob_estimado' => 'nullable|numeric|min:0',
            'observacion' => 'nullable|string|max:4000',
            'exige_stock_para_cerrar' => 'nullable|boolean',
            'ordencompra_ids' => 'nullable|array',
            'ordencompra_ids.*' => 'integer|exists:ordencompra,id',
            'lote_ids' => 'nullable|array',
            'lote_ids.*' => 'integer|exists:lote,id',
            'embarques' => 'nullable|array',
            'embarques.*.id' => 'nullable|integer',
            'embarques.*.tipo_documento' => 'nullable|string|max:20',
            'embarques.*.numero_documento' => 'nullable|string|max:80',
            'embarques.*.contenedor' => 'nullable|string|max:60',
            'embarques.*.buque_vuelo' => 'nullable|string|max:120',
            'embarques.*.puerto_origen' => 'nullable|string|max:120',
            'embarques.*.puerto_destino' => 'nullable|string|max:120',
            'embarques.*.fecha_embarque' => 'nullable|date',
            'embarques.*.fecha_arribo' => 'nullable|date',
            'embarques.*.observacion' => 'nullable|string|max:2000',
            'gastos' => 'nullable|array',
            'gastos.*.id' => 'nullable|integer',
            'gastos.*.importacion_gasto_tipo_id' => 'nullable|integer|exists:importacion_gasto_tipo,id',
            'gastos.*.naturaleza' => 'nullable|in:estimado,real',
            'gastos.*.proveedor_id' => 'nullable|integer|exists:proveedor,id',
            'gastos.*.comprobante_proveedor_id' => 'nullable|integer|exists:comprobante_proveedor,id',
            'gastos.*.moneda_id' => 'nullable|integer|exists:moneda,id',
            'gastos.*.cotizacion' => 'nullable|numeric|min:0',
            'gastos.*.importe' => 'nullable|numeric',
            'gastos.*.importe_moneda_local' => 'nullable|numeric',
            'gastos.*.base_prorrateo' => 'nullable|string|max:20',
            'gastos.*.fecha' => 'nullable|date',
            'gastos.*.referencia' => 'nullable|string|max:120',
            'gastos.*.observacion' => 'nullable|string|max:2000',
            'archivos_quitar' => 'nullable|array',
            'archivos_quitar.*' => 'integer',
            'nombrearchivos' => 'nullable|array',
            'nombrearchivos.*' => 'file|max:20480',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'exige_stock_para_cerrar' => $this->boolean('exige_stock_para_cerrar'),
        ]);
    }
}
