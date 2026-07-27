<?php

namespace App\Models\Seguridad;

use Illuminate\Database\Eloquent\Model;

class UsuarioAuditoriaFavorito extends Model
{
    public $timestamps = false;

    protected $table = 'usuario_auditoria_favorito';

    protected $fillable = [
        'usuario_id',
        'auditable_type',
        'etiqueta',
        'orden',
    ];

    protected $casts = [
        'usuario_id' => 'integer',
        'orden' => 'integer',
    ];
}
