<?php

namespace App\Models\Ordenventa;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use Illuminate\Database\Eloquent\SoftDeletes;

class Ordenventa_Cuota extends Model implements Auditable
{
	use \OwenIt\Auditing\Auditable;
    use SoftDeletes;

    protected $fillable = ['ordenventa_id', 'fechafactura', 'montofactura'];
    protected $table = 'ordenventa_cuota';

	public function ordenventas()
	{
    	return $this->belongsTo(Ordenventa::class, 'ordenventa_id', 'id');
	}

}
