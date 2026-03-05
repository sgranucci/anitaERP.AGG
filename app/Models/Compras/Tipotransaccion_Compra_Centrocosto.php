<?php

namespace App\Models\Compras;

use Illuminate\Database\Eloquent\Model;
use App\Models\Contable\Centrocosto;

class Tipotransaccion_Compra_Centrocosto extends Model
{
    protected $fillable = ['tipotransaccion_compra_id', 'centrocosto_id'];
    protected $table = 'tipotransaccion_compra_centrocosto';

	public function tipotransaccion_compras()
	{
    	return $this->belongsTo(Tipotransaccion_compra::class, 'tipotransaccion_compra_id', 'id');
	}

	public function centrocostos()
	{
    	return $this->belongsTo(Centrocosto::class, 'centrocosto_id');
	}

}
