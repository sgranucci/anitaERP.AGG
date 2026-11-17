<?php

namespace App\Models\Configuracion;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReglaNegocioVersion extends Model
{
    protected $table = 'regla_negocio_version';

    protected $fillable = [
        'regla_negocio_id', 'nro_version', 'snapshot', 'motivo', 'usuario_id',
    ];

    protected $casts = [
        'regla_negocio_id' => 'integer',
        'nro_version' => 'integer',
        'usuario_id' => 'integer',
        'snapshot' => 'array',
    ];

    public function regla(): BelongsTo
    {
        return $this->belongsTo(ReglaNegocio::class, 'regla_negocio_id');
    }
}
