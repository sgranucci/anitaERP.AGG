<?php

namespace App\Models\Compras;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use App\Models\Compras\RetencionIIBB_Condicion;
use App\Models\Configuracion\Provincia;
use App\Models\Contable\Cuentacontable;
use OwenIt\Auditing\Contracts\Auditable;

class RetencionIIBB extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    protected $fillable = [
						'nombre', 'provincia_id', 'cuentacontable_id'
						];
    protected $table = 'retencionIIBB';
	
	public function retencionIIBB_condiciones()
	{
    	return $this->hasMany(RetencionIIBB_Condicion::class, 'retencionIIBB_id', 'id')->with("condicionesIIBB");
	}

	public function provincias()
    {
        return $this->belongsTo(Provincia::class, 'provincia_id');
    }

	public function cuentascontables()
    {
        return $this->belongsTo(Cuentacontable::class, 'cuentacontable_id');
    }

}
