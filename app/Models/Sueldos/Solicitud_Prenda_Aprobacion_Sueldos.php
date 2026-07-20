<?php

namespace App\Models\Sueldos;

use App\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Model;

class Solicitud_Prenda_Aprobacion_Sueldos extends Model
{
    protected $table = 'solicitud_prenda_aprobacion_sueldos';

    public const ENVIO = 'E';

    public const APROBO = 'A';

    public const RECHAZO = 'R';

    public const ENTREGO = 'G';

    protected $fillable = [
        'solicitud_id',
        'nivel',
        'usuario_id',
        'accion',
        'observacion',
        'fecha',
    ];

    protected $casts = [
        'solicitud_id' => 'integer',
        'nivel' => 'integer',
        'usuario_id' => 'integer',
        'fecha' => 'datetime',
    ];

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}
