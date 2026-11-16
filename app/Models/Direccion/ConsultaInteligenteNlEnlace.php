<?php

namespace App\Models\Direccion;

use App\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

class ConsultaInteligenteNlEnlace extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'consulta_inteligente_nl_enlace';

    protected $fillable = [
        'token',
        'usuario_id',
        'titulo',
        'pregunta',
        'entidad',
        'query_json',
    ];

    protected $casts = [
        'usuario_id' => 'integer',
        'query_json' => 'array',
    ];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}
