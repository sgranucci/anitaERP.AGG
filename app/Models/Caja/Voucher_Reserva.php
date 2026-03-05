<?php

namespace App\Models\Caja;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use OwenIt\Auditing\Contracts\Auditable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Auth;

class Voucher_Reserva extends Model implements Auditable
{
    use SoftDeletes;
	use \OwenIt\Auditing\Auditable;

    protected $fillable = [
							'voucher_id', 'reserva_id', 'pasajero_id',  
							'nombrepasajero', 'fechaarribo', 'fechapartida',
							'pax', 'limitepax', 'paxfree', 'limitefree', 'incluido',
							'opcional'
							];
    protected $table = 'voucher_reserva';

    public function vouchers()
	{
    	return $this->belongsTo(Voucher::class, 'voucher_id', 'id');
	}

}



