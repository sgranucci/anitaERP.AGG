<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ValidacionRemitoInterno extends FormRequest
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
            'fecha' => 'required|date',
            'local_venta_id' => 'required|integer|exists:local_venta,id',
            'destinatario' => 'nullable|string|max:160',
            'leyenda' => 'nullable|string|max:255',
            'observacion' => 'nullable|string|max:2000',
            'lineas' => 'required|array|min:1',
            'lineas.*.id' => 'nullable|integer',
            'lineas.*.articulo_id' => 'required|integer|exists:articulo,id',
            'lineas.*.articulo_codigo' => 'nullable|string|max:40',
            'lineas.*.descripcion' => 'nullable|string|max:255',
            'lineas.*.combinacion_id' => 'nullable|integer',
            'lineas.*.talle_id' => 'nullable|integer',
            'lineas.*.color_id' => 'nullable|integer',
            'lineas.*.modulo_id' => 'nullable|integer',
            'lineas.*.cantidad' => 'required|numeric|min:0.0001',
            'lineas.*.combinacion_label' => 'nullable|string|max:120',
            'lineas.*.talle_label' => 'nullable|string|max:40',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'fecha' => 'fecha',
            'local_venta_id' => 'local',
            'lineas' => 'líneas',
            'lineas.*.articulo_id' => 'artículo',
            'lineas.*.cantidad' => 'cantidad',
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
            $cantidad = (float) ($linea['cantidad'] ?? 0);
            if ($articuloId <= 0 || $cantidad <= 0) {
                continue;
            }
            $out[] = [
                'id' => (int) ($linea['id'] ?? 0) ?: null,
                'articulo_id' => $articuloId,
                'descripcion' => $linea['descripcion'] ?? null,
                'combinacion_id' => (int) ($linea['combinacion_id'] ?? 0) ?: null,
                'talle_id' => (int) ($linea['talle_id'] ?? 0) ?: null,
                'color_id' => (int) ($linea['color_id'] ?? 0) ?: null,
                'modulo_id' => (int) ($linea['modulo_id'] ?? 0) ?: null,
                'cantidad' => $cantidad,
            ];
        }

        return $out;
    }
}
