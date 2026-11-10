<?php

namespace App\Models\Caja\Flash;

use App\Models\Configuracion\Empresa;
use App\Models\Configuracion\Sala;
use App\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

class FlashCrown extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    public const ORIGEN_AUTO = 'auto';

    public const ORIGEN_MANUAL = 'manual';

    public const ORIGEN_MIXTO = 'mixto';

    protected $table = 'flash_crown';

    protected $fillable = [
        'sala_id',
        'empresa_id',
        'fecha',
        'att',
        'cashdrop',
        'soft_mesas',
        'cant_mesas',
        'caja_empleado',
        'wheel_r',
        'baccarat_r',
        'blackjack_r',
        'multi_r',
        'crap_r',
        'caribbean_r',
        'poker_r',
        'seven_r',
        'super_peek_r',
        'torneo_poker',
        'master_cup',
        'inscripcion',
        'premio',
        'slot_d',
        'soft_count',
        'hard_count',
        'cant_slots',
        'coin_in',
        'coin_out',
        'pago_manual',
        'slot_r',
        'cash_less',
        'ruleta_el_d',
        'ruleta_el_r',
        'soft_rul_el',
        'cant_rul_el',
        'coin_in_rul',
        'ol_att',
        'ol_drop',
        'ol_coin_in',
        'ol_cage_bal',
        'ol_win',
        'bs_att',
        'bs_coin_in',
        'bs_cage_bal',
        'bs_win',
        'proc_mesa',
        'proc_slot',
        'proc_ruleta_el',
        'venta_mesa_id',
        'venta_slot_id',
        'venta_ruleta_el_id',
        'asiento_mesa_id',
        'asiento_slot_id',
        'asiento_ruleta_el_id',
        'ayb',
        'hotel',
        'comentario',
        'overrides_json',
        'detalle_json',
        'origen',
        'calculado_en',
        'validado',
        'validado_en',
        'validado_usuario_id',
        'creousuario_id',
        'actualizousuario_id',
    ];

    protected $casts = [
        'fecha' => 'date',
        'att' => 'integer',
        'cant_mesas' => 'integer',
        'cant_slots' => 'integer',
        'cant_rul_el' => 'integer',
        'ol_att' => 'integer',
        'bs_att' => 'integer',
        'cashdrop' => 'float',
        'soft_mesas' => 'float',
        'caja_empleado' => 'float',
        'wheel_r' => 'float',
        'baccarat_r' => 'float',
        'blackjack_r' => 'float',
        'multi_r' => 'float',
        'crap_r' => 'float',
        'caribbean_r' => 'float',
        'poker_r' => 'float',
        'seven_r' => 'float',
        'super_peek_r' => 'float',
        'torneo_poker' => 'float',
        'master_cup' => 'float',
        'inscripcion' => 'float',
        'premio' => 'float',
        'slot_d' => 'float',
        'soft_count' => 'float',
        'hard_count' => 'float',
        'coin_in' => 'float',
        'coin_out' => 'float',
        'pago_manual' => 'float',
        'slot_r' => 'float',
        'cash_less' => 'float',
        'ruleta_el_d' => 'float',
        'ruleta_el_r' => 'float',
        'soft_rul_el' => 'float',
        'coin_in_rul' => 'float',
        'ol_drop' => 'float',
        'ol_coin_in' => 'float',
        'ol_cage_bal' => 'float',
        'ol_win' => 'float',
        'bs_coin_in' => 'float',
        'bs_cage_bal' => 'float',
        'bs_win' => 'float',
        'ayb' => 'float',
        'hotel' => 'float',
        'overrides_json' => 'array',
        'detalle_json' => 'array',
        'calculado_en' => 'datetime',
        'validado' => 'boolean',
        'validado_en' => 'datetime',
    ];

    public function sala(): BelongsTo
    {
        return $this->belongsTo(Sala::class, 'sala_id');
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function validadoUsuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'validado_usuario_id');
    }

    public function creoUsuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'creousuario_id');
    }

    public function actualizoUsuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'actualizousuario_id');
    }

    public function estaValidado(): bool
    {
        return (bool) ($this->validado ?? false);
    }

    /** Win de mesas de juego (sin torneos). */
    public function getWinMesasAttribute(): float
    {
        return round(
            (float) $this->wheel_r
            + (float) $this->baccarat_r
            + (float) $this->blackjack_r
            + (float) $this->multi_r
            + (float) $this->crap_r
            + (float) $this->caribbean_r
            + (float) $this->poker_r
            + (float) $this->seven_r
            + (float) $this->super_peek_r,
            2
        );
    }

    /** Gaming = mesas + slots − cashless + ruleta el. + Del Río online + Betsur (l-consolida). */
    public function getGamingAttribute(): float
    {
        return round(
            $this->win_mesas
            + (float) $this->slot_r
            - (float) $this->cash_less
            + (float) $this->ruleta_el_r
            + (float) $this->ol_win
            + (float) $this->bs_win,
            2
        );
    }

    /** Combined = gaming + F&B + hotel. */
    public function getCombinedAttribute(): float
    {
        return round($this->gaming + (float) $this->ayb + (float) $this->hotel, 2);
    }

    public function getWinTorneosAttribute(): float
    {
        return round((float) $this->torneo_poker + (float) $this->master_cup, 2);
    }

    /**
     * @return list<string>
     */
    public static function camposNumericos(): array
    {
        return [
            'att', 'cashdrop', 'soft_mesas', 'cant_mesas', 'caja_empleado',
            'wheel_r', 'baccarat_r', 'blackjack_r', 'multi_r', 'crap_r',
            'caribbean_r', 'poker_r', 'seven_r', 'super_peek_r',
            'torneo_poker', 'master_cup', 'inscripcion', 'premio',
            'slot_d', 'soft_count', 'hard_count', 'cant_slots',
            'coin_in', 'coin_out', 'pago_manual', 'slot_r', 'cash_less',
            'ruleta_el_d', 'ruleta_el_r', 'soft_rul_el', 'cant_rul_el', 'coin_in_rul',
            'ol_att', 'ol_drop', 'ol_coin_in', 'ol_cage_bal', 'ol_win',
            'bs_att', 'bs_coin_in', 'bs_cage_bal', 'bs_win',
            'ayb', 'hotel',
        ];
    }
}
