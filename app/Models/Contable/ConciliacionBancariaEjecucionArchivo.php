<?php

namespace App\Models\Contable;

use App\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

class ConciliacionBancariaEjecucionArchivo extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'conciliacion_bancaria_ejecucion_archivo';

    protected $fillable = [
        'ejecucion_id',
        'nombrearchivo',
        'nombre_original',
        'etapa',
        'usuario_id',
    ];

    public function ejecucion(): BelongsTo
    {
        return $this->belongsTo(ConciliacionBancariaEjecucion::class, 'ejecucion_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}
