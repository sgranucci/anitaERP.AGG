<?php

namespace App\Models\Ventas;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use App\Models\Seguridad\Usuario;

class Cliente_Seguimiento extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    protected $fillable = ['cliente_id', 'fecha', 'observacion', 'leyenda', 'creousuario_id'];
	
    protected $table = 'cliente_seguimiento';

	public function clientes()
	{
    	return $this->belongsTo(Cliente::class, 'cliente_id', 'id');
	}

    public function creousuarios()
    {
        return $this->belongsTo(Usuario::class, 'creousuario_id');
    }

}
