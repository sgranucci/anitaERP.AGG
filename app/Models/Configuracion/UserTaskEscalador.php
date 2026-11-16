<?php

namespace App\Models\Configuracion;

use App\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserTaskEscalador extends Model
{
    protected $table = 'user_task_escalador';

    protected $fillable = ['usuario_id', 'activo'];

    protected $casts = [
        'activo' => 'boolean',
        'usuario_id' => 'integer',
    ];

    public function usuarios(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}
