<?php

namespace App\Models\Configuracion;

use App\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Model;

class Padron_Iibb_Carga extends Model
{
    public const ESTADO_EN_PROCESO = 'en_proceso';

    public const ESTADO_OK = 'ok';

    public const ESTADO_ERROR = 'error';

    protected $table = 'padron_iibb_carga';

    protected $fillable = [
        'provincia_id', 'jurisdiccion', 'etiqueta', 'tipopadron', 'origen', 'estado', 'archivo',
        'desdefecha', 'hastafecha', 'filas_leidas', 'filas_insertadas', 'filas_actualizadas',
        'filas_omitidas', 'filas_borradas', 'errores', 'segundos', 'mensaje', 'usuario_id',
    ];

    protected $casts = [
        'desdefecha' => 'date',
        'hastafecha' => 'date',
    ];

    public function provincias()
    {
        return $this->belongsTo(Provincia::class, 'provincia_id');
    }

    public function usuarios()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function enProceso(): bool
    {
        return $this->estado === self::ESTADO_EN_PROCESO;
    }

    public function periodoEtiqueta(): string
    {
        if ($this->desdefecha === null) {
            return 'Padrón completo';
        }

        return $this->desdefecha->format('d/m/Y') . ' a ' . optional($this->hastafecha)->format('d/m/Y');
    }
}
