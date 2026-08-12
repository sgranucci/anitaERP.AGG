<?php

namespace App\Models\Contable;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReporteContableEliRegla extends Model
{
    protected $table = 'reporte_contable_eli_regla';

    protected $fillable = [
        'reporte_contable_id',
        'nombre',
        'codigo_desde',
        'codigo_hasta',
        'activo',
        'orden',
        'ambito',
        'empresa_a_id',
        'empresa_b_id',
    ];

    protected $casts = [
        'codigo_desde' => 'integer',
        'codigo_hasta' => 'integer',
        'activo' => 'boolean',
        'orden' => 'integer',
        'reporte_contable_id' => 'integer',
        'empresa_a_id' => 'integer',
        'empresa_b_id' => 'integer',
    ];

    public function reporte(): BelongsTo
    {
        return $this->belongsTo(ReporteContable::class, 'reporte_contable_id');
    }
}
