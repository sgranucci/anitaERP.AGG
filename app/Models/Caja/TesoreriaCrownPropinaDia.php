<?php

namespace App\Models\Caja;

use App\Models\Configuracion\Sala;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

class TesoreriaCrownPropinaDia extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    public const FUENTE_ANITA = 'anita';

    public const FUENTE_ERP = 'erp';

    protected $table = 'tesoreria_crown_propina_dia';

    protected $fillable = [
        'sala_id',
        'fecha',
        'monto',
        'fuente',
    ];

    protected $casts = [
        'fecha' => 'date',
        'monto' => 'decimal:2',
    ];

    public function sala(): BelongsTo
    {
        return $this->belongsTo(Sala::class, 'sala_id');
    }
}
