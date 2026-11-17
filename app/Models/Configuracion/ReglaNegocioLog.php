<?php

namespace App\Models\Configuracion;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReglaNegocioLog extends Model
{
    protected $table = 'regla_negocio_log';

    protected $fillable = [
        'regla_negocio_id', 'punto', 'entity_id', 'empresa_id',
        'ok', 'permitido', 'detalle', 'ejecutado_en',
    ];

    protected $casts = [
        'ok' => 'boolean',
        'permitido' => 'boolean',
        'entity_id' => 'integer',
        'empresa_id' => 'integer',
        'regla_negocio_id' => 'integer',
        'ejecutado_en' => 'datetime',
    ];

    public function regla(): BelongsTo
    {
        return $this->belongsTo(ReglaNegocio::class, 'regla_negocio_id');
    }
}
