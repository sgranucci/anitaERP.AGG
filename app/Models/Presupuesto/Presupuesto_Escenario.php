<?php

namespace App\Models\Presupuesto;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use App\Traits\Presupuesto\Presupuesto_EscenarioTrait;
use App\Models\Seguridad\Usuario;

class Presupuesto_Escenario extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
	use Presupuesto_EscenarioTrait;

    protected $fillable = ['presupuesto_id', 'nombre', 'tipo', 'codigo', 'creousuario_id'];
    protected $table = 'presupuesto_escenario';

	public function presupuestos()
	{
    	return $this->belongsTo(Presupuesto::class, 'presupuesto_id', 'id');
	}

    public function creousuarios()
    {
        return $this->belongsTo(Usuario::class, 'creousuario_id');
    }

}
