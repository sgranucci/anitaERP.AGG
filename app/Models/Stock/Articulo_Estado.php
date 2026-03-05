<?php

namespace App\Models\Stock;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use App\Traits\Stock\Articulo_EstadoTrait;
use App\Models\Seguridad\Usuario;

class Articulo_Estado extends Model implements Auditable
{
	use \OwenIt\Auditing\Auditable;
	use Articulo_EstadoTrait;

    protected $fillable = ['articulo_id', 'fecha', 'estado', 'usuario_id', 'observacion'];
    protected $table = 'articulo_estado';

	public function articulos()
	{
    	return $this->belongsTo(Articulo::class, 'articulo_id', 'id');
	}

	public function usuarios()
	{
        return $this->belongsTo(Usuario::class, 'usuario_id');
	}	
}
