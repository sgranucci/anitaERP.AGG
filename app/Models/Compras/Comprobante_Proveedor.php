<?php

namespace App\Models\Compras;

use App\Models\Caja\Caja_Movimiento;
use App\Models\Configuracion\Condicioniva;
use App\Models\Configuracion\Empresa;
use App\Models\Configuracion\Moneda;
use App\Models\Contable\Asiento;
use App\Models\Seguridad\Usuario;
use App\Models\Stock\Recepcion_Proveedor;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class Comprobante_Proveedor extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'comprobante_proveedor';

    protected $fillable = [
        'empresa_id', 'proveedor_id', 'tipotransaccion_compra_id', 'ordencompra_id',
        'ordencompra_comprobante_id', 'precarga_comprobante_proveedor_id', 'condicionpago_id',
        'conceptogasto_id',
        'letra', 'sucursal', 'numerocomprobante', 'fechacomprobante', 'fechaiva', 'fechavencimiento',
        'fecharecepcion', 'subtotal', 'total', 'moneda_id', 'cotizacion', 'numerocae', 'tipo_autorizacion',
        'fechavencimientocae', 'es_fce',         'leyenda', 'modo_carga', 'origen_entrada', 'tipo_tesoreria', 'estado', 'asiento_id',
        'caja_movimiento_id', 'proveedor_nombre_eventual', 'proveedor_documento_eventual',
        'identificacion_proveedor_cuit',
        'proveedor_condicioniva_id_eventual',
        'pararevisar', 'anita_nro_interno', 'anita_sync_estado', 'anita_sync_error',
        'anita_sync_at', 'creousuario_id',
    ];

    protected $casts = [
        'fechacomprobante' => 'date',
        'fechaiva' => 'date',
        'fechavencimiento' => 'date',
        'fecharecepcion' => 'datetime',
        'fechavencimientocae' => 'date',
        'es_fce' => 'boolean',
        'pararevisar' => 'boolean',
        'anita_sync_at' => 'datetime',
    ];

    public function empresas()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function proveedores()
    {
        return $this->belongsTo(Proveedor::class, 'proveedor_id');
    }

    public function caja_movimientos()
    {
        return $this->belongsTo(Caja_Movimiento::class, 'caja_movimiento_id');
    }

    public function proveedor_condicioniva_eventual()
    {
        return $this->belongsTo(Condicioniva::class, 'proveedor_condicioniva_id_eventual');
    }

    public function tipotransaccion_compras()
    {
        return $this->belongsTo(Tipotransaccion_Compra::class, 'tipotransaccion_compra_id');
    }

    public function ordencompras()
    {
        return $this->belongsTo(Ordencompra::class, 'ordencompra_id');
    }

    public function ordencompra_comprobantes()
    {
        return $this->belongsTo(Ordencompra_Comprobante::class, 'ordencompra_comprobante_id');
    }

    public function precarga_comprobante_proveedores()
    {
        return $this->belongsTo(Precarga_Comprobante_Proveedor::class, 'precarga_comprobante_proveedor_id');
    }

    public function conceptogastos()
    {
        return $this->belongsTo(\App\Models\Caja\Conceptogasto::class, 'conceptogasto_id');
    }

    public function condicionpagos()
    {
        return $this->belongsTo(Condicionpago::class, 'condicionpago_id');
    }

    public function monedas()
    {
        return $this->belongsTo(Moneda::class, 'moneda_id');
    }

    public function asientos()
    {
        return $this->belongsTo(Asiento::class, 'asiento_id');
    }

    public function creousuarios()
    {
        return $this->belongsTo(Usuario::class, 'creousuario_id');
    }

    public function comprobante_proveedor_conceptos()
    {
        return $this->hasMany(Comprobante_Proveedor_Concepto::class, 'comprobante_proveedor_id')
            ->orderBy('orden');
    }

    public function comprobante_proveedor_articulos()
    {
        return $this->hasMany(Comprobante_Proveedor_Articulo::class, 'comprobante_proveedor_id')
            ->orderBy('orden');
    }

    public function comprobante_proveedor_recepciones()
    {
        return $this->hasMany(Comprobante_Proveedor_Recepcion::class, 'comprobante_proveedor_id')
            ->orderBy('orden');
    }

    public function recepcion_proveedores()
    {
        return $this->belongsToMany(
            Recepcion_Proveedor::class,
            'comprobante_proveedor_recepcion',
            'comprobante_proveedor_id',
            'recepcion_proveedor_id'
        )->withPivot('orden')->withTimestamps()->orderByPivot('orden');
    }

    public function comprobante_proveedor_cuotas()
    {
        return $this->hasMany(Comprobante_Proveedor_Cuota::class, 'comprobante_proveedor_id')
            ->orderBy('numero_cuota');
    }

    public function comprobante_proveedor_estados()
    {
        return $this->hasMany(Comprobante_Proveedor_Estado::class, 'comprobante_proveedor_id');
    }

    public function comprobante_proveedor_archivos()
    {
        return $this->hasMany(Comprobante_Proveedor_Archivo::class, 'comprobante_proveedor_id');
    }
}
