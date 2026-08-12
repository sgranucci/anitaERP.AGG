<?php

namespace App\Models\Contable;

use App\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Nota al pie de una línea del informe. Las ediciones no sobrescriben: versionan.
 */
class ReporteContableNota extends Model
{
    protected $table = 'reporte_contable_nota';

    protected $fillable = [
        'reporte_contable_id',
        'reporte_contable_rubro_id',
        'codigo_linea',
        'texto',
        'periodo_desde',
        'periodo_hasta',
        'activo',
        'orden',
        'version',
        'nota_original_id',
        'usuario_id',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'orden' => 'integer',
        'version' => 'integer',
        'periodo_desde' => 'integer',
        'periodo_hasta' => 'integer',
    ];

    public function reporte(): BelongsTo
    {
        return $this->belongsTo(ReporteContable::class, 'reporte_contable_id');
    }

    public function rubro(): BelongsTo
    {
        return $this->belongsTo(ReporteContableRubro::class, 'reporte_contable_rubro_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    /** Id de la cadena de versiones a la que pertenece la nota. */
    public function cadenaId(): int
    {
        return (int) ($this->nota_original_id ?? $this->id);
    }

    public function vigenciaTexto(): string
    {
        $fmt = static fn (?int $p): string => $p === null || $p <= 0
            ? ''
            : substr((string) $p, 4, 2).'/'.substr((string) $p, 0, 4);

        $desde = $fmt($this->periodo_desde);
        $hasta = $fmt($this->periodo_hasta);

        if ($desde === '' && $hasta === '') {
            return 'Siempre';
        }
        if ($desde !== '' && $hasta !== '') {
            return $desde === $hasta ? $desde : $desde.' a '.$hasta;
        }

        return $desde !== '' ? 'Desde '.$desde : 'Hasta '.$hasta;
    }
}
