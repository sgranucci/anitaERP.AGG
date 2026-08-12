<?php

declare(strict_types=1);

namespace App\Models\Contable;

use App\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReporteContablePublicacion extends Model
{
    protected $table = 'reporte_contable_publicacion';

    protected $fillable = [
        'reporte_contable_id',
        'nombre',
        'hash',
        'filtros',
        'resultado',
        'periodo_texto',
        'fecha_desde',
        'fecha_hasta',
        'filas',
        'definicion_version',
        'observacion',
        'usuario_id',
    ];

    protected $casts = [
        'reporte_contable_id' => 'integer',
        'filtros' => 'array',
        'filas' => 'integer',
        'definicion_version' => 'integer',
        'usuario_id' => 'integer',
        'fecha_desde' => 'date',
        'fecha_hasta' => 'date',
    ];

    public function reporte(): BelongsTo
    {
        return $this->belongsTo(ReporteContable::class, 'reporte_contable_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    /**
     * El resultado se guarda como JSON crudo: es un documento inmutable, no un modelo.
     *
     * @return array<string, mixed>
     */
    public function resultadoArray(): array
    {
        $decodificado = json_decode((string) $this->resultado, true);

        return is_array($decodificado) ? $decodificado : [];
    }
}
