<?php

namespace App\Models\Ventas;

use App\Models\Configuracion\Empresa;
use App\Models\Configuracion\Sala;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class MozoGastronomia extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $fillable = ['nombre', 'codigo', 'clave', 'empresa_id', 'sala_id'];

    protected $hidden = ['clave'];

    protected $table = 'mozo_gastronomia';

    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function sala()
    {
        return $this->belongsTo(Sala::class, 'sala_id');
    }
}
