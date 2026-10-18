<?php

namespace App\Models\Ventas\GastronomiaCrown;

use App\Models\Configuracion\Empresa;
use App\Models\Configuracion\Sala;
use App\Models\Ventas\ConfiguracionPuntoventaGastronomia;
use App\Models\Ventas\MesaGastronomia;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Plano gráfico de mesas por empresa + sala. El layout vive en `layout_json`:
 * {version:1, width, height, grid:20, nodes:[{id, mesa_id, x, y, w, h, rotation, shape}]}
 */
class GastroPlanoMesa extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'gastro_plano_mesa';

    protected $fillable = [
        'empresa_id',
        'sala_id',
        'codigo',
        'nombre',
        'activo',
        'orden',
        'layout_json',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'orden' => 'integer',
        'layout_json' => 'array',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function sala(): BelongsTo
    {
        return $this->belongsTo(Sala::class, 'sala_id');
    }

    public function configuraciones(): BelongsToMany
    {
        return $this->belongsToMany(
            ConfiguracionPuntoventaGastronomia::class,
            'configuracion_puntoventa_gastronomia_plano',
            'gastro_plano_mesa_id',
            'configuracion_puntoventa_gastronomia_id'
        )->withPivot('orden');
    }

    /**
     * Mesas efectivamente ubicadas en el plano.
     *
     * @return list<int>
     */
    public function mesaIdsDelLayout(): array
    {
        $nodes = (array) (($this->layout_json['nodes'] ?? []) ?: []);

        return collect($nodes)
            ->pluck('mesa_id')
            ->filter(fn ($id) => (int) $id > 0)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    public function cantidadMesas(): int
    {
        return count($this->mesaIdsDelLayout());
    }

    /**
     * Mesas candidatas del plano (misma empresa y sala).
     *
     * @return \Illuminate\Database\Eloquent\Builder<MesaGastronomia>
     */
    public function mesasDisponiblesQuery()
    {
        return MesaGastronomia::query()
            ->where('empresa_id', $this->empresa_id)
            ->when($this->sala_id, fn ($q) => $q->where('sala_id', $this->sala_id))
            ->orderBy('codigo')
            ->orderBy('nombre');
    }

    public function etiqueta(): string
    {
        return trim(($this->codigo ? $this->codigo.' — ' : '').(string) $this->nombre);
    }
}
