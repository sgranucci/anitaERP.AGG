<?php

namespace App\Models\Caja;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use OwenIt\Auditing\Contracts\Auditable;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Configuracion\Moneda;
use Auth;

class Rendicionreceptivo_Comision extends Model implements Auditable
{
    use SoftDeletes;
	use \OwenIt\Auditing\Auditable;

    protected $fillable = [
							'rendicionreceptivo_id', 'voucher_id', 'cuentacaja_id',
							'moneda_id', 'monto', 'cotizacion'
						];
    protected $table = 'rendicionreceptivo_comision';

    public function rendicionreceptivos()
	{
    	return $this->belongsTo(Rendicionreceptivo::class, 'rendicionreceptivo_id');
	}

	function vouchers()
	{
    	return $this->belongsTo(Voucher::class, 'voucher_id', 'id')->with('voucher_guias');
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



