<?php

namespace App\Models\Configuracion;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ModuloAvisoEventoSuscripcion extends Model
{
    protected $table = 'modulo_aviso_evento_suscripcion';

    protected $fillable = [
        'evento', 'modulo_aviso_tipo_id', 'activo', 'condiciones',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'condiciones' => 'array',
    ];

    public function tipo(): BelongsTo
    {
        return $this->belongsTo(ModuloAvisoTipo::class, 'modulo_aviso_tipo_id');
    }
}
