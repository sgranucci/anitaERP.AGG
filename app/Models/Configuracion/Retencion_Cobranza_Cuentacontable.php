<?php

namespace App\Models\Configuracion;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use App\Models\Seguridad\Usuario;
use App\Models\Contable\Cuentacontable;

class Retencion_Cobranza_Cuentacontable extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    protected $fillable = ['retencion_cobranza_id', 'empresa_id', 'cuentacontable_id', 'creousuario_id'];
    protected $table = 'retencion_cobranza_cuentacontable';

	public function retencion_cobranzas()
	{
    	return $this->belongsTo(Retencion_Cobranza::class, 'retencion_cobranza_id', 'id');
	}

    public function empresas()
	{
    	return $this->belongsTo(Empresa::class, 'empresa_id', 'id');
	}

	public function cuentacontables()
	{
    	return $this->belongsTo(Cuentacontable::class, 'cuentacontable_id', 'id');
	}

    public function creousuarios()
    {
        return $this->belongsTo(Usuario::class, 'creousuario_id');
    }

}
