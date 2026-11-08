<?php

namespace App\Models\Caja;

use App\Models\Configuracion\Sala;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

class TesoreriaCrownResumenFinalDia extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    public const FUENTE_ANITA = 'anita';

    public const FUENTE_ERP = 'erp';

    protected $table = 'tesoreria_crown_resumen_final_dia';

    protected $fillable = [
        'sala_id',
        'fecha',
        'codigo',
        'monto',
        'fuente',
    ];

    protected $casts = [
        'fecha' => 'date',
        'codigo' => 'integer',
        'monto' => 'decimal:2',
    ];

    public function sala(): BelongsTo
    {
        return $this->belongsTo(Sala::class, 'sala_id');
    }
}
