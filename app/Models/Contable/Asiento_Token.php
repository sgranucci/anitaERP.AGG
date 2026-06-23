<?php

namespace App\Models\Contable;

use App\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Model;

class Asiento_Token extends Model
{
    public const ACCION_APROBAR = 'aprobar';

    public const ACCION_RECHAZAR = 'rechazar';

    public const ACCION_VISUALIZAR = 'visualizar';

    protected $table = 'asiento_token';

    protected $fillable = [
        'asiento_id',
        'token',
        'accion',
        'usuario_destino_id',
        'usado_el',
        'expira_el',
    ];

    protected $casts = [
        'usado_el' => 'datetime',
        'expira_el' => 'datetime',
    ];

    public function asientos()
    {
        return $this->belongsTo(Asiento::class, 'asiento_id');
    }

    public function usuarios()
    {
        return $this->belongsTo(Usuario::class, 'usuario_destino_id');
    }

    public function estaActivo(): bool
    {
        if ($this->usado_el !== null) {
            return false;
        }
        if ($this->expira_el !== null && $this->expira_el->lt(now())) {
            return false;
        }

        return true;
    }
}
