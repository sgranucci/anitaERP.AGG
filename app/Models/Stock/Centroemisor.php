<?php

namespace App\Models\Stock;

use App\Models\Configuracion\Oficinacompra;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class Centroemisor extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'centroemisor';

    protected $fillable = [
        'codigo_interno_sifab',
        'codigo',
        'nombre',
        'calle',
        'numero',
        'piso',
        'departamento',
        'codigo_postal',
        'barrio',
        'oficinacompra_id',
        'habilitado',
    ];

    protected $casts = [
        'habilitado' => 'boolean',
    ];

    public function oficinacompras()
    {
        return $this->belongsTo(Oficinacompra::class, 'oficinacompra_id');
    }
}
