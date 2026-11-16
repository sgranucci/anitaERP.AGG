<?php

namespace App\Models\Configuracion;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AvisoReglaLog extends Model
{
    protected $table = 'aviso_regla_log';

    protected $fillable = [
        'aviso_regla_id', 'evento', 'entity_id', 'empresa_id',
        'ok', 'detalle', 'ejecutado_en',
    ];

    protected $casts = [
        'ok' => 'boolean',
        'entity_id' => 'integer',
        'empresa_id' => 'integer',
        'aviso_regla_id' => 'integer',
        'ejecutado_en' => 'datetime',
    ];

    public function regla(): BelongsTo
    {
        return $this->belongsTo(AvisoRegla::class, 'aviso_regla_id');
    }
}
