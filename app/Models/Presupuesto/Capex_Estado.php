<?php

namespace App\Models\Presupuesto;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use App\Traits\Presupuesto\Capex_EstadoTrait;
use App\Models\Seguridad\Usuario;

class Capex_Estado extends Model implements Auditable
{
	use \OwenIt\Auditing\Auditable;
	use Capex_EstadoTrait;

    protected $fillable = ['capex_id', 'fecha', 'estado', 'observacion', 'usuario_id'];
    protected $table = 'capex_estado';

	public function capexs()
	{
    	return $this->belongsTo(Capex::class, 'capex_id', 'id');
	}

	public function usuarios()
	{
        return $this->belongsTo(Usuario::class, 'usuario_id');
	}

}
