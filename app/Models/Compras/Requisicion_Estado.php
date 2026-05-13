<?php

namespace App\Models\Compras;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use App\Models\Seguridad\Usuario;
use App\Traits\Compras\Requisicion_EstadoTrait;

class Requisicion_Estado extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    use Requisicion_EstadoTrait;

    protected $fillable = ['requisicion_id', 'fecha', 'estado', 'observacion', 'usuario_id'];
    protected $table = 'requisicion_estado';

    protected $casts = [
        'fecha' => 'datetime',
    ];

    public function requisiciones()
    {
        return $this->belongsTo(Requisicion::class, 'requisicion_id', 'id');
    }

    public function usuarios()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}
