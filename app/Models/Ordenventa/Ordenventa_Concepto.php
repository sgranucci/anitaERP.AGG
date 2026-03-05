<?php

namespace App\Models\Ordenventa;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use App\Models\Ventas\Venta;

class Ordenventa_Concepto extends Model implements Auditable
{
	use \OwenIt\Auditing\Auditable;

    protected $fillable = ['ordenventa_id', 'concepto_ordenventa_id', 'cantidad', 'detalle', 'monto'];
    protected $table = 'ordenventa_concepto';

	public function ordenventas()
	{
    	return $this->belongsTo(Ordenventa::class, 'ordenventa_id', 'id');
	}

    public function concepto_ordenventas()
	{
    	return $this->belongsTo(Concepto_Ordenventa::class, 'concepto_ordenventa_id')->with('concepto_cuentacontable_ordenventas');
	}

}
