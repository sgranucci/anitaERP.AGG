<?php

namespace App\Models\Caja\Estacionamiento;

use App\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class CierreParcialTurnoEstacionamiento extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    public $timestamps = false;

    protected $table = 'cierre_parcial_turno_estacionamiento';

    protected $fillable = [
        'turno_operativo_estacionamiento_id',
        'numero_parcial',
        'identificador_pc',
        'total_facturacion_turno',
        'totales_json',
        'usuario_id',
        'created_at',
    ];

    protected $casts = [
        'totales_json' => 'array',
        'total_facturacion_turno' => 'float',
        'created_at' => 'datetime',
    ];

    public function turnoOperativo()
    {
        return $this->belongsTo(TurnoOperativoEstacionamiento::class, 'turno_operativo_estacionamiento_id');
    }

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}
