<?php

namespace App\Models\Sueldos;

use App\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReporteSueldosDefinibleVariante extends Model
{
    protected $table = 'reporte_sueldos_definible_variante';

    protected $fillable = [
        'reporte_sueldos_definible_id',
        'usuario_id',
        'nombre',
        'filtros',
        'columnas_visibles',
        'ordenamiento',
        'agrupaciones',
        'pivot_spec',
        'visualizacion',
        'compartida',
        'predeterminada',
    ];

    protected $casts = [
        'reporte_sueldos_definible_id' => 'integer',
        'usuario_id' => 'integer',
        'filtros' => 'array',
        'columnas_visibles' => 'array',
        'ordenamiento' => 'array',
        'agrupaciones' => 'array',
        'pivot_spec' => 'array',
        'visualizacion' => 'array',
        'compartida' => 'boolean',
        'predeterminada' => 'boolean',
    ];

    public function reporte(): BelongsTo
    {
        return $this->belongsTo(ReporteSueldosDefinible::class, 'reporte_sueldos_definible_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}
