<?php

namespace App\Models\Direccion;

use App\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

class ConsultaInteligenteTableroWidget extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'consulta_inteligente_tablero_widget';

    protected $fillable = [
        'usuario_id',
        'entidad',
        'titulo',
        'tipo',
        'query_json',
        'orden',
        'compartido',
        'compartido_empresa_id',
    ];

    protected $casts = [
        'query_json' => 'array',
        'usuario_id' => 'integer',
        'orden' => 'integer',
        'compartido' => 'boolean',
        'compartido_empresa_id' => 'integer',
    ];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}
