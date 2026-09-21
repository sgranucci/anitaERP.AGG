<?php

namespace App\Http\Requests;

use App\Support\Ventas\FacturacionLocal\CambioDevolucionMarketplaceCatalogoSupport;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ValidacionCambioDevolucionMarketplace extends FormRequest
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
        return [
            'canal' => ['required', Rule::in(array_keys(CambioDevolucionMarketplaceCatalogoSupport::CANALES))],
            'local_venta_id' => 'required|integer|exists:local_venta,id',
            'empresa_id' => 'nullable|integer|exists:empresa,id',
            'tiendanube_pedido_id' => 'nullable|integer',
            'venta_original_id' => 'required|integer|exists:venta,id',
            'cliente_id' => 'nullable|integer|exists:cliente,id',
            'receptor_nombre' => 'nullable|string|max:120',
            'receptor_documento' => 'nullable|string|max:30',
            'motivo_codigo' => ['nullable', Rule::in(array_keys(CambioDevolucionMarketplaceCatalogoSupport::MOTIVOS))],
            'motivo' => 'nullable|string|max:255',
            'observacion' => 'nullable|string',
            'lineas' => 'nullable|array',
            'lineas.*.tipo' => ['nullable', Rule::in(array_keys(CambioDevolucionMarketplaceCatalogoSupport::TIPOS_LINEA))],
            'lineas.*.articulo_id' => 'nullable|integer|exists:articulo,id',
            'lineas.*.talle_id' => 'nullable|integer',
            'lineas.*.color_id' => 'nullable|integer',
            'lineas.*.combinacion_id' => 'nullable|integer',
            'lineas.*.cantidad' => 'nullable|numeric|min:0.0001',
            'lineas.*.precio_unitario' => 'nullable|numeric|min:0',
            'lineas.*.descripcion' => 'nullable|string|max:255',
            'lineas.*.venta_emision_id' => 'nullable|integer',
            'nombrearchivos' => 'nullable|array',
            'nombrearchivos.*' => 'nullable|file|max:10240',
            'nombresanteriores' => 'nullable|array',
            'nombresanteriores.*' => 'nullable|string|max:255',
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function lineasNormalizadas(): array
    {
        $out = [];
        foreach ($this->input('lineas', []) as $linea) {
            if (! is_array($linea)) {
                continue;
            }
            $articuloId = (int) ($linea['articulo_id'] ?? 0);
            $tipo = (string) ($linea['tipo'] ?? '');
            if ($articuloId <= 0 || $tipo === '') {
                continue;
            }
            $out[] = $linea;
        }

        return $out;
    }
}
