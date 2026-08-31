<?php

namespace App\Models\Sueldos;

use App\Models\Contable\Asiento;
use App\Models\Contable\Centrocosto;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

class Liquidacion_Asiento_Sueldos extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'liquidacion_asiento_sueldos';

    protected $fillable = [
        'liquidacion_id',
        'asiento_id',
        'centrocosto_id',
    ];

    protected $casts = [
        'liquidacion_id' => 'integer',
        'asiento_id' => 'integer',
        'centrocosto_id' => 'integer',
    ];

    public function liquidacion(): BelongsTo
    {
        return $this->belongsTo(Liquidacion_Sueldos::class, 'liquidacion_id');
    }

    public function asiento(): BelongsTo
    {
        return $this->belongsTo(Asiento::class, 'asiento_id');
    }

    public function centrocosto(): BelongsTo
    {
        return $this->belongsTo(Centrocosto::class, 'centrocosto_id');
    }
}
