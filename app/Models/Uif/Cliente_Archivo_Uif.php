<?php

namespace App\Models\Uif;

use Illuminate\Database\Eloquent\Model;

class Cliente_Archivo_Uif extends Model
{
    protected $fillable = ['cliente_uif_id', 'nombrearchivo'];
    protected $table = 'cliente_archivo_uif';

	public function cliente_uifs()
	{
    	return $this->belongsTo(Cliente_Uif::class, 'cliente_uif_id', 'id');
	}

}
