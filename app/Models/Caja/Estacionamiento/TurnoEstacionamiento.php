<?php

namespace App\Models\Caja\Estacionamiento;

use App\Models\Configuracion\Empresa;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class TurnoEstacionamiento extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'turno_estacionamiento';

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

    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    /**
     * Turno nocturno u operación 24x7: la hora de fin cae al día calendario siguiente.
     */
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
}
