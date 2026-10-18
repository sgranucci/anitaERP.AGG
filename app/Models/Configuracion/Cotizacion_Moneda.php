<?php

namespace App\Models\Configuracion;

use Illuminate\Database\Eloquent\Model;
use App\Models\Configuracion\Moneda;
use OwenIt\Auditing\Contracts\Auditable;

class Cotizacion_Moneda extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
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
