<?php

namespace App\Models\Ventas;

use App\Models\Configuracion\Salida;
use App\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComprobanteImpresionLog extends Model
{
    protected $table = 'comprobante_impresion_log';

    protected $fillable = [
        'documento_tipo', 'documento_id', 'formulario', 'copia_codigo', 'copia_leyenda',
        'salida_id', 'destino_path', 'estado', 'mensaje', 'intentos', 'medio', 'modo',
        'usuario_id',
    ];

    public const ESTADO_OK = 'ok';

    public const ESTADO_ERROR = 'error';

    public const ESTADO_PENDIENTE = 'pendiente';

    public const MEDIO_IMPRESORA = 'IMPRESORA';

    public const MEDIO_ARCHIVO = 'ARCHIVO';

    public function salida(): BelongsTo
    {
        return $this->belongsTo(Salida::class, 'salida_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}
