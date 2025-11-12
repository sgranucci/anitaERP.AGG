<?php

namespace App\Models\Ventas;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\ApiAnita;
use App\Models\Seguridad\Usuario;
use App\Models\Contable\Cuentacontable;
use App\Models\Configuracion\Impuesto;
use App\Models\Configuracion\Localidad;
use App\Models\Configuracion\Provincia;
use App\Models\Configuracion\Pais;
use App\Models\Ventas\Zonavta;
use App\Models\Ventas\Subzonavta;
use App\Models\Ventas\Vendedor;
use App\Models\Ventas\Condicionventa;
use App\Models\Configuracion\Condicioniva;
use App\Models\Configuracion\Tipodocumento;
use App\Models\Stock\Listaprecio;
use App\Models\Ventas\Tiposuspensioncliente;
use App\Traits\Ventas\ClienteTrait;
use OwenIt\Auditing\Contracts\Auditable;

class Cliente extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
	use SoftDeletes;
	use ClienteTrait;

    protected $fillable = ['nombre','codigo','contacto','fantasia','email','telefono','urlweb','domicilio','localidad_id',
							'provincia_id','pais_id','zonavta_id','subzonavta_id','vendedor_id','numerodocumento','condicioniva_id',
							'retieneiva','nroiibb','condicioniibb','condicionventa_id','listaprecio_id','cuentacontable_id','vaweb',
							'estado','usuario_id','codigopostal','transporte_id','descuento','leyenda','tiposuspension_id',
                            'tipoalta','modofacturacion', 'cajaespecial',
                            // El Bierzo
                            'abasto_id', 'coeficiente_id', 'porcentajelogistica', 'emitecertificado', 'emitenotadecredito',
                            'coeficienteextra', 'agregabonificacion', 'desdefecha_exclusionpercepcioniva',
                            'hastafecha_exclusionpercepcioniva', 'distribuidor_id', 'descuentoventa_id', 'tipodocumento_id',
                            'lugarentrega'
                        ];

    protected $table = 'cliente';
	protected $dates = ['deleted_at'];

	public function cliente_entregas()
	{
    	return $this->hasMany(Cliente_Entrega::class, 'cliente_id')->with('localidades')->with('provincias')->with('transportes');
	}

	public function cliente_seguimientos()
	{
    	return $this->hasMany(Cliente_Seguimiento::class, 'cliente_id');
	}

    public function cliente_articulo_suspendidos()
	{
    	return $this->hasMany(Cliente_Articulo_Suspendido::class, 'cliente_id');
	}

	public function cliente_archivos()
	{
    	return $this->hasMany(Cliente_Archivo::class, 'cliente_id');
	}

    public function tipossuspensioncliente()
    {
        return $this->belongsTo(Tiposuspensioncliente::class, 'tiposuspension_id');
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

    public function zonavtas()
    {
        return $this->belongsTo(Zonavta::class, 'zonavta_id');
    }

    public function subzonavtas()
    {
        return $this->belongsTo(Subsonavta::class, 'subzonavta_id');
    }

    public function vendedores()
    {
        return $this->belongsTo(Vendedor::class, 'vendedor_id');
    }

    public function condicionivas()
    {
        return $this->belongsTo(Condicioniva::class, 'condicioniva_id');
    }

    public function condicionventas()
    {
        return $this->belongsTo(Condicionventa::class, 'condicionventa_id');
    }

    public function listaprecios()
    {
        return $this->belongsTo(Listaprecio::class, 'listaprecio_id');
    }

    public function cuentascontables()
    {
        return $this->belongsTo(Cuentacontable::class, 'cuentacontable_id');
    }

    public function transportes()
    {
        return $this->belongsTo(Transporte::class, 'transporte_id');
    }

    public function abastos()
    {
        return $this->belongsTo(Abasto::class, 'abasto_id');
    }

    public function coeficientes()
    {
        return $this->belongsTo(Coeficiente::class, 'coeficiente_id');
    }

    public function distribuidores()
    {
        return $this->belongsTo(Distribuidor::class, 'distribuidor_id');
    }

    public function descuentoventas()
    {
        return $this->belongsTo(Descuentoventa::class, 'descuentoventa_id');
    }

    public function tipodocumentos()
    {
        return $this->belongsTo(Tipodocumento::class, 'tipodocumento_id');
    }

    public function usuarios()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

	public function getDescProvinciaAttribute()
	{
		$data = Provincia::find($this->provincia_id);
		return ($data ? $data->nombre : '');
	}

	public function getDescLocalidadAttribute()
	{
		$data = Localidad::find($this->localidad_id);
		return ($data ? $data->nombre : '');
	}

	public function getCodigoTransporteAttribute()
	{
		$data = Transporte::find($this->transporte_id);
		return ($data ? $data->codigo : '0');
	}

	public function getDescripcionEstadoAttribute()
	{
		$desc = self::$enumEstado[$this->estado];

		return ($desc);
	}

    public function setCodigoTransporteAttribute($value)
	{
		$data = Transporte::find($this->transporte_id);
		if ($data)
			$this->attributes['codigotransporte'] = $data->codigo;
	}
}

