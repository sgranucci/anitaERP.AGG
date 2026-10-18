<?php

namespace App\Models\Ventas;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class Cliente_Archivo extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    protected $fillable = ['cliente_id', 'nombrearchivo'];
    protected $table = 'cliente_archivo';

	public function clientes()
	{
    	return $this->belongsTo(Cliente::class, 'cliente_id', 'id');
	}

}
