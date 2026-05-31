<?php

namespace App\Models\Ventas;

use App\Models\Configuracion\Empresa;
use App\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Model;

class CierreTotemJornadaGastronomia extends Model
{
    protected $table = 'cierre_totem_jornada_gastronomia';

    protected $fillable = [
        'jornada_gastronomia_id',
        'empresa_id',
        'waitry_order_id_anterior',
        'waitry_order_id_desde',
        'waitry_order_id_hasta',
        'cantidad_lineas',
        'total_monto',
        'cantidad_impagas_waitry',
        'cantidad_pagadas_waitry',
        'cantidad_facturadas_erp',
        'detalle_json',
        'informe_z_json',
        'detalle_truncado',
        'usuario_id',
    ];

    protected $casts = [
        'waitry_order_id_anterior' => 'integer',
        'waitry_order_id_desde' => 'integer',
        'waitry_order_id_hasta' => 'integer',
        'cantidad_lineas' => 'integer',
        'total_monto' => 'float',
        'cantidad_impagas_waitry' => 'integer',
        'cantidad_pagadas_waitry' => 'integer',
        'cantidad_facturadas_erp' => 'integer',
        'detalle_json' => 'array',
        'informe_z_json' => 'array',
        'detalle_truncado' => 'boolean',
    ];

    public function jornada()
    {
        return $this->belongsTo(JornadaGastronomia::class, 'jornada_gastronomia_id');
    }

    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function lineasDetalle(): array
    {
        $detalle = $this->detalle_json;
        if (! is_array($detalle)) {
            return [];
        }

        $lineas = $detalle['lineas'] ?? $detalle;

        return is_array($lineas) ? array_values($lineas) : [];
    }
}
