<?php

namespace App\Models\Sueldos;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReporteSueldosDefinibleVersion extends Model
{
    protected $table = 'reporte_sueldos_definible_version';

    protected $fillable = [
        'reporte_sueldos_definible_id',
        'version',
        'snapshot',
        'usuario_id',
        'comentario',
    ];

    protected $casts = [
        'reporte_sueldos_definible_id' => 'integer',
        'version' => 'integer',
        'snapshot' => 'array',
        'usuario_id' => 'integer',
    ];

    public function reporte(): BelongsTo
    {
        return $this->belongsTo(ReporteSueldosDefinible::class, 'reporte_sueldos_definible_id');
    }
}
