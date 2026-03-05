<?php

namespace App\Models\Compras;

use Illuminate\Database\Eloquent\Model;

class Proveedor_Archivo extends Model
{
    protected $fillable = ['proveedor_id', 'nombrearchivo'];
    protected $table = 'proveedor_archivo';

	public function proveedores()
	{
    	return $this->belongsTo(Proveedor::class, 'proveedor_id', 'id');
	}

}
