<?php

namespace App\Models\Stock;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Models\Stock\Articulo_Movimiento;
use App\Models\Stock\Mventa;
use App\Models\Stock\Tipotransaccion_Stock;
use App\Traits\Stock\MovimientoStockTrait;

class MovimientoStock extends Model
{
	use SoftDeletes;
	use MovimientoStockTrait;

    protected $table = "movimientostock";
    protected $fillable = ['fecha', 'fechajornada', 'tipotransaccion_stock_id', 'mventa_id', 'codigo', 'leyenda', 'estado', 'usuario_id', 'asiento_id', 'centrocosto_destino_id'];

	public function estadoEnum()
	{
		return MovimientoStockTrait::$enumEstado;
	}

	public function articulos_movimiento()
	{
		return $this->hasMany(Articulo_Movimiento::class, 'movimientostock_id')->with('articulo_movimiento_talles')->with('combinaciones');
	}

	public function tipotransaccion_stock()
	{
		return $this->hasOne(Tipotransaccion_Stock::class, 'id', 'tipotransaccion_stock_id');
	}

	public function mventas()
	{
		return $this->hasOne(Mventa::class, 'id', 'mventa_id');
	}

	public function asientos()
	{
		return $this->belongsTo(\App\Models\Contable\Asiento::class, 'asiento_id');
	}

	public function centrocostoDestino()
	{
		return $this->belongsTo(\App\Models\Contable\Centrocosto::class, 'centrocosto_destino_id');
	}
}
