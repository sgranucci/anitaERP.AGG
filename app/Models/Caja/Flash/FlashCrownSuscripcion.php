<?php

namespace App\Models\Caja\Flash;

use App\Models\Seguridad\Usuario;
use App\Support\Caja\Flash\FlashCrownPerfilVistaSupport;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

class FlashCrownSuscripcion extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    public const PERIODICIDAD_MENSUAL = 'mensual';

    public const PERIODICIDAD_SEMANAL = 'semanal';

    public const PERIODICIDAD_DIARIA = 'diaria';

    public const PERIODO_MES_ANTERIOR = 'mes_anterior';

    public const PERIODO_MES_ACTUAL = 'mes_actual';

    public const PERIODO_FIJO = 'fijo';

    public const ESTADO_OK = 'ok';

    public const ESTADO_ERROR = 'error';

    public const ESTADO_OMITIDA = 'omitida';

    protected $table = 'flash_crown_suscripcion';

    protected $fillable = [
        'nombre',
        'activo',
        'periodicidad',
        'dia_mes',
        'dia_semana',
        'hora',
        'periodo_relativo',
        'mes_fijo',
        'perfil_vista',
        'solo_validados',
        'destinatarios',
        'mensaje',
        'ultima_ejecucion',
        'ultimo_estado',
        'ultimo_mensaje',
        'usuario_id',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'solo_validados' => 'boolean',
        'dia_mes' => 'integer',
        'dia_semana' => 'integer',
        'ultima_ejecucion' => 'datetime',
    ];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    /**
     * @return array<string, string>
     */
    public static function periodicidades(): array
    {
        return [
            self::PERIODICIDAD_DIARIA => 'Diaria (MTD del mes en curso)',
            self::PERIODICIDAD_SEMANAL => 'Semanal (un día de la semana)',
            self::PERIODICIDAD_MENSUAL => 'Mensual (un día fijo del mes)',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function periodosRelativos(): array
    {
        return [
            self::PERIODO_MES_ACTUAL => 'Mes en curso (hasta ayer)',
            self::PERIODO_MES_ANTERIOR => 'Mes anterior completo',
            self::PERIODO_FIJO => 'Mes fijo del envío',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function perfilesVista(): array
    {
        return FlashCrownPerfilVistaSupport::etiquetas();
    }

    /**
     * @return array<int, string>
     */
    public static function diasSemana(): array
    {
        return [
            1 => 'Lunes',
            2 => 'Martes',
            3 => 'Miércoles',
            4 => 'Jueves',
            5 => 'Viernes',
            6 => 'Sábado',
            7 => 'Domingo',
        ];
    }
}
