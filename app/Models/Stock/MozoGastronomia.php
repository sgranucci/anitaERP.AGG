<?php

namespace App\Models\Stock;

use App\Models\Configuracion\Empresa;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class MozoGastronomia extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $fillable = ['nombre', 'codigo', 'empresa_id'];

    protected $table = 'mozo_gastronomia';

    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }
}
