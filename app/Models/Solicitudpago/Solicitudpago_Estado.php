<?php

namespace App\Models\Solicitudpago;

use App\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class Solicitudpago_Estado extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'solicitudpago_estado';

    protected $fillable = [
        'solicitudpago_id',
        'fecha',
        'hora',
        'usuario_id',
        'estado_anterior',
        'estado_actual',
        'leyenda',
    ];

    protected $casts = [
        'solicitudpago_id' => 'integer',
        'fecha' => 'date',
        'usuario_id' => 'integer',
    ];

    public function solicitudpagos()
    {
        return $this->belongsTo(Solicitudpago::class, 'solicitudpago_id');
    }

    public function usuarios()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}
