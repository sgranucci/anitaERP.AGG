<?php

namespace App\Models\Compras;

use App\Models\Configuracion\Empresa;
use App\Models\Configuracion\Moneda;
use App\Models\Contable\Centrocosto;
use App\Models\Contable\Cuentacontable;
use App\Models\Seguridad\Usuario;
use App\Models\Ventas\Transporte;
use Illuminate\Database\Eloquent\Model;

class Ordencompra extends Model
{
    /** Tratamiento de pago / anticipo (órdenes de compra). */
    public static $enumTratamientoCompra = [
        ['id' => '1', 'nombre' => 'NO ANTICIPADA'],
        ['id' => '2', 'nombre' => 'ANTICIPADA'],
    ];

    public static function mapearTratamientoDesdeRequisicion(string $valorReq): string
    {
        $t = strtoupper(trim($valorReq));

        // Exacto: "NO ANTICIPADA" también contiene "ANTICIP" y no debe mapear a anticipada.
        return ($t === 'ANTICIPADA' || $t === '2' || $t === 'S') ? 'ANTICIPADA' : 'NO ANTICIPADA';
    }

    /** Valor Anita penmp_es_anticipo (S/N) desde tratamiento ERP. */
    public static function anitaEsAnticipoDesdeTratamiento(?string $tratamiento): string
    {
        $t = strtoupper(trim((string) $tratamiento));

        return ($t === 'ANTICIPADA' || $t === '2' || $t === 'S') ? 'S' : 'N';
    }

    protected $table = 'ordencompra';

    protected $fillable = [
        'fecha', 'fechaentrega', 'empresa_id', 'numeroordencompra', 'requisicion_id', 'centrocosto_id',
        'comentario', 'detalle', 'lugarentrega', 'transporte_id', 'tratamiento', 'proveedor_id',
        'condicioncompra_id', 'condicionentrega_id', 'condicionpago_id', 'descuento', 'descuento_tipo', 'estadoordencompra', 'sector_legajocompra_id',
        'condiciones_contratacion', 'creousuario_id',
        'es_contrato', 'contrato_vigencia_desde', 'contrato_vigencia_hasta', 'contrato_monto_tope',
        'contrato_moneda_id', 'contrato_auto_renovable', 'contrato_dias_preaviso', 'contrato_dias_aviso',
        'contrato_responsable_id', 'contrato_requiere_recepcion', 'contrato_imputacion_contable',
        'contrato_cuentacontable_id',
    ];

    protected $casts = [
        'es_contrato' => 'boolean',
        'contrato_auto_renovable' => 'boolean',
        'contrato_requiere_recepcion' => 'boolean',
        'contrato_vigencia_desde' => 'date',
        'contrato_vigencia_hasta' => 'date',
        'contrato_monto_tope' => 'float',
        'contrato_dias_preaviso' => 'integer',
    ];

    public function empresas()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function centrocostos()
    {
        return $this->belongsTo(Centrocosto::class, 'centrocosto_id');
    }

    public function proveedores()
    {
        return $this->belongsTo(Proveedor::class, 'proveedor_id');
    }

    public function requisiciones()
    {
        return $this->belongsTo(Requisicion::class, 'requisicion_id');
    }

    public function usuarios()
    {
        return $this->belongsTo(Usuario::class, 'creousuario_id');
    }

    public function sector_legajocompras()
    {
        return $this->belongsTo(Sector_Legajocompra::class, 'sector_legajocompra_id');
    }

    public function ordencompra_articulos()
    {
        return $this->hasMany(Ordencompra_Articulo::class, 'ordencompra_id');
    }

    public function ordencompra_estados()
    {
        return $this->hasMany(Ordencompra_Estado::class, 'ordencompra_id');
    }

    public function ordencompra_comprobantes()
    {
        return $this->hasMany(Ordencompra_Comprobante::class, 'ordencompra_id')
            ->orderBy('fechavencimiento')
            ->orderBy('id');
    }

    public function ordencompra_historias()
    {
        return $this->hasMany(Ordencompra_Historia::class, 'ordencompra_id');
    }

    public function ordencompra_articulo_precio_historias()
    {
        return $this->hasMany(Ordencompra_Articulo_Precio_Historia::class, 'ordencompra_id');
    }

    public function ordencompra_archivos()
    {
        return $this->hasMany(Ordencompra_Archivo::class, 'ordencompra_id');
    }

    public function comprobante_proveedores()
    {
        return $this->hasMany(Comprobante_Proveedor::class, 'ordencompra_id');
    }

    public function condicioncompras()
    {
        return $this->belongsTo(Condicioncompra::class, 'condicioncompra_id');
    }

    public function condicionentregas()
    {
        return $this->belongsTo(Condicionentrega::class, 'condicionentrega_id');
    }

    public function condicionpagos()
    {
        return $this->belongsTo(Condicionpago::class, 'condicionpago_id');
    }

    public function transportes()
    {
        return $this->belongsTo(Transporte::class, 'transporte_id');
    }

    public function contrato_responsables()
    {
        return $this->belongsTo(Usuario::class, 'contrato_responsable_id');
    }

    public function contrato_monedas()
    {
        return $this->belongsTo(Moneda::class, 'contrato_moneda_id');
    }

    public function contrato_cuentacontables()
    {
        return $this->belongsTo(Cuentacontable::class, 'contrato_cuentacontable_id');
    }

    public function contrato_avisos()
    {
        return $this->hasMany(Ordencompra_Contrato_Aviso::class, 'ordencompra_id');
    }
}
