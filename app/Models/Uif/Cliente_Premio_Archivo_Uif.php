<?php

namespace App\Models\Uif;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class Cliente_Premio_Archivo_Uif extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    protected $fillable = ['cliente_premio_uif_id', 'nombrearchivo'];
    protected $table = 'cliente_premio_archivo_uif';

	public function cliente_premio_uifs()
	{
    	return $this->belongsTo(Cliente_Premio_Uif::class, 'cliente_premio_uif_id', 'id');
	}

}
