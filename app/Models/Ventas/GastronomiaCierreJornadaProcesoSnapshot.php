<?php

namespace App\Models\Ventas;

use App\Models\Configuracion\Empresa;
use App\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Model;

class GastronomiaCierreJornadaProcesoSnapshot extends Model
{
    protected $table = 'gastronomia_cierre_jornada_proceso_snapshot';

    protected $fillable = [
        'jornada_gastronomia_id',
        'empresa_id',
        'fecha_jornada',
        'payload',
        'porcentaje',
        'usuario_id',
        'congelado_en',
    ];

    protected $casts = [
        'jornada_gastronomia_id' => 'integer',
        'empresa_id' => 'integer',
        'fecha_jornada' => 'date',
        'payload' => 'array',
        'porcentaje' => 'float',
        'usuario_id' => 'integer',
        'congelado_en' => 'datetime',
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
    public function lineas(): array
    {
        $payload = $this->payload;
        if (! is_array($payload)) {
            return [];
        }

        $lineas = $payload['lineas'] ?? [];

        return is_array($lineas) ? array_values($lineas) : [];
    }

    /**
     * @return array<string, mixed>
     */
    public function meta(): array
    {
        $payload = $this->payload;
        if (! is_array($payload)) {
            return [];
        }

        $meta = $payload['meta'] ?? [];

        return is_array($meta) ? $meta : [];
    }
}
