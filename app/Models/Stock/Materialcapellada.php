<?php

namespace App\Models\Stock;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\ApiAnita;
use App\Models\Stock\Articulo;

class Materialcapellada extends Model
{
    protected $fillable = ['nombre', 'articulo_id'];
    protected $table = 'materialcapellada';

    public function articulos()
    {
        return $this->belongsTo(Articulo::class, 'articulo_id');
    }

	public function getSkuAttribute()
	{
		$data = Articulo::find($this->articulo_id);
		return ($data ? $data->sku : '');
	}

	public function getDescArticuloAttribute()
	{
		$data = Articulo::find($this->articulo_id);
		return ($data ? $data->descripcion : '');
	}
}

