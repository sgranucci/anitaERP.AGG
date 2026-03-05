<?php

namespace App\Models\Caja;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use OwenIt\Auditing\Contracts\Auditable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Auth;

class Rendicionreceptivo_Caja_Movimiento extends Model implements Auditable
{
    use SoftDeletes;
	use \OwenIt\Auditing\Auditable;

    protected $fillable = [
							'rendicionreceptivo_id', 'caja_movimiento_id'
						];
    protected $table = 'rendicionreceptivo_caja_movimiento';

    public function rendicionreceptivos()
	{
    	return $this->belongsTo(Rendicionreceptivo::class, 'rendicionreceptivo_id', 'id');
	}

	public function caja_movimientos()
	{
    	return $this->belongsTo(Caja_Movimiento::class, 'caja_movimiento_id', 'id')->with('conceptogastos')
					->with('caja_movimiento_cuentacajas');
	}

}



