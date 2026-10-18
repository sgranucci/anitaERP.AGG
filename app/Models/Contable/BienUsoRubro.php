<?php

namespace App\Models\Contable;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Contracts\Auditable;

class BienUsoRubro extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'bien_uso_rubro';

    protected $fillable = [
        'codigo',
        'nombre',
    ];

    public function subrubros(): HasMany
    {
        return $this->hasMany(BienUsoSubrubro::class, 'bien_uso_rubro_id');
    }

    public function bienes(): HasMany
    {
        return $this->hasMany(BienUso::class, 'bien_uso_rubro_id');
    }
}
