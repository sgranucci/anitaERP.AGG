<?php

namespace App\Models\Ventas;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use OwenIt\Auditing\Contracts\Auditable;

class Venta_Exportacion extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    protected $fillable = ['venta_id', 'incoterm_id', 'formapago_id', 'mercaderia', 'leyendaexportacion', 'peso_neto'];
    protected $table = 'venta_exportacion';

    protected $casts = [
        'peso_neto' => 'float',
    ];

    public function ventas()
	{
    	return $this->belongsTo(Venta::class, 'venta_id');
	}

    public function incoterms()
    {
        return $this->belongsTo(Incoterm::class, 'incoterm_id');
    }

    public function formapagos()
    {
        return $this->belongsTo(Formapago::class, 'formapago_id');
    }
}

