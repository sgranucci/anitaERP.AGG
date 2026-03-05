<?php

namespace App\Models\Stock;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Models\Configuracion\Pais;

class Lote extends Model
{
	use SoftDeletes;
    protected $table = "lote";
    protected $fillable = ['numerodespacho', 'fechaingreso', 'pais_id', 'usuario_id'];

	public function paises()
	{
		return $this->belongsTo(Pais::class, 'pais_id');
	}
}
