<?php

namespace App\Models\Contable;

use App\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReporteContableVariante extends Model
{
    protected $table = 'reporte_contable_variante';

    protected $fillable = [
        'usuario_id',
        'reporte_contable_id',
        'nombre',
        'filtros',
    ];

    protected $casts = [
        'usuario_id' => 'integer',
        'reporte_contable_id' => 'integer',
        'filtros' => 'array',
    ];

    public function reporte(): BelongsTo
    {
        return $this->belongsTo(ReporteContable::class, 'reporte_contable_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}
