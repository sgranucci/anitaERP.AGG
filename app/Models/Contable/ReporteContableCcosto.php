<?php

namespace App\Models\Contable;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReporteContableCcosto extends Model
{
    protected $table = 'reporte_contable_ccosto';

    protected $fillable = [
        'reporte_contable_cuenta_id',
        'ccosto_desde',
        'ccosto_hasta',
        'centrocosto_id',
    ];

    protected $casts = [
        'ccosto_desde' => 'integer',
        'ccosto_hasta' => 'integer',
    ];

    public function cuenta(): BelongsTo
    {
        return $this->belongsTo(ReporteContableCuenta::class, 'reporte_contable_cuenta_id');
    }

    public function centrocosto(): BelongsTo
    {
        return $this->belongsTo(Centrocosto::class, 'centrocosto_id');
    }
}
