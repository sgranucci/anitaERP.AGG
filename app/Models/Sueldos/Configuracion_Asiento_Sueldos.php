<?php

namespace App\Models\Sueldos;

use App\Models\Configuracion\Empresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

class Configuracion_Asiento_Sueldos extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'configuracion_asiento_sueldos';

    protected $fillable = [
        'empresa_id',
        'modo',
    ];

    protected $casts = [
        'empresa_id' => 'integer',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }
}
