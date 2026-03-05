<?php

namespace App\Models\Caja;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use OwenIt\Auditing\Contracts\Auditable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Auth;

class Rendicionreceptivo_Formapago extends Model implements Auditable
{
    use SoftDeletes;
	use \OwenIt\Auditing\Auditable;

    protected $fillable = [
							'rendicionreceptivo_id', 'cuentacaja_id', 'moneda_id',  
							'monto', 'cotizacion'
						];
    protected $table = 'rendicionreceptivo_formapago';

    public function rendicionreceptivos()
	{
    	return $this->belongsTo(Rendicionreceptivo::class, 'rendicionreceptivo_id');
	}

	public function cuentacajas()
	{
    	return $this->belongsTo(Cuentacaja::class, 'cuentacaja_id');
	}

	public function monedas()
	{
    	return $this->belongsTo(Moneda::class, 'moneda_id');
	}

}



