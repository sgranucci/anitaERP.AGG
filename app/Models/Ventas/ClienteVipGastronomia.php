<?php

namespace App\Models\Ventas;

use App\Models\Configuracion\Empresa;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class ClienteVipGastronomia extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'cliente_vip_gastronomia';

    protected $fillable = [
        'empresa_id',
        'numeroid',
        'nrodocumento',
        'apellido',
        'nombre',
        'usualta_id',
        'fecha_alta',
        'hora_alta',
        'usumod_id',
        'fecha_mod',
        'hora_mod',
        'nickname',
        'localidad',
    ];

    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function nombreCompleto(): string
    {
        return trim(trim((string) ($this->apellido ?? '')).' '.trim((string) ($this->nombre ?? '')));
    }

    public function fechaInformixAFormato(?int $fecha): ?string
    {
        if ($fecha === null || $fecha <= 0) {
            return null;
        }

        $s = str_pad((string) $fecha, 8, '0', STR_PAD_LEFT);
        if (strlen($s) !== 8) {
            return null;
        }

        return substr($s, 6, 2).'/'.substr($s, 4, 2).'/'.substr($s, 0, 4);
    }

    public function getFechaAltaFormatoAttribute(): ?string
    {
        return $this->fechaInformixAFormato($this->fecha_alta !== null ? (int) $this->fecha_alta : null);
    }

    public function getFechaModFormatoAttribute(): ?string
    {
        return $this->fechaInformixAFormato($this->fecha_mod !== null ? (int) $this->fecha_mod : null);
    }
}
