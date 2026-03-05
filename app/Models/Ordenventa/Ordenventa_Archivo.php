<?php

namespace App\Models\Ordenventa;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class Ordenventa_Archivo extends Model implements Auditable
{
	use \OwenIt\Auditing\Auditable;

    protected $fillable = ['ordenventa_id', 'nombrearchivo'];
    protected $table = 'ordenventa_archivo';

	public function ordenventas()
	{
    	return $this->belongsTo(Ordenventa::class, 'ordenventa_id', 'id');
	}

}
