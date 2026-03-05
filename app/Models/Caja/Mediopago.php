<?php

namespace App\Models\Caja;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use App\Models\Configuracion\Empresa;

class Mediopago extends Model
{
    protected $fillable = ['nombre', 'codigo', 'cuentacaja_id', 'empresa_id'];

    protected $table = 'mediopago';

    public function cuentacajas()
    {
        return $this->belongsTo(Cuentacaja::class, 'cuentacaja_id');
    }

    public function empresas()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

}

