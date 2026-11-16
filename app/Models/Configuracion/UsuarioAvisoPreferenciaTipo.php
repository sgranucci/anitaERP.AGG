<?php

namespace App\Models\Configuracion;

use App\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UsuarioAvisoPreferenciaTipo extends Model
{
    protected $table = 'usuario_aviso_preferencia_tipo';

    protected $fillable = [
        'usuario_id', 'modulo_aviso_tipo_id', 'canal', 'habilitado',
    ];

    protected $casts = [
        'habilitado' => 'boolean',
        'usuario_id' => 'integer',
        'modulo_aviso_tipo_id' => 'integer',
    ];

    public function usuarios(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function tipo(): BelongsTo
    {
        return $this->belongsTo(ModuloAvisoTipo::class, 'modulo_aviso_tipo_id');
    }
}
