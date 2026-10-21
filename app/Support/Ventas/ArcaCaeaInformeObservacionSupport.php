<?php

declare(strict_types=1);

namespace App\Support\Ventas;

use App\Models\Ventas\ArcaCaea;
use App\Models\Ventas\ArcaCaeaInformeObservacion;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Texto de cada observación ARCA al presentar CAEA.
 * Se conservan los últimos 3 meses y se borran las anteriores.
 */
final class ArcaCaeaInformeObservacionSupport
{
    public const MESES_RETENCION = 3;

    /**
     * @param  array{
     *   pto_vta: int,
     *   tipo_afip: int,
     *   numero: int,
     *   texto?: string,
     *   fuente?: string,
     *   tipo_anita?: string|null,
     *   letra?: string|null,
     *   venta_id?: int|null,
     *   fecha_comprobante?: string|null
     * }  $datos
     */
    public static function registrar(ArcaCaea $registro, array $datos): void
    {
        $empresaId = (int) $registro->empresa_id;
        $ptoVta = (int) ($datos['pto_vta'] ?? 0);
        $tipoAfip = (int) ($datos['tipo_afip'] ?? 0);
        $numero = (int) ($datos['numero'] ?? 0);
        if ($empresaId < 1 || $ptoVta < 1 || $tipoAfip < 1 || $numero < 1) {
            return;
        }

        $texto = trim((string) ($datos['texto'] ?? ''));
        if ($texto === '') {
            return;
        }

        $clave = [
            'empresa_id' => $empresaId,
            'pto_vta' => $ptoVta,
            'tipo_afip' => $tipoAfip,
            'numero' => $numero,
        ];

        $tipoAnita = strtoupper(trim((string) ($datos['tipo_anita'] ?? '')));
        $letra = strtoupper(trim((string) ($datos['letra'] ?? '')));

        ArcaCaeaInformeObservacion::query()->updateOrCreate($clave, [
            'arca_caea_id' => (int) $registro->id,
            'venta_id' => ($datos['venta_id'] ?? null) !== null ? (int) $datos['venta_id'] : null,
            'fuente' => (string) ($datos['fuente'] ?? 'erp'),
            'tipo_anita' => $tipoAnita !== '' ? substr($tipoAnita, 0, 8) : null,
            'letra' => $letra !== '' ? substr($letra, 0, 1) : null,
            'fecha_comprobante' => self::fecha($datos['fecha_comprobante'] ?? null),
            'texto' => $texto,
            'informado_at' => now(),
        ]);

        self::purgar();
    }

    public static function purgar(?Carbon $ahora = null): int
    {
        $corte = ($ahora ?? now())->copy()->subMonths(self::MESES_RETENCION);

        return ArcaCaeaInformeObservacion::query()
            ->where('informado_at', '<', $corte)
            ->delete();
    }

    /**
     * @param  list<int>  $empresaIds
     * @return Collection<int, ArcaCaeaInformeObservacion>
     */
    public static function listar(array $empresaIds, ?int $arcaCaeaId = null): Collection
    {
        self::purgar();

        $empresaIds = array_values(array_filter(
            array_map(static fn ($id): int => (int) $id, $empresaIds),
            static fn (int $id): bool => $id > 0,
        ));
        if ($empresaIds === []) {
            return new Collection;
        }

        $corte = now()->subMonths(self::MESES_RETENCION);

        return ArcaCaeaInformeObservacion::query()
            ->whereIn('empresa_id', $empresaIds)
            ->where('informado_at', '>=', $corte)
            ->when($arcaCaeaId !== null && $arcaCaeaId > 0, fn ($q) => $q->where('arca_caea_id', $arcaCaeaId))
            ->orderByDesc('informado_at')
            ->orderByDesc('id')
            ->limit(300)
            ->get();
    }

    private static function fecha(mixed $raw): ?string
    {
        if ($raw instanceof Carbon) {
            return $raw->toDateString();
        }

        $d = preg_replace('/\D+/', '', (string) $raw) ?? '';
        if (strlen($d) < 8) {
            return null;
        }

        return substr($d, 0, 4).'-'.substr($d, 4, 2).'-'.substr($d, 6, 2);
    }
}
