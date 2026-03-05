<?php

namespace App\Models\Configuracion;

use Illuminate\Database\Eloquent\Model;
use App\Models\Configuracion\Moneda;

class Cotizacion_Moneda extends Model
{
    protected $fillable = ['cotizacion_id', 'moneda_id',
							'cotizacioncompra', 'cotizacionventa'];
    protected $table = 'cotizacion_moneda';

	public function cotizaciones()
	{
    	return $this->belongsTo(Cotizacion::class, 'cotizacion_id', 'id');
	}

	public function monedas()
	{
    	return $this->belongsTo(Moneda::class, 'moneda_id');
	}

}
