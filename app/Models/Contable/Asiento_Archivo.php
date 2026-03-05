<?php

namespace App\Models\Contable;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class Asiento_Archivo extends Model implements Auditable
{
	use \OwenIt\Auditing\Auditable;
    protected $fillable = ['asiento_id', 'nombrearchivo'];
    protected $table = 'asiento_archivo';

	public function asientos()
	{
    	return $this->belongsTo(Asiento::class, 'asiento_id', 'id');
	}

}
