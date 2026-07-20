<?php

namespace App\Models\Configuracion;

use Illuminate\Database\Eloquent\Model;

class Empresa extends Model
{
    protected $fillable = [
        'nombre',
        'domicilio',
        'pais_id',
        'provincia_id',
        'localidad_id',
        'codigopostal',
        'nroinscripcion',
        'codigo',
        'numeroiibb',
        'fechainicioactividad',
    ];

    protected $table = 'empresa';

    public function pais()
    {
        return $this->belongsTo(Pais::class, 'pais_id');
    }

    public function provincia()
    {
        return $this->belongsTo(Provincia::class, 'provincia_id');
    }

    public function localidad()
    {
        return $this->belongsTo(Localidad::class, 'localidad_id');
    }
}
