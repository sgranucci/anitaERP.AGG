<?php

namespace App\Models\Direccion;

use App\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

class ConsultaInteligenteNlFavorito extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'consulta_inteligente_nl_favorito';

    protected $fillable = [
        'usuario_id',
        'titulo',
        'pregunta',
        'orden',
    ];

    protected $casts = [
        'usuario_id' => 'integer',
        'orden' => 'integer',
    ];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}
