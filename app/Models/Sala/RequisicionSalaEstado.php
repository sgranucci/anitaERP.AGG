<?php

namespace App\Models\Sala;

use App\Models\Seguridad\Usuario;
use App\Traits\Sala\RequisicionSalaEstadoTrait;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class RequisicionSalaEstado extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    use RequisicionSalaEstadoTrait;

    protected $table = 'requisicion_sala_estado';

    protected $fillable = [
        'requisicion_sala_id', 'fecha', 'estado', 'usuario_id', 'observacion',
    ];

    public function requisicion_salas()
    {
        return $this->belongsTo(RequisicionSala::class, 'requisicion_sala_id');
    }

    public function usuarios()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}
