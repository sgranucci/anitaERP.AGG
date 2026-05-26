<?php

namespace App\Models\Sala;

use App\Models\Configuracion\Empresa;
use Illuminate\Database\Eloquent\Model;

class ZonaSala extends Model
{
    protected $fillable = ['nombre', 'empresa_id'];

    protected $table = 'zona_sala';

    public function empresas()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id', 'id');
    }
}
