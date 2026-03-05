<?php

namespace App\Models\Compras;

use Illuminate\Database\Eloquent\Model;

class Tipotransaccion_Compra_Concepto_Ivacompra extends Model
{
    protected $fillable = ['tipotransaccion_compra_id', 'concepto_ivacompra_id'];
    protected $table = 'tipotransaccion_compra_concepto_ivacompra';

	public function tipotransaccion_compras()
	{
    	return $this->belongsTo(Tipotransaccion_compra::class, 'tipotransaccion_compra_id', 'id');
	}

	public function concepto_ivacompras()
	{
    	return $this->belongsTo(Concepto_Ivacompra::class, 'concepto_ivacompra_id');
	}

}
