<?php

namespace App\Models\Stock;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class Rubro extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'rubro';

    protected $fillable = [
        'codigo_interno_sifab',
        'codigo',
        'nombre',
        'codigo_interno_cuenta_compra',
        'codigo_interno_cuenta_gasto',
        'codigo_interno_cuenta_variacion',
        'subrubro_obligatorio',
        'habilitado',
    ];

    protected $casts = [
        'subrubro_obligatorio' => 'boolean',
        'habilitado' => 'boolean',
    ];

    public function subrubros()
    {
        return $this->hasMany(Subrubro::class, 'rubro_id');
    }
}
