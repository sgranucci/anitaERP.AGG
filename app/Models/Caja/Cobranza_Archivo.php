<?php

namespace App\Models\Caja;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class Cobranza_Archivo extends Model implements Auditable
{
	use \OwenIt\Auditing\Auditable;

    protected $fillable = ['cobranza_id', 'nombrearchivo'];
    protected $table = 'cobranza_archivo';

	public function cobranzas()
	{
    	return $this->belongsTo(Cobranza::class, 'cobranza_id', 'id');
	}

}
