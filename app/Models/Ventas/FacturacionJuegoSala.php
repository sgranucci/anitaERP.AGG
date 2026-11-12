<?php

declare(strict_types=1);

namespace App\Models\Ventas;

use App\Models\Caja\Cuentacaja;
use App\Models\Configuracion\Sala;
use App\Models\Contable\Centrocosto;
use App\Models\Contable\Cuentacontable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

class FacturacionJuegoSala extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'facturacion_juego_sala';

    protected $fillable = [
        'sala_id',
        'sucursal_anita',
        'cuentacaja_id',
        'cuentacontable_mesa_id',
        'cuentacontable_slot_id',
        'cuentacontable_ruleta_el_id',
        'cuentacontable_del_rio_id',
        'cuentacontable_betsur_id',
        'cuentacontable_apuestas_id',
        'centrocosto_id',
        'activo',
    ];

    protected $casts = [
        'sucursal_anita' => 'integer',
        'activo' => 'boolean',
    ];

    public function sala(): BelongsTo
    {
        return $this->belongsTo(Sala::class, 'sala_id');
    }

    public function cuentacaja(): BelongsTo
    {
        return $this->belongsTo(Cuentacaja::class, 'cuentacaja_id');
    }

    public function cuentacontableMesa(): BelongsTo
    {
        return $this->belongsTo(Cuentacontable::class, 'cuentacontable_mesa_id');
    }

    public function cuentacontableSlot(): BelongsTo
    {
        return $this->belongsTo(Cuentacontable::class, 'cuentacontable_slot_id');
    }

    public function cuentacontableRuletaEl(): BelongsTo
    {
        return $this->belongsTo(Cuentacontable::class, 'cuentacontable_ruleta_el_id');
    }

    public function cuentacontableDelRio(): BelongsTo
    {
        return $this->belongsTo(Cuentacontable::class, 'cuentacontable_del_rio_id');
    }

    public function cuentacontableBetsur(): BelongsTo
    {
        return $this->belongsTo(Cuentacontable::class, 'cuentacontable_betsur_id');
    }

    public function cuentacontableApuestas(): BelongsTo
    {
        return $this->belongsTo(Cuentacontable::class, 'cuentacontable_apuestas_id');
    }

    public function centrocosto(): BelongsTo
    {
        return $this->belongsTo(Centrocosto::class, 'centrocosto_id');
    }
}
