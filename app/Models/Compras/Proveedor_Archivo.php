<?php

namespace App\Models\Compras;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class Proveedor_Archivo extends Model implements Auditable
{
	use \OwenIt\Auditing\Auditable;
    protected $fillable = ['proveedor_id', 'nombrearchivo'];
    protected $table = 'proveedor_archivo';

	public function proveedores()
	{
    	return $this->belongsTo(Proveedor::class, 'proveedor_id', 'id');
	}

}
