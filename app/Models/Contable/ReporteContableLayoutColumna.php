<?php

namespace App\Models\Contable;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

class ReporteContableLayoutColumna extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    protected $table = 'reporte_contable_layout_columna';

    protected $fillable = [
        'reporte_contable_layout_id',
        'key',
        'label',
        'tipo',
        'orden',
        'meta',
    ];

    protected $casts = [
        'orden' => 'integer',
        'meta' => 'array',
    ];

    public function layout(): BelongsTo
    {
        return $this->belongsTo(ReporteContableLayout::class, 'reporte_contable_layout_id');
    }
}
