<?php

namespace App\Models\Caja\Flash;

use App\Models\Configuracion\Empresa;
use App\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class FlashCaja extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'flash_caja';

    protected $fillable = [
        'empresa_id',
        'fecha',
        'att',
        'ayb',
        'slot_coin_in',
        'slot_d',
        'slot_r',
        'soft_count',
        'hard_count',
        'cant_slots',
        'rul_coin_in',
        'rul_d',
        'rul_r',
        'soft_rul',
        'hard_rul',
        'cant_rul',
        'cotizacion',
        'comentario',
        'bingo_cant_carton',
        'bingo_total_venta',
        'bingo_resultado',
        'pos_online',
        'win_ol_slot',
        'win_ol_rul',
        'estac',
        'vending',
        'cant_vehic',
        'show',
        'calculado_en',
        'creousuario_id',
        'actualizousuario_id',
    ];

    protected $casts = [
        'fecha' => 'date',
        'att' => 'integer',
        'cant_slots' => 'integer',
        'cant_rul' => 'integer',
        'bingo_cant_carton' => 'integer',
        'pos_online' => 'integer',
        'cant_vehic' => 'integer',
        'calculado_en' => 'datetime',
        'ayb' => 'float',
        'slot_coin_in' => 'float',
        'slot_d' => 'float',
        'slot_r' => 'float',
        'soft_count' => 'float',
        'hard_count' => 'float',
        'rul_coin_in' => 'float',
        'rul_d' => 'float',
        'rul_r' => 'float',
        'soft_rul' => 'float',
        'hard_rul' => 'float',
        'cotizacion' => 'float',
        'bingo_total_venta' => 'float',
        'bingo_resultado' => 'float',
        'win_ol_slot' => 'float',
        'win_ol_rul' => 'float',
        'estac' => 'float',
        'vending' => 'float',
        'show' => 'float',
    ];

    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function creoUsuario()
    {
        return $this->belongsTo(Usuario::class, 'creousuario_id');
    }

    public function actualizoUsuario()
    {
        return $this->belongsTo(Usuario::class, 'actualizousuario_id');
    }

    /** True si un usuario del ERP lo creó o editó (ABM), no el cron ni el import Anita. */
    public function fueCargadoPorUsuario(): bool
    {
        return (int) ($this->creousuario_id ?? 0) > 0
            || (int) ($this->actualizousuario_id ?? 0) > 0;
    }

    public function getTotalGamingAttribute(): float
    {
        return round(
            (float) $this->win_ol_slot
            + (float) $this->win_ol_rul
            + (float) $this->bingo_resultado,
            2
        );
    }

    public function getTotalRevenuesAttribute(): float
    {
        // Misma fórmula que l-flash.c Net Revenues (sin vending; EGA ≈ show).
        return round(
            $this->total_gaming
            + (float) $this->ayb
            + (float) $this->estac
            + (float) $this->show,
            2
        );
    }
}
