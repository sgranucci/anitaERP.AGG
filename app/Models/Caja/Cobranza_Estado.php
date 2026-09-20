<?php

namespace App\Models\Caja;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use App\Traits\Caja\Cobranza_EstadoTrait;
use App\Models\Seguridad\Usuario;

class Cobranza_Estado extends Model implements Auditable
{
	use \OwenIt\Auditing\Auditable;
	use Cobranza_EstadoTrait;

    // Historial de estados: la fila ya ES la traza (usuario_id + fecha).
    protected $auditEvents = ['updated', 'deleted'];

    protected $fillable = ['cobranza_id', 'fecha', 'estado', 'usuario_id', 'observacion'];
    protected $table = 'cobranza_estado';

	public function cobranzas()
	{
    	return $this->belongsTo(cobranza::class, 'cobranza_id', 'id');
	}

	public function usuarios()
	{
        return $this->belongsTo(Usuario::class, 'usuario_id');
	}	
}
