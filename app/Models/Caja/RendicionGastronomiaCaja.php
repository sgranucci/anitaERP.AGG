<?php

namespace App\Models\Caja;

use App\Models\Configuracion\Empresa;
use App\Models\Seguridad\Usuario;
use App\Models\Ventas\Puntoventa;
use App\Models\Ventas\TurnoOperativoGastronomia;
use Illuminate\Database\Eloquent\Model;

class RendicionGastronomiaCaja extends Model
{
    protected $table = 'rendicion_gastronomia_caja';

    protected $fillable = [
        'codigo',
        'empresa_id',
        'puntoventa_cae_id',
        'puntoventa_caea_id',
        'caja_id',
        'creousuario_id',
        'fecharendicion',
        'iniciodelfondo',
        'totalfactura',
        'totalcobrado',
        'totalinvitacion',
        'totalnotacredito',
        'totalredondeo',
        'totalredondeoinvitacion',
        'sobrantefaltante',
        'turno_operativo_gastronomia_id',
        'observacion',
    ];

    protected $casts = [
        'fecharendicion' => 'datetime',
        'iniciodelfondo' => 'float',
        'totalfactura' => 'float',
        'totalcobrado' => 'float',
        'totalinvitacion' => 'float',
        'totalnotacredito' => 'float',
        'totalredondeo' => 'float',
        'totalredondeoinvitacion' => 'float',
        'sobrantefaltante' => 'float',
    ];

    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function puntoventaCae()
    {
        return $this->belongsTo(Puntoventa::class, 'puntoventa_cae_id');
    }

    public function puntoventaCaea()
    {
        return $this->belongsTo(Puntoventa::class, 'puntoventa_caea_id');
    }

    public function caja()
    {
        return $this->belongsTo(Caja::class, 'caja_id');
    }

    public function creousuario()
    {
        return $this->belongsTo(Usuario::class, 'creousuario_id');
    }

    public function turnoOperativo()
    {
        return $this->belongsTo(TurnoOperativoGastronomia::class, 'turno_operativo_gastronomia_id');
    }

    public function movimientos()
    {
        return $this->hasMany(RendicionGastronomiaMovimientoCaja::class, 'rendicion_gastronomia_caja_id');
    }
}
