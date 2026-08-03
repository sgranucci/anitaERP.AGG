<?php

namespace App\Models\Caja;

use App\Models\Configuracion\Empresa;
use App\Models\Contable\Asiento;
use App\Models\Seguridad\Usuario;
use App\Models\Ventas\Maquinavending;
use App\Models\Ventas\MaquinavendingRendicion;
use App\Models\Ventas\Puntoventa;
use App\Models\Ventas\Venta;
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
        'asiento_id',
        'venta_id',
        'cierre_contable_en',
        'cierre_contable_usuario_id',
        'cierre_contable_legacy',
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
        'cierre_contable_en' => 'datetime',
        'cierre_contable_legacy' => 'boolean',
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

    public function asiento()
    {
        return $this->belongsTo(Asiento::class, 'asiento_id');
    }

    public function venta()
    {
        return $this->belongsTo(Venta::class, 'venta_id');
    }

    public function cierreContableUsuario()
    {
        return $this->belongsTo(Usuario::class, 'cierre_contable_usuario_id');
    }

    public function tieneCierreContable(): bool
    {
        if ((bool) ($this->cierre_contable_legacy ?? false)) {
            return true;
        }

        return (int) ($this->asiento_id ?? 0) > 0 && $this->cierre_contable_en !== null;
    }

    public function esCierreContableLegacy(): bool
    {
        return (bool) ($this->cierre_contable_legacy ?? false);
    }

    public function puedeCerrarContablemente(): bool
    {
        return ! $this->tieneCierreContable();
    }
}
