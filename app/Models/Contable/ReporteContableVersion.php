<?php

namespace App\Models\Contable;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Seguridad\Usuario;

class ReporteContableVersion extends Model
{
    protected $table = 'reporte_contable_version';

    protected $fillable = [
        'reporte_contable_id',
        'version',
        'nombre',
        'snapshot',
        'usuario_id',
    ];

    protected $casts = [
        'version' => 'integer',
        'snapshot' => 'array',
        'usuario_id' => 'integer',
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
