<?php

namespace App\Models\Seguridad;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use App\Models\Configuracion\Empresa;

class Usuario_Empresa extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    protected $fillable = ['usuario_id', 'empresa_id'];
    protected $table = 'usuario_empresa';
    public $incrementing = true;

	public function usuarios()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

	public function empresas()
	{
    	return $this->belongsTo(Empresa::class, 'empresa_id', 'id');
	}

}
