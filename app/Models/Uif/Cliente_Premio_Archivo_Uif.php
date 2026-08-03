<?php

namespace App\Models\Uif;

use Illuminate\Database\Eloquent\Model;

class Cliente_Premio_Archivo_Uif extends Model
{
    protected $fillable = ['cliente_premio_uif_id', 'nombrearchivo'];
    protected $table = 'cliente_premio_archivo_uif';

	public function cliente_premio_uifs()
	{
    	return $this->belongsTo(Cliente_Premio_Uif::class, 'cliente_premio_uif_id', 'id');
	}

}
