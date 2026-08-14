<?php

namespace App\Models\Caja\Bingo;

use App\Models\Configuracion\Empresa;
use App\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * SI pozo acumulado al cierre de un día con actividad bingo (p-vtabingo Evol. SI Pozo AC).
 */
class BingoPozoAcumulado extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    public const ORIGEN_CIERRE = 'cierre';

    public const ORIGEN_SEMILLA_ANITA = 'semilla_anita';

    public const ORIGEN_MANUAL = 'manual';

    protected $table = 'bingo_pozo_acumulado';

    protected $fillable = [
        'empresa_id',
        'fecha',
        'importe',
        'origen',
        'usuario_id',
    ];

    protected $casts = [
        'fecha' => 'date',
        'importe' => 'decimal:2',
    ];

    protected $attributes = [
        'origen' => self::ORIGEN_CIERRE,
    ];

    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}
