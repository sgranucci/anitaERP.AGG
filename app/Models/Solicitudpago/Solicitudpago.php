<?php

namespace App\Models\Solicitudpago;

use App\Models\Compras\Proveedor;
use App\Models\Configuracion\Empresa;
use App\Models\Configuracion\Moneda;
use App\Models\Contable\Centrocosto;
use App\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class Solicitudpago extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'solicitudpago';

    protected $fillable = [
        'codigo',
        'empresa_id',
        'fecha',
        'tratamiento',
        'proveedor_id',
        'concepto_solicitudpago_id',
        'formapagosol_id',
        'moneda_id',
        'beneficiario',
        'endoso',
        'fecha_entrega',
        'fecha_vencimiento',
        'monto',
        'observacion',
        'estado',
        'sector_solicitudpago_id',
        'centrocosto_id',
        'detalle',
        'solicitudpago_madre_id',
        'usuario_umod_id',
    ];

    protected $casts = [
        'codigo' => 'integer',
        'empresa_id' => 'integer',
        'fecha' => 'date',
        'fecha_entrega' => 'date',
        'fecha_vencimiento' => 'date',
        'monto' => 'decimal:2',
        'proveedor_id' => 'integer',
        'concepto_solicitudpago_id' => 'integer',
        'formapagosol_id' => 'integer',
        'moneda_id' => 'integer',
        'sector_solicitudpago_id' => 'integer',
        'centrocosto_id' => 'integer',
        'solicitudpago_madre_id' => 'integer',
        'usuario_umod_id' => 'integer',
    ];

    public function empresas()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function proveedores()
    {
        return $this->belongsTo(Proveedor::class, 'proveedor_id');
    }

    public function conceptos()
    {
        return $this->belongsTo(Concepto_Solicitudpago::class, 'concepto_solicitudpago_id');
    }

    public function formapagosol()
    {
        return $this->belongsTo(Formapagosol::class, 'formapagosol_id');
    }

    public function monedas()
    {
        return $this->belongsTo(Moneda::class, 'moneda_id');
    }

    public function sectores()
    {
        return $this->belongsTo(Sector_Solicitudpago::class, 'sector_solicitudpago_id');
    }

    public function centrocostos()
    {
        return $this->belongsTo(Centrocosto::class, 'centrocosto_id');
    }

    public function madre()
    {
        return $this->belongsTo(self::class, 'solicitudpago_madre_id');
    }

    public function hijas()
    {
        return $this->hasMany(self::class, 'solicitudpago_madre_id');
    }

    public function usuarios_umod()
    {
        return $this->belongsTo(Usuario::class, 'usuario_umod_id');
    }

    public function cuentas()
    {
        return $this->hasMany(Solicitudpago_Cuenta::class, 'solicitudpago_id')->orderBy('id');
    }

    public function cuotas()
    {
        return $this->hasMany(Solicitudpago_Cuota::class, 'solicitudpago_id')->orderBy('nro_cuota');
    }

    public function estados()
    {
        return $this->hasMany(Solicitudpago_Estado::class, 'solicitudpago_id')->orderBy('fecha')->orderBy('id');
    }

    public function archivos()
    {
        return $this->hasMany(Solicitudpago_Archivo::class, 'solicitudpago_id')->orderBy('nro_linea');
    }
}
