<?php

namespace App\Models\Caja;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class ConceptoPerdida extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'concepto_perdida';

    protected $fillable = [
        'codigo',
        'nombre',
    ];

    protected $casts = [
        'codigo' => 'integer',
    ];

    public function perdidas()
    {
        return $this->hasMany(PerdidaPersonal::class, 'concepto_perdida_id');
    }
}
