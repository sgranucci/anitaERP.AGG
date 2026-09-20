<?php

namespace App\Models\Stock;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Models\Configuracion\Pais;

class Lote extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = "lote";
    protected $fillable = ['numerodespacho', 'fechaingreso', 'pais_id', 'usuario_id'];

	public function paises()
	{
		return $this->belongsTo(Pais::class, 'pais_id');
	}
}
