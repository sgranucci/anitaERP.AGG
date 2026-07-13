<?php

namespace App\Models\Stock;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class Subrubro extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'subrubro';

    protected $fillable = [
        'codigo_interno_sifab',
        'rubro_id',
        'codigo',
        'nombre',
        'habilitado',
    ];

    protected $casts = [
        'habilitado' => 'boolean',
    ];

    public function rubros()
    {
        return $this->belongsTo(Rubro::class, 'rubro_id');
    }
}
