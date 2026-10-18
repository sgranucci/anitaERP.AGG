<?php

namespace App\Models\Configuracion;

use App\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class AnitaNotificacion extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    protected $table = 'anita_notificacion';

    protected $fillable = [
        'usuario_id',
        'tipo',
        'titulo',
        'cuerpo',
        'url',
        'leida_at',
        'meta',
    ];

    protected $casts = [
        'leida_at' => 'datetime',
        'meta' => 'array',
    ];

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function estaLeida(): bool
    {
        return $this->leida_at !== null;
    }
}
