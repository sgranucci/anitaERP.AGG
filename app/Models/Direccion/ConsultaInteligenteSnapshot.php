<?php

namespace App\Models\Direccion;

use App\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

class ConsultaInteligenteSnapshot extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'consulta_inteligente_snapshot';

    protected $fillable = [
        'usuario_id',
        'entidad',
        'titulo',
        'filtros_json',
        'columnas_json',
        'filas_json',
        'filas_count',
        'expires_at',
    ];

    protected $casts = [
        'filtros_json' => 'array',
        'columnas_json' => 'array',
        'filas_json' => 'array',
        'filas_count' => 'integer',
        'expires_at' => 'datetime',
        'usuario_id' => 'integer',
    ];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}
