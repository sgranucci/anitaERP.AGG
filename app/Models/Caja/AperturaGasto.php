<?php

namespace App\Models\Caja;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class AperturaGasto extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    public const ESTADO_ACTIVO = 'activo';

    public const ESTADO_SUSPENDIDO = 'suspendido';

    /** @var array<int, array{valor: string, nombre: string}> */
    public static array $enumEstado = [
        ['valor' => self::ESTADO_ACTIVO, 'nombre' => 'Activo'],
        ['valor' => self::ESTADO_SUSPENDIDO, 'nombre' => 'Suspendido'],
    ];

    protected $table = 'apertura_gasto';

    protected $fillable = [
        'codigo',
        'nombre',
        'estado',
    ];

    protected $casts = [
        'codigo' => 'integer',
    ];

    protected $attributes = [
        'estado' => self::ESTADO_ACTIVO,
    ];

    public function empresas()
    {
        return $this->hasMany(AperturaGastoEmpresa::class, 'apertura_gasto_id');
    }

    public function getEstadoLabelAttribute(): string
    {
        foreach (self::$enumEstado as $row) {
            if ($row['valor'] === $this->estado) {
                return $row['nombre'];
            }
        }

        return (string) ($this->estado ?? '');
    }

    /**
     * Resumen de empresas configuradas (listado).
     */
    public function getEmpresasResumenAttribute(): string
    {
        if (! $this->relationLoaded('empresas')) {
            return '';
        }

        return $this->empresas
            ->sortBy(fn ($e) => $e->empresa->nombre ?? '')
            ->map(fn ($e) => $e->empresa->nombre ?? ('#'.$e->empresa_id))
            ->filter()
            ->implode(', ');
    }
}
