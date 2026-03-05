<?php

namespace App\Models\Presupuesto;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class Capex_Archivo extends Model implements Auditable
{
	use \OwenIt\Auditing\Auditable;

    protected $fillable = ['capex_id', 'nombrearchivo'];
    protected $table = 'capex_archivo';

	public function capexs()
	{
    	return $this->belongsTo(Capex::class, 'capex_id', 'id');
	}

}
