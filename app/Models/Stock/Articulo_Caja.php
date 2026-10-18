<?php

namespace App\Models\Stock;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\ApiAnita;
use OwenIt\Auditing\Contracts\Auditable;

class Articulo_Caja extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    protected $fillable = ['articulo_id', 'caja_id', 'desdenro', 'hastanro'];
    protected $table = 'articulo_caja';
	public $timestamps = false;

    public function cajas()
    {
        return $this->belongsTo(Caja::class, 'caja_id', 'id')->with('articulos');
    }

}
