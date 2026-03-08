<?php

namespace App\Models\Presupuesto;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class Partidagasto_Archivo extends Model implements Auditable
{
	use \OwenIt\Auditing\Auditable;

    protected $fillable = ['partidagasto_id', 'nombrearchivo'];
    protected $table = 'partidagasto_archivo';

	public function partidagastos()
	{
    	return $this->belongsTo(Partidagasto::class, 'partidagasto_id', 'id');
	}

}
