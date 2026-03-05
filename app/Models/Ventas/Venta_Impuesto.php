<?php

namespace App\Models\Ventas;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

class Venta_Impuesto extends Model implements Auditable
{
    use SoftDeletes;
    use \OwenIt\Auditing\Auditable;

    protected $fillable = ['venta_id', 'concepto', 'baseimponible', 'tasa', 'importe', 'provincia_id', 'impuesto_id'];
    protected $table = 'venta_impuesto';
	protected $casts = [
			'deleted_at' => 'datetime',
	];

    public function ventas()
	{
    	return $this->belongsTo(Venta::class, 'venta_id');
	}

}

