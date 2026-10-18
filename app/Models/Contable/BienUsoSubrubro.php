<?php

namespace App\Models\Contable;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Contracts\Auditable;

class BienUsoSubrubro extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'bien_uso_subrubro';

    protected $fillable = [
        'codigo',
        'nombre',
        'bien_uso_rubro_id',
    ];

    public function rubro(): BelongsTo
    {
        return $this->belongsTo(BienUsoRubro::class, 'bien_uso_rubro_id');
    }

    public function bienes(): HasMany
    {
        return $this->hasMany(BienUso::class, 'bien_uso_subrubro_id');
    }
}
