<?php

namespace App\Models\Presupuesto;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class Partidagasto_Monto extends Model implements Auditable
{
	use \OwenIt\Auditing\Auditable;

    // Detalle inmutable: el alta ya está en la fila (creousuario_id + created_at), así que
    // auditar 'created' la duplicaba. Era el 19% de la tabla audits.
    protected $auditEvents = ['updated', 'deleted'];

    protected $fillable = ['partidagasto_id', 'periodo', 'monto', 'creousuario_id'];
    protected $table = 'partidagasto_monto';

	public function partidagastos()
	{
    	return $this->belongsTo(Partidagasto::class, 'partidagasto_id', 'id');
	}

	public function creousuarios()
	{
        return $this->belongsTo(Usuario::class, 'creousuario_id');
	}

}
