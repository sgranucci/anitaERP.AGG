<?php

namespace App\Models\Contable;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Contracts\Auditable;

class BienUsoIndice extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    public const TIPO_COEFICIENTE = 'C';

    public const TIPO_INDICES = 'I';

    public const TIPO_DIRECTO = 'D';

    protected $table = 'bien_uso_indice';

    protected $fillable = [
        'codigo',
        'nombre',
        'tipo_valor',
    ];

    public function coeficientes(): HasMany
    {
        return $this->hasMany(BienUsoIndiceCoeficiente::class, 'bien_uso_indice_id');
    }
}
