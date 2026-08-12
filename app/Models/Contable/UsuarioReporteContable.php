<?php

namespace App\Models\Contable;

use App\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UsuarioReporteContable extends Model
{
    protected $table = 'usuario_reporte_contable';

    protected $fillable = [
        'usuario_id',
        'reporte_contable_id',
    ];

    protected $casts = [
        'usuario_id' => 'integer',
        'reporte_contable_id' => 'integer',
    ];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function reporte(): BelongsTo
    {
        return $this->belongsTo(ReporteContable::class, 'reporte_contable_id');
    }
}
