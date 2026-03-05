<?php

namespace App\Models\Ordenventa;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use App\Models\Ventas\Venta;

class Ordenventa_Cuota extends Model implements Auditable
{
	use \OwenIt\Auditing\Auditable;

    protected $fillable = ['ordenventa_id', 'fechafactura', 'montofactura', 'venta_id'];
    protected $table = 'ordenventa_cuota';

	public function ordenventas()
	{
    	return $this->belongsTo(Ordenventa::class, 'ordenventa_id', 'id');
	}

    public function ventas()
	{
    	return $this->belongsTo(Venta::class, 'venta_id');
	}

}
