<?php

namespace App\Models\Caja;

use App\Models\Configuracion\Empresa;
use App\Models\Seguridad\Usuario;
use App\Models\Ventas\Maquinavending;
use App\Models\Ventas\MaquinavendingRendicion;
use App\Models\Ventas\Puntoventa;
use Illuminate\Database\Eloquent\Model;

class RendicionMaquinavendingCaja extends Model
{
    protected $table = 'rendicion_maquinavending_caja';

    protected $fillable = [
        'codigo',
        'maquinavending_rendicion_id',
        'empresa_id',
        'maquinavending_id',
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

    public function maquinavendingRendicion()
    {
        return $this->belongsTo(MaquinavendingRendicion::class, 'maquinavending_rendicion_id');
    }

    public function maquinavending()
    {
        return $this->belongsTo(Maquinavending::class, 'maquinavending_id');
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

    public function movimientos()
    {
        return $this->hasMany(RendicionMaquinavendingMovimientoCaja::class, 'rendicion_maquinavending_caja_id');
    }
}
