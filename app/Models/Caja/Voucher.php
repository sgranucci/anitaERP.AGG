<?php

namespace App\Models\Caja;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;
use App\Models\Receptivo\Servicioterrestre;
use App\Models\Compras\Proveedor;
use App\Models\Ventas\Formapago;
use Auth;

class Voucher extends Model implements Auditable
{
    use SoftDeletes;
	use \OwenIt\Auditing\Auditable;

    protected $fillable = ['talonariovoucher_id', 'numero', 'fecha',
							'pax', 'paxfree', 'incluido', 'opcional', 'servicioterrestre_id', 'proveedor_id',
                            'montovoucher', 'montoempresa', 'montoproveedor', 'observacion'];
    protected $table = 'voucher';

    public function voucher_guias()
	{
    	return $this->hasMany(Voucher_Guia::class, 'voucher_id');
	}

    public function voucher_reservas()
	{
    	return $this->hasMany(Voucher_Reserva::class, 'voucher_id');
	}

	public function voucher_formapagos()
	{
    	return $this->hasMany(Voucher_Formapago::class, 'voucher_id')->with('cuentacajas')->with('monedas');
	}

	public function talonariovouchers()
	{
    	return $this->belongsTo(Talonariovoucher::class, 'talonariovoucher_id');
	}

    public function servicioterrestres()
	{
    	return $this->belongsTo(Servicioterrestre::class, 'servicioterrestre_id');
	}

    public function proveedores()
	{
    	return $this->belongsTo(Proveedor::class, 'proveedor_id');
	}

}



