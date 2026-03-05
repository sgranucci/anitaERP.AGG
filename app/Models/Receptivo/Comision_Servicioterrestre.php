<?php

namespace App\Models\Receptivo;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use App\Models\Ventas\Formapago;
use App\Traits\Receptivo\Comision_ServicioterrestreTrait;

class Comision_Servicioterrestre extends Model
{
	use Comision_ServicioterrestreTrait;

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
