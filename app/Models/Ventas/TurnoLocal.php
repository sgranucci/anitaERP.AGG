<?php

namespace App\Models\Ventas;

use App\Models\Configuracion\Empresa;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class TurnoLocal extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'turno_local';

    protected $fillable = [
        'empresa_id',
        'nombre',
        'codigo',
        'hora_desde',
        'hora_hasta',
        'orden',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'orden' => 'integer',
    ];

    protected $attributes = [
        'activo' => true,
        'orden' => 0,
    ];

    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function turnosOperativos()
    {
        return $this->hasMany(TurnoOperativoLocal::class, 'turno_local_id');
    }

    public function cruzaMedianoche(): bool
    {
        if ($this->hora_desde === null || $this->hora_hasta === null) {
            return false;
        }

        return substr((string) $this->hora_hasta, 0, 8) < substr((string) $this->hora_desde, 0, 8);
    }

    public function etiquetaHorario(): string
    {
        if ($this->hora_desde === null && $this->hora_hasta === null) {
            return '—';
        }

        $desde = $this->hora_desde ? substr((string) $this->hora_desde, 0, 5) : '—';
        $hasta = $this->hora_hasta ? substr((string) $this->hora_hasta, 0, 5) : '—';
        $sufijo = $this->cruzaMedianoche() ? ' (día siguiente)' : '';

        return $desde.' – '.$hasta.$sufijo;
    }

    public function cubreHora(?string $horaHms = null): bool
    {
        if ($this->hora_desde === null || $this->hora_hasta === null) {
            return false;
        }

        $ahora = substr($horaHms ?? now()->format('H:i:s'), 0, 8);
        $desde = substr((string) $this->hora_desde, 0, 8);
        $hasta = substr((string) $this->hora_hasta, 0, 8);

        if ($this->cruzaMedianoche()) {
            return $ahora >= $desde || $ahora < $hasta;
        }

        return $ahora >= $desde && $ahora < $hasta;
    }
}
