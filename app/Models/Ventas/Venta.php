<?php

namespace App\Models\Ventas;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Configuracion\Moneda;
use App\Models\Configuracion\Actividad_Arca;
use App\Models\Ventas\Tipotransaccion;
use App\Models\Ventas\Puntoventa;
use App\Models\Ventas\Cliente;
use App\Models\Contable\Asiento;
use App\Models\Ordenventa\Ordenventa;
use App\Models\Caja\Caja_Movimiento;
use App\Models\Caja\Cobranza;
use OwenIt\Auditing\Contracts\Auditable;

class Venta extends Model implements Auditable
{
    use SoftDeletes;
    use \OwenIt\Auditing\Auditable;
    
	protected $casts = [
			'deleted_at' => 'datetime',
            'caea_informado_at' => 'datetime',
	];
    protected $fillable = [
            'fecha', 'fechajornada', 'tipotransaccion_id',
            'puntoventa_id', 'numerocomprobante', 'actividad_arca_id', 'cliente_id', 'condicionventa_id',
            'vendedor_id', 'transporte_id', 'total', 'moneda_id', 'cotizacion', 'estado',
            'usuario_id', 'leyenda', 'descuento', 'descuentointegrado', 'lugarentrega',
            'cliente_entrega_id', 'codigo', 'nombre', 'domicilio', 'localidad_id', 'provincia_id',
            'pais_id', 'codigopostal', 'email', 'telefono', 'nroinscripcion', 
            'condicioniva_id', 'cae', 'fechavencimientocae',
            'caea_informado_estado', 'caea_informado_at', 'caea_informado_codigo_error', 'caea_informado_mensaje',
            'puntoventaremito_id',
            'numeroremito', 'cantidadbulto', 'ordenventa_id', 'pedido_id', 'remito_id'
    ];

    protected $table = 'venta';

	public function venta_impuestos()
	{
    	return $this->hasMany(Venta_Impuesto::class, 'venta_id');
	}

	public function venta_emisiones()
	{
    	return $this->hasMany(Venta_Emision::class, 'venta_id')->with('articulos');
	}

	public function venta_exportaciones()
	{
    	return $this->hasMany(Venta_Exportacion::class, 'venta_id');
	}

    public function cliente_cuentacorrientes()
	{
    	return $this->hasMany(Cliente_Cuentacorriente::class, 'venta_id')->with('cliente_cuentacorriente_aplicaciones');
	}

    public function asientos()
	{
    	return $this->hasMany(Asiento::class, 'venta_id')->with('asiento_movimientos');
	}

    public function caja_movimientos()
    {
        return $this->hasMany(Caja_Movimiento::class, 'venta_id');
    }

    public function cobranzas()
    {
        return $this->hasManyThrough(
            Cobranza::class,
            Caja_Movimiento::class,
            'venta_id',
            'id',
            'id',
            'cobranza_id'
        );
    }

    /** Cobranza POS gastronomía (venta_id en tabla cobranza). */
    public function cobranzasDirectas()
    {
        return $this->hasMany(Cobranza::class, 'venta_id');
    }

    public function tickettarjetasGastronomia()
    {
        return $this->hasMany(TickettarjetaGastronomia::class, 'venta_id');
    }

    public function ticketcanjesGastronomia()
    {
        return $this->hasMany(TicketcanjeGastronomia::class, 'venta_id');
    }

    public function categoriafidelidadEntregasGastronomia()
    {
        return $this->hasMany(CategoriafidelidadEntregaGastronomia::class, 'venta_id');
    }

    public function gastronomiaEmision()
    {
        return $this->hasOne(VentaGastronomiaEmision::class, 'venta_id');
    }

    public function estacionamientoEmision()
    {
        return $this->hasOne(\App\Models\Caja\Estacionamiento\VentaEstacionamientoEmision::class, 'venta_id');
    }

    public function actividad_arcas()
	{
    	return $this->hasMany(Actividad_Arca::class, 'actividad_arca_id');
	}

    public function tipotransacciones()
    {
        return $this->hasOne(TipoTransaccion::class, 'id', 'tipotransaccion_id');
    }

    public function puntoventas()
    {
        return $this->hasOne(Puntoventa::class, 'id', 'puntoventa_id')->with('empresas');
    }

    public function puntoventaremito()
    {
        return $this->hasOne(Puntoventa::class, 'id', 'puntoventaremito_id');
    }

    public function clientes()
    {
        return $this->hasOne(Cliente::class, 'id', 'cliente_id')
                    ->with("condicionivas");
    }

    public function ordenventas()
    {
        return $this->hasOne(Ordenventa::class, 'id', 'ordenventa_id');
    }

    public function pedidos()
    {
        return $this->hasOne(Pedido::class, 'id', 'pedido_id');
    }

    public function remitos()
    {
        return $this->hasOne(Remito::class, 'id', 'remito_id');
    }

    public function transportes()
    {
        return $this->hasOne(Transporte::class, 'id', 'transporte_id');
    }

    public function monedas()
    {
        return $this->hasOne(Moneda::class, 'id', 'moneda_id');
    }

    public function condicionivas()
    {
        return $this->hasOne(\App\Models\Configuracion\Condicioniva::class, 'id', 'condicioniva_id');
    }

    // Borra en cadena por soft deletes
    protected static function boot()
	{
		parent::boot();

		static::deleting(function ($venta) {
			$venta->venta_impuestos()->delete();
			foreach ($venta->venta_emisiones as $emision) {
				$emision->delete();
			}
			$venta->venta_exportaciones()->delete();
            $venta->cliente_cuentacorrientes()->delete();
		});
	}    
}

