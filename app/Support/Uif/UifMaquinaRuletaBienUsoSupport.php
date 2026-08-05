<?php

namespace App\Support\Uif;

use App\Models\Contable\BienUso;
use Illuminate\Support\Facades\DB;

/**
 * Padrón de ruletas electrónicas UIF = máquinas en bien_uso (tema Roulette/Ruleta).
 * No usa listados Excel externos: la tabla ERP es la fuente de verdad.
 */
final class UifMaquinaRuletaBienUsoSupport
{
    /** @var array<int, array<string, true>>|null empresa_id => set de claves normalizadas */
    private static ?array $clavesPorEmpresa = null;

    public static function esRuletaElectronica(?string $posicion, ?int $empresaId): bool
    {
        $empresaId = (int) $empresaId;
        if ($empresaId <= 0) {
            return false;
        }

        foreach (self::clavesDesdePosicion($posicion) as $clave) {
            if (isset(self::clavesPorEmpresa($empresaId)[$clave])) {
                return true;
            }
        }

        return false;
    }

    /**
     * UIDs canónicos (con guión) de ruletas electrónicas por empresa.
     *
     * @return list<string>
     */
    public static function uidsPorEmpresa(int $empresaId): array
    {
        if ($empresaId <= 0) {
            return [];
        }

        return BienUso::query()
            ->where('empresa_id', $empresaId)
            ->where('tipo_bien', 'M')
            ->whereNotNull('uid')
            ->where('uid', '!=', '')
            ->where(function ($q) {
                $q->where('tema', 'like', '%Roulette%')
                    ->orWhere('tema', 'like', '%ROULETTE%')
                    ->orWhere('tema', 'like', '%Ruleta%')
                    ->orWhere('tema', 'like', '%RULETA%');
            })
            ->orderBy('uid')
            ->pluck('uid')
            ->map(static fn ($uid) => strtoupper(trim((string) $uid)))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<string, true>
     */
    public static function clavesPorEmpresa(int $empresaId): array
    {
        if (self::$clavesPorEmpresa !== null && isset(self::$clavesPorEmpresa[$empresaId])) {
            return self::$clavesPorEmpresa[$empresaId];
        }

        $set = [];
        $rows = BienUso::query()
            ->where('empresa_id', $empresaId)
            ->where('tipo_bien', 'M')
            ->where(function ($q) {
                $q->where('tema', 'like', '%Roulette%')
                    ->orWhere('tema', 'like', '%ROULETTE%')
                    ->orWhere('tema', 'like', '%Ruleta%')
                    ->orWhere('tema', 'like', '%RULETA%');
            })
            ->get(['uid', 'codigo_inventario']);

        foreach ($rows as $row) {
            foreach (self::clavesDesdeUidYCodigo($row->uid, $row->codigo_inventario) as $clave) {
                $set[$clave] = true;
            }
        }

        self::$clavesPorEmpresa ??= [];
        self::$clavesPorEmpresa[$empresaId] = $set;

        return $set;
    }

    /**
     * @return list<string>
     */
    public static function clavesDesdePosicion(?string $posicion): array
    {
        $p = strtoupper(trim((string) $posicion));
        if ($p === '' || $p === '-') {
            return [];
        }

        $claves = [$p, str_replace('-', '', $p)];

        if (preg_match('/^(\d+)-(\d+)$/', $p, $m) === 1) {
            $sufijo = $m[2];
            $claves[] = $sufijo;
            $claves[] = ltrim($sufijo, '0') ?: '0';
            $claves[] = sprintf('%04d', (int) $sufijo);
        } elseif (preg_match('/^\d+$/', $p) === 1) {
            $claves[] = ltrim($p, '0') ?: '0';
            $claves[] = sprintf('%04d', (int) $p);
        }

        return array_values(array_unique(array_filter($claves, static fn ($c) => $c !== '')));
    }

    /**
     * @return list<string>
     */
    private static function clavesDesdeUidYCodigo(mixed $uid, mixed $codigoInventario): array
    {
        $claves = [];
        $uidNorm = strtoupper(trim((string) ($uid ?? '')));
        if ($uidNorm !== '') {
            $claves = array_merge($claves, self::clavesDesdePosicion($uidNorm));
        }

        if ($codigoInventario !== null && $codigoInventario !== '') {
            $cod = (string) (int) $codigoInventario;
            $claves[] = $cod;
            $claves[] = sprintf('%04d', (int) $codigoInventario);
        }

        return array_values(array_unique(array_filter($claves, static fn ($c) => $c !== '')));
    }

    /** Solo pruebas / comandos batch. */
    public static function resetCacheForTesting(): void
    {
        self::$clavesPorEmpresa = null;
    }

    /**
     * Empresa ERP = sala UIF en config anita_origenes (1 Biyemas, 2 Kandiko, 3 Rebisco).
     */
    public static function empresaIdDesdeSalaUif(?int $salaId): int
    {
        $salaId = (int) $salaId;
        if ($salaId <= 0) {
            return 0;
        }

        foreach (config('uif.anita_origenes', []) as $cfg) {
            if ((int) ($cfg['sala_id'] ?? 0) === $salaId) {
                return (int) ($cfg['empresa_id'] ?? $salaId);
            }
        }

        return $salaId;
    }

    public static function cantidadRuletasEnPadron(?int $empresaId = null): int
    {
        $q = BienUso::query()
            ->where('tipo_bien', 'M')
            ->where(function ($w) {
                $w->where('tema', 'like', '%Roulette%')
                    ->orWhere('tema', 'like', '%ROULETTE%')
                    ->orWhere('tema', 'like', '%Ruleta%')
                    ->orWhere('tema', 'like', '%RULETA%');
            });

        if ($empresaId !== null && $empresaId > 0) {
            $q->where('empresa_id', $empresaId);
        }

        return (int) $q->count();
    }

    /** Diagnóstico: conteo por empresa vía query builder (sin cache). */
    public static function resumenPadron(): array
    {
        return BienUso::query()
            ->select('empresa_id', DB::raw('COUNT(*) as c'))
            ->where('tipo_bien', 'M')
            ->where(function ($w) {
                $w->where('tema', 'like', '%Roulette%')
                    ->orWhere('tema', 'like', '%ROULETTE%')
                    ->orWhere('tema', 'like', '%Ruleta%')
                    ->orWhere('tema', 'like', '%RULETA%');
            })
            ->groupBy('empresa_id')
            ->orderBy('empresa_id')
            ->get()
            ->map(static fn ($r) => [
                'empresa_id' => (int) $r->empresa_id,
                'cantidad' => (int) $r->c,
            ])
            ->all();
    }
}
