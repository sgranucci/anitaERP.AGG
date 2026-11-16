<?php

namespace App\Models\Configuracion;

use App\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UsuarioAvisoPreferencia extends Model
{
    protected $table = 'usuario_aviso_preferencia';

    protected $fillable = [
        'usuario_id', 'canal', 'habilitado',
    ];

    protected $casts = [
        'habilitado' => 'boolean',
        'usuario_id' => 'integer',
    ];

    public function usuarios(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}
