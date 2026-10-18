<?php

namespace App\Models\Receptivo;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use App\Models\Ventas\Formapago;
use App\Traits\Receptivo\Comision_ServicioterrestreTrait;
use OwenIt\Auditing\Contracts\Auditable;

class Comision_Servicioterrestre extends Model implements Auditable
{
	use Comision_ServicioterrestreTrait;

    use \OwenIt\Auditing\Auditable;
    protected $fillable = [
						 'servicioterrestre_id', 'tipocomision', 'formapago_id', 'porcentajecomision'
						];
    protected $table = 'comision_servicioterrestre';

	public function servicioterrestres()
    {
        return $this->belongsTo(Servicioterrestre::class, 'servicioterrestre_id');
    }

	public function formapagos()
    {
        return $this->belongsTo(Formapago::class, 'formapago_id');
    }

}
