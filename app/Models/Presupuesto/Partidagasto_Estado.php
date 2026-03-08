<?php

namespace App\Models\Presupuesto;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use App\Traits\Presupuesto\Partidagasto_EstadoTrait;
use App\Models\Seguridad\Usuario;

class Partidagasto_Estado extends Model implements Auditable
{
	use \OwenIt\Auditing\Auditable;
	use Partidagasto_EstadoTrait;

    protected $fillable = ['partidagasto_id', 'fecha', 'estado', 'observacion', 'usuario_id'];
    protected $table = 'partidagasto_estado';

	public function partidagastos()
	{
    	return $this->belongsTo(Partidagasto::class, 'partidagasto_id', 'id');
	}

	public function usuarios()
	{
        return $this->belongsTo(Usuario::class, 'usuario_id');
	}

}
