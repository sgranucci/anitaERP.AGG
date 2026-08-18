<?php

namespace App\Models\Sueldos;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReporteSueldosDefinibleConcepto extends Model
{
    protected $table = 'reporte_sueldos_definible_concepto';

    protected $fillable = [
        'columna_id',
        'concepto_codigo',
        'orden',
        'signo',
    ];

    protected $casts = [
        'columna_id' => 'integer',
        'concepto_codigo' => 'integer',
        'orden' => 'integer',
    ];

    public function columna(): BelongsTo
    {
        return $this->belongsTo(ReporteSueldosDefinibleColumna::class, 'columna_id');
    }

    public function concepto(): BelongsTo
    {
        return $this->belongsTo(Concepto_Sueldos::class, 'concepto_codigo', 'codigo');
    }
}
