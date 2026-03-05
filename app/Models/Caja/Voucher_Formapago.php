<?php

namespace App\Models\Caja;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use OwenIt\Auditing\Contracts\Auditable;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Receptivo\Reserva;
use App\Models\Configuracion\Moneda;
use Auth;

class Voucher_Formapago extends Model implements Auditable
{
    use SoftDeletes;
	use \OwenIt\Auditing\Auditable;

    protected $fillable = [
							'voucher_id', 'cuentacaja_id', 'moneda_id',  
							'monto', 'cotizacion'
						];
    protected $table = 'voucher_formapago';

    public function vouchers()
	{
    	return $this->belongsTo(Voucher::class, 'voucher_id');
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



