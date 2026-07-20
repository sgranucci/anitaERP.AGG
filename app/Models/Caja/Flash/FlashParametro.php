<?php

namespace App\Models\Caja\Flash;

use App\Models\Configuracion\Empresa;
use App\Models\Seguridad\Usuario;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class FlashParametro extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'flash_parametro';

    protected $fillable = [
        'empresa_id',
        'periodo',
        'budget_total',
        'budget_slot',
        'budget_rul',
        'budget_poker',
        'budget_bingo',
        'budget_f_b',
        'budget_pos',
        'budget_estac',
        'total_season',
        'total_sbingo',
        'total_sslot',
        'total_srul',
        'total_spoker',
        'total_s_estac',
        'creousuario_id',
        'actualizousuario_id',
    ];

    protected $casts = [
        'budget_total' => 'float',
        'budget_slot' => 'float',
        'budget_rul' => 'float',
        'budget_poker' => 'float',
        'budget_bingo' => 'float',
        'budget_f_b' => 'float',
        'budget_pos' => 'integer',
        'budget_estac' => 'float',
        'total_season' => 'float',
        'total_sbingo' => 'float',
        'total_sslot' => 'float',
        'total_srul' => 'float',
        'total_spoker' => 'float',
        'total_s_estac' => 'float',
    ];

    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function indices()
    {
        return $this->hasMany(FlashParametroIndice::class, 'flash_parametro_id')->orderBy('fecha');
    }

    public function creoUsuario()
    {
        return $this->belongsTo(Usuario::class, 'creousuario_id');
    }

    public function actualizoUsuario()
    {
        return $this->belongsTo(Usuario::class, 'actualizousuario_id');
    }

    public function getPeriodoLabelAttribute(): string
    {
        $periodo = (string) ($this->periodo ?? '');
        if (! preg_match('/^\d{6}$/', $periodo)) {
            return $periodo;
        }

        try {
            return Carbon::createFromFormat('Ym', $periodo)->locale('es')->isoFormat('MMMM YYYY');
        } catch (\Throwable) {
            return substr($periodo, 4, 2).'/'.substr($periodo, 0, 4);
        }
    }

    public function getPeriodoInputAttribute(): string
    {
        $periodo = (string) ($this->periodo ?? '');
        if (! preg_match('/^\d{6}$/', $periodo)) {
            return '';
        }

        return substr($periodo, 0, 4).'-'.substr($periodo, 4, 2);
    }

    public static function periodoDesdeInput(?string $valor): string
    {
        $valor = trim((string) $valor);
        if (preg_match('/^\d{6}$/', $valor)) {
            return $valor;
        }
        if (preg_match('/^(\d{4})-(\d{2})$/', $valor, $m)) {
            return $m[1].$m[2];
        }

        return '';
    }
}
