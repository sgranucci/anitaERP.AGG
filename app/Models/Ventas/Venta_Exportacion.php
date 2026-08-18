<?php

namespace App\Models\Ventas;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use OwenIt\Auditing\Contracts\Auditable;

class Venta_Exportacion extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    protected $fillable = ['venta_id', 'incoterm_id', 'formapago_id', 'mercaderia', 'leyendaexportacion'];
    protected $table = 'venta_exportacion';

    public function ventas()
	{
    	return $this->belongsTo(Venta::class, 'venta_id');
	}

}

