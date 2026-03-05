<?php

namespace App\Models\Presupuesto;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class Capex_Partida_Monto extends Model implements Auditable
{
	use \OwenIt\Auditing\Auditable;

    protected $fillable = ['capex_partida_id', 'capex_id', 'periodo', 'monto', 'creousuario_id'];
    protected $table = 'capex_partida_monto';

	public function capex_partidas()
	{
    	return $this->belongsTo(Capex_Partida::class, 'capex_partida_id', 'id');
	}

	public function usuarios()
	{
        return $this->belongsTo(Usuario::class, 'creousuario_id');
	}

}
