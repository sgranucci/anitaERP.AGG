<?php

namespace App\Models\Sueldos;

use App\Models\Configuracion\Empresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

class Sueldos_Recibo_Firma extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'sueldos_recibo_firma';

    protected $fillable = [
        'empresa_id',
        'nombre',
        'cargo',
        'imagen',
    ];

    protected $casts = [
        'empresa_id' => 'integer',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }
}
