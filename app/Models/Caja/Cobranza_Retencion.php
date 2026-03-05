<?php

namespace App\Models\Caja;

use Illuminate\Database\Eloquent\Model;
use App\Models\Configuracion\Moneda;
use App\Models\Configuracion\Retencion_Cobranza;
use OwenIt\Auditing\Contracts\Auditable;

class Cobranza_Retencion extends Model implements Auditable
{
	use \OwenIt\Auditing\Auditable;

    protected $fillable = ['cobranza_id', 'retencion_cobranza_id', 'monto', 'moneda_id',
							'cotizacion', 'tasa', 'comprobante'];
    protected $table = 'cobranza_retencion';

	public function cobranzas()
	{
    	return $this->belongsTo(Cobranza::class, 'cobranza_id', 'id');
	}

	public function retencion_cobranzas()
	{
    	return $this->belongsTo(Retencion_Cobranza::class, 'retencion_cobranza_id');
	}

	public function monedas()
	{
    	return $this->belongsTo(Moneda::class, 'moneda_id');
	}

}
