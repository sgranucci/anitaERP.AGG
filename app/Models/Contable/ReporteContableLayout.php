<?php

namespace App\Models\Contable;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReporteContableLayout extends Model
{
    protected $table = 'reporte_contable_layout';

    protected $fillable = [
        'reporte_contable_id',
        'codigo',
        'nombre',
        'es_default',
        'activo',
        'orden',
    ];

    protected $casts = [
        'es_default' => 'boolean',
        'activo' => 'boolean',
        'orden' => 'integer',
        'reporte_contable_id' => 'integer',
    ];

    public function reporte(): BelongsTo
    {
        return $this->belongsTo(ReporteContable::class, 'reporte_contable_id');
    }

    public function columnas(): HasMany
    {
        return $this->hasMany(ReporteContableLayoutColumna::class, 'reporte_contable_layout_id')
            ->orderBy('orden')
            ->orderBy('id');
    }

    public function scopeSistema($query)
    {
        return $query->whereNull('reporte_contable_id');
    }

    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }
}
