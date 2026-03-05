<?php

namespace App\Models\Presupuesto;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use OwenIt\Auditing\Contracts\Auditable;
use App\Traits\Presupuesto\PresupuestoTrait;
use App\Models\Seguridad\Usuario;
use Illuminate\Support\Str;

class Presupuesto extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    use PresupuestoTrait;

    protected $fillable = ['nombre', 'detalle', 'estado', 'codigo', 'anio', 'creousuario_id'];
    protected $table = 'presupuesto';

    public function presupuesto_escenarios()
	{
    	return $this->hasMany(Presupuesto_Escenario::class, 'presupuesto_id');
	}    

    public function creousuarios()
    {
        return $this->belongsTo(Usuario::class, 'creousuario_id');
    }    
}
