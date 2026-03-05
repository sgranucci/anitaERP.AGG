<?php

namespace App\Models\Compras;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Arr;
use App\Traits\Compras\Tipotransaccion_CompraTrait;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tipotransaccion_Compra extends Model
{
    use SoftDeletes;
	use Tipotransaccion_CompraTrait;

    protected $fillable = ['nombre', 'operacion', 'abreviatura', 'codigoafip', 'signo', 'subdiario', 
                            'asientocontable', 'retieneiva', 'retieneganancia', 'retieneIIBB', 'estado'];
    protected $table = 'tipotransaccion_compra';

    public function tipotransaccion_compra_centrocostos()
	{
    	return $this->hasMany(Tipotransaccion_Compra_Centrocosto::class, 'tipotransaccion_compra_id')
                    ->with('centrocostos');
	}

    public function tipotransaccion_compra_concepto_ivacompras()
	{
    	return $this->hasMany(Tipotransaccion_Compra_Concepto_IvaCompra::class, 'tipotransaccion_compra_id')
                    ->with('concepto_ivacompras');
	}

    public function setSignoAttribute($signo)
    {
        switch(Tipotransaccion_CompraTrait::$enumSigno[$signo])
        {
        case 'Suma':
            $this->attributes['signo'] = 1;
            break;
        case 'Resta':
            $this->attributes['signo'] = -1;
            break;
        }
    }

    public function getSignoAttribute($signo)
    {
        $retSigno = 'S';
        switch($signo)
        {
        case 1:
            $retSigno = 'S';
            break;
        case -1:
            $retSigno = 'R';
            break;
        }
        return $retSigno;
    }

    public function getDescOperacionAttribute()
	{
	  	return Arr::get(Tipotransaccion_Compra::$enumOperacion, $this->operacion);
	}

    public function getDescSignoAttribute()
	{
	  	return Arr::get(Tipotransaccion_Compra::$enumSigno, $this->signo);
	}

    public function getDescSubdiarioAttribute()
	{
	  	return Arr::get(Tipotransaccion_Compra::$enumSubdiario, $this->subdiario);
	}

    public function getDescAsientocontableAttribute()
	{
	  	return Arr::get(Tipotransaccion_Compra::$enumAsientoContable, $this->asientocontable);
	}

    public function getDescEstadoAttribute()
	{
	  	return Arr::get(Tipotransaccion_Compra::$enumEstado, $this->estado);
	}

    public function getDescRetieneivaAttribute()
	{
	  	return Arr::get(Tipotransaccion_Compra::$enumRetiene, $this->retieneiva);
	}

    public function getDescRetienegananciaAttribute()
	{
	  	return Arr::get(Tipotransaccion_Compra::$enumRetiene, $this->retieneganancia);
	}

    public function getDescRetieneiibbAttribute()
	{
	  	return Arr::get(Tipotransaccion_Compra::$enumRetiene, $this->retieneiibb);
	}
}

