<?php

namespace App\Models\Compras;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Arr;
use App\Models\Configuracion\Empresa;
use App\Models\Configuracion\Provincia;
use App\Models\Configuracion\Localidad;
use App\Models\Configuracion\Pais;
use App\Models\Configuracion\Moneda;
use App\Models\Contable\Centrocosto;
use App\Models\Ventas\Cliente;
use App\Models\Ventas\Formapago;
use App\Models\Ventas\Venta;
use App\Traits\Ordenventa\OrdenventaTrait;
use DB;

class Requisicion extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
	use OrdenventaTrait;
    protected $fillable = ['fecha', 'empresa_id', 'numeroordenventa', 'centrocosto_id', 'comentario', 'detalle', 
							'monto', 'moneda_id', 'tratamiento', 'cliente_id', 'nombrecliente', 'fantasiacliente', 
							'domicilio', 'localidad_id', 
							'codigopostal', 'provincia_id', 'pais_id', 'nroinscripcion', 'telefono', 'email', 'formapago_id',
							'estado', 'creousuario_id'];
    protected $table = 'ordenventa';

    public function ordenventa_estados()
	{
    	return $this->hasMany(Ordenventa_Estado::class, 'ordenventa_id');
	}

    public function ordenventa_cuotas()
	{
    	return $this->hasMany(Ordenventa_Cuota::class, 'ordenventa_id');
	}

    public function ordenventa_conceptos()
	{
    	return $this->hasMany(Ordenventa_Concepto::class, 'ordenventa_id')->with('concepto_ordenventas');
	}

    public function ordenventa_archivos()
	{
    	return $this->hasMany(Ordenventa_Archivo::class, 'ordenventa_id');
	}

    public function ventas()
	{
    	return $this->hasMany(Venta::class, 'ordenventa_id')->with('cliente_cuentacorrientes');
	}

    public function empresas()
	{
    	return $this->belongsTo(Empresa::class, 'empresa_id');
	}

    public function centrocostos()
	{
    	return $this->belongsTo(Centrocosto::class, 'centrocosto_id');
	}

    public function monedas()
	{
    	return $this->belongsTo(Moneda::class, 'moneda_id');
	}

    public function clientes()
	{
    	return $this->belongsTo(Cliente::class, 'cliente_id');
	}

    public function localidades()
	{
    	return $this->belongsTo(Localidad::class, 'localidad_id');
	}

    public function provincias()
	{
    	return $this->belongsTo(Provincia::class, 'provincia_id');
	}

	public function paises()
	{
    	return $this->belongsTo(Pais::class, 'pais_id');
	}

    public function formapagos()
	{
    	return $this->belongsTo(Formapago::class, 'formapago_id');
	}

	public function usuarios()
	{
        return $this->belongsTo(Usuario::class, 'creousuario_id');
	}

	public function scopeConUltimoEstado(Builder $query) 
	{ 
		$subquery = Login::select('ordenventa_estado.estado') 
			->whereColumn('ordenventa_estado.ordenventa_id', 'ordenventa.id') 
			->latest() 
			->limit(1); 
		$query->addSelect(['ultimo_estado' => $subquery]);
		$query->with('ordenventa_estados'); 
	}
}
