<?php

namespace App\Models\Caja;

use App\Models\Configuracion\Sala;
use App\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

class TesoreriaCrownAviso extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'tesoreria_crown_aviso';

    protected $fillable = [
        'sala_id',
        'fecha',
        'jornada_id',
        'asignacion_id',
        'comprobante_id',
        'cierre_id',
        'cajero_crown_id',
        'regla',
        'severidad',
        'clave',
        'titulo',
        'detalle',
        'importe',
        'estado',
        'comentario',
        'resuelto_usuario_id',
        'resuelto_at',
    ];

    protected $casts = [
        'fecha' => 'date',
        'importe' => 'decimal:2',
        'resuelto_at' => 'datetime',
    ];

    public function sala(): BelongsTo
    {
        return $this->belongsTo(Sala::class, 'sala_id');
    }

    public function jornada(): BelongsTo
    {
        return $this->belongsTo(TesoreriaCrownJornada::class, 'jornada_id');
    }

    public function asignacion(): BelongsTo
    {
        return $this->belongsTo(TesoreriaCrownAsignacion::class, 'asignacion_id');
    }

    public function comprobante(): BelongsTo
    {
        return $this->belongsTo(TesoreriaCrownComprobante::class, 'comprobante_id');
    }

    public function cajero(): BelongsTo
    {
        return $this->belongsTo(CajeroCrown::class, 'cajero_crown_id');
    }

    public function resueltoUsuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'resuelto_usuario_id');
    }
}
