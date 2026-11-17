<?php

namespace App\Support\ReglaNegocio;

use App\Models\Configuracion\ReglaNegocio;
use App\Models\Configuracion\ReglaNegocioVersion;
use Illuminate\Support\Facades\Schema;

final class ReglaNegocioVersionSupport
{
    /** @var list<string> */
    private const CAMPOS = [
        'codigo', 'nombre', 'descripcion', 'dominio', 'punto',
        'activo', 'prioridad', 'stop_on_match', 'condiciones', 'acciones',
    ];

    public static function snapshot(ReglaNegocio $regla, string $motivo = 'update', ?int $usuarioId = null): ?ReglaNegocioVersion
    {
        if (! Schema::hasTable('regla_negocio_version') || ! $regla->exists) {
            return null;
        }

        $nro = (int) ReglaNegocioVersion::query()
            ->where('regla_negocio_id', $regla->id)
            ->max('nro_version');

        $snap = [];
        foreach (self::CAMPOS as $campo) {
            $snap[$campo] = $regla->getAttribute($campo);
        }

        return ReglaNegocioVersion::query()->create([
            'regla_negocio_id' => $regla->id,
            'nro_version' => $nro + 1,
            'snapshot' => $snap,
            'motivo' => mb_substr($motivo, 0, 40),
            'usuario_id' => $usuarioId,
        ]);
    }

    /**
     * Restaura snapshot (sin tocar codigo). Crea versión previa automáticamente.
     */
    public static function restaurar(ReglaNegocio $regla, int $versionId, ?int $usuarioId = null): ReglaNegocio
    {
        $version = ReglaNegocioVersion::query()
            ->where('regla_negocio_id', $regla->id)
            ->whereKey($versionId)
            ->firstOrFail();

        self::snapshot($regla, 'pre_rollback', $usuarioId);

        $snap = is_array($version->snapshot) ? $version->snapshot : [];
        foreach (self::CAMPOS as $campo) {
            if ($campo === 'codigo' || ! array_key_exists($campo, $snap)) {
                continue;
            }
            $regla->setAttribute($campo, $snap[$campo]);
        }
        $regla->save();

        self::snapshot($regla, 'rollback_v'.$version->nro_version, $usuarioId);

        return $regla->fresh();
    }
}
