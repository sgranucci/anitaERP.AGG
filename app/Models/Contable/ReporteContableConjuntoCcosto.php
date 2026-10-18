<?php

namespace App\Models\Contable;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

class ReporteContableConjuntoCcosto extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    protected $table = 'reporte_contable_conjunto_ccosto';

    protected $fillable = [
        'reporte_contable_conjunto_cuenta_id',
        'ccosto_desde',
        'ccosto_hasta',
        'centrocosto_id',
    ];

    protected $casts = [
        'ccosto_desde' => 'integer',
        'ccosto_hasta' => 'integer',
        'centrocosto_id' => 'integer',
    ];

    public function cuenta(): BelongsTo
    {
        return $this->belongsTo(ReporteContableConjuntoCuenta::class, 'reporte_contable_conjunto_cuenta_id');
    }
}
