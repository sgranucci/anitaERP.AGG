<?php

namespace App\Models\Configuracion;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\ApiAnita;

class Provincia extends Model
{
    protected $fillable = ['nombre', 'abreviatura', 'jurisdiccion', 'codigo', 'pais_id', 'codigoexterno'];
    protected $table = 'provincia';

    public function paises()
    {
        return $this->belongsTo(Pais::class, 'pais_id');
    }

    public function provincia_tasaiibbs()
	{
    	return $this->hasMany(Provincia_Tasaiibb::class, 'provincia_id')
                    ->with('condicioniibbs');
	}

    public function provincia_cuentacontableiibbs()
	{
    	return $this->hasMany(Provincia_Cuentacontableiibb::class, 'provincia_id');
	}    
}
