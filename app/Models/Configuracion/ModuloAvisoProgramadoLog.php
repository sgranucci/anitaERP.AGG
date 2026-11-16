<?php

namespace App\Models\Configuracion;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ModuloAvisoProgramadoLog extends Model
{
    protected $table = 'modulo_aviso_programado_log';

    protected $fillable = [
        'modulo_aviso_tipo_id', 'clave', 'comando', 'origen',
        'inicio_en', 'fin_en', 'exit_code', 'salida', 'error',
    ];

    protected $casts = [
        'inicio_en' => 'datetime',
        'fin_en' => 'datetime',
        'exit_code' => 'integer',
    ];

    public function tipo(): BelongsTo
    {
        return $this->belongsTo(ModuloAvisoTipo::class, 'modulo_aviso_tipo_id');
    }
}
