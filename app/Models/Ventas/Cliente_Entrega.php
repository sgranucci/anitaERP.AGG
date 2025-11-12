<?php

namespace App\Models\Ventas;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use App\Models\Ventas\Cliente;
use App\Models\Ventas\Zonavta;
use App\Models\Ventas\Subzonavta;
use App\Models\Ventas\Vendedor;
use App\Models\Ventas\Transporte;
use App\Models\Configuracion\Localidad;
use App\Models\Configuracion\Provincia;
use App\Models\Configuracion\Pais;

class Cliente_Entrega extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    protected $fillable = ['cliente_id', 'nombre', 'codigo', 'domicilio', 'localidad_id', 'provincia_id', 
		'pais_id', 'codigopostal', 'zonavta_id', 'subzonavta_id', 'vendedor_id', 'transporte_id'];
    protected $table = 'cliente_entrega';

	public function clientes()
	{
    	return $this->belongsTo(Cliente::class, 'cliente_id', 'id');
	}

    public function provincias()
    {
        return $this->belongsTo(Provincia::class, 'provincia_id');
    }

    public function localidades()
    {
        return $this->belongsTo(Localidad::class, 'localidad_id');
    }

    public function transportes()
    {
        return $this->belongsTo(Transporte::class, 'transporte_id');
    }


    public function paises()
    {
        return $this->belongsTo(Pais::class, 'paises_id');
    }

    public function zonavtas()
    {
        return $this->belongsTo(Zonavta::class, 'zonavta_id');
    }

    public function subzonavtas()
    {
        return $this->belongsTo(Subzonavta::class, 'subzonavta_id');
    }

    public function vendedores()
    {
        return $this->belongsTo(Vendedor::class, 'vendedor_id');
    }

	public function getDescProvinciasAttribute()
	{
		$data = Provincia::find($this->provincia_id);
		return ($data ? $data->nombre : '');
	}

	public function getDescLocalidadesAttribute()
	{
		$data = Localidad::find($this->localidad_id);
		return ($data ? $data->nombre : '');
	}

}
