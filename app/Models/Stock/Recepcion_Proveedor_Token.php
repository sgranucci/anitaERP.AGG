<?php

namespace App\Models\Stock;

use App\Models\Seguridad\Usuario;
use App\Support\Configuracion\OperacionPublicaTokenSupport;
use Illuminate\Database\Eloquent\Model;

class Recepcion_Proveedor_Token extends Model
{
    public const ACCION_VISUALIZAR = OperacionPublicaTokenSupport::ACCION_VISUALIZAR;

    protected $table = 'recepcion_proveedor_token';

    protected $fillable = [
        'recepcion_proveedor_id',
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

    public function recepcion_proveedor()
    {
        return $this->belongsTo(Recepcion_Proveedor::class, 'recepcion_proveedor_id');
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
