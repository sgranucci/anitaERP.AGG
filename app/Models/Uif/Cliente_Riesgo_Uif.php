<?php

namespace App\Models\Uif;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use OwenIt\Auditing\Contracts\Auditable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Auth;

class Cliente_Riesgo_Uif extends Model implements Auditable
{
    use SoftDeletes;
	use \OwenIt\Auditing\Auditable;

    protected $fillable = [
							'cliente_uif_id', 'periodo', 'inusualidad_uif_id', 
							'riesgo', 'creousuario_id'
						];
    protected $table = 'cliente_riesgo_uif';

    public function cliente_uifs()
	{
    	return $this->belongsTo(Cliente_Uif::class, 'cliente_uif_id');
	}

	public function inusualidad_uifs()
	{
    	return $this->belongsTo(Inusualidad_Uif::class, 'inusualidad_uif_id');
	}

    public function usuarios()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

}



