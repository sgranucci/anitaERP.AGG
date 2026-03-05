<?php

namespace App\Models\Ventas;

use Illuminate\Database\Eloquent\Model;

class Cliente_Archivo extends Model
{
    protected $fillable = ['cliente_id', 'nombrearchivo'];
    protected $table = 'cliente_archivo';

	public function clientes()
	{
    	return $this->belongsTo(Cliente::class, 'cliente_id', 'id');
	}

}
