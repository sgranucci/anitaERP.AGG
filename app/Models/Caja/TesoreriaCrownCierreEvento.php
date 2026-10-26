<?php

namespace App\Models\Caja;

use App\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

class TesoreriaCrownCierreEvento extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'tesoreria_crown_cierre_evento';

    protected $fillable = [
        'cierre_id',
        'estado',
        'usuario_id',
        'detalle',
    ];

    public function cierre(): BelongsTo
    {
        return $this->belongsTo(TesoreriaCrownCierre::class, 'cierre_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}
