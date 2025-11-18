<?php

namespace App\Models\Configuracion;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Arr;

class Padron_Iibb extends Model
{
    protected $fillable = ['id', 'nombre', 'cuit'];
    protected $table = 'padron_iibb';

    public function padron_iibb_tasas()
	{
    	return $this->hasMany(Padron_Iibb_Tasa::class, 'padron_iibb_id')
                    ->with('provincias');
	}
}

