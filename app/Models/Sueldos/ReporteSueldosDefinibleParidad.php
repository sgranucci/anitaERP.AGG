<?php

namespace App\Models\Sueldos;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReporteSueldosDefinibleParidad extends Model
{
    protected $table = 'reporte_sueldos_definible_paridad';

    protected $fillable = [
        'ejecucion_id',
        'liquidacion_anita',
        'empresa_anita',
        'columna_nro',
        'columna_descripcion',
        'total_erp',
        'total_anita',
        'diferencia',
        'tolerancia',
        'coincide',
        'detalle',
    ];

    protected $casts = [
        'ejecucion_id' => 'integer',
        'liquidacion_anita' => 'integer',
        'empresa_anita' => 'integer',
        'columna_nro' => 'integer',
        'total_erp' => 'float',
        'total_anita' => 'float',
        'diferencia' => 'float',
        'tolerancia' => 'float',
        'coincide' => 'boolean',
        'detalle' => 'array',
    ];

    public function ejecucion(): BelongsTo
    {
        return $this->belongsTo(ReporteSueldosDefinibleEjecucion::class, 'ejecucion_id');
    }
}
