<?php

namespace App\Models\Sala;

use App\Models\Configuracion\Empresa;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class ZonaSala extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    protected $fillable = ['nombre', 'empresa_id'];

    protected $table = 'zona_sala';

    public function empresas()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id', 'id');
    }
}
