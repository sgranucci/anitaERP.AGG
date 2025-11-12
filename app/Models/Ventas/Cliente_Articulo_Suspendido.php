<?php

namespace App\Models\Ventas;

use Illuminate\Database\Eloquent\Model;
use App\Models\Stock\Articulo;
use OwenIt\Auditing\Contracts\Auditable;

class Cliente_Articulo_Suspendido extends Model implements Auditable
{
	use \OwenIt\Auditing\Auditable;
    protected $fillable = ['cliente_id', 'articulo_id', 'creousuario_id'];
	
    protected $table = 'cliente_articulo_suspendido';

	public function clientes()
	{
    	return $this->belongsTo(Cliente::class, 'cliente_id', 'id');
	}

	public function articulos()
	{
    	return $this->belongsTo(Articulo::class, 'articulo_id', 'id');
	}	

    public function creousuarios()
    {
        return $this->belongsTo(Usuario::class, 'creousuario_id');
    }

}
