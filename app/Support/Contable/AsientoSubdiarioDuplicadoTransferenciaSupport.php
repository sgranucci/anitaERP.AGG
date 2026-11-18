<?php

declare(strict_types=1);

namespace App\Support\Contable;

use App\Models\Contable\Asiento;
use App\Models\Contable\Asiento_Movimiento;
use App\Support\Database\EloquentAuditDeleteSupport;
use Illuminate\Support\Collection;

/**
 * Detecta asientos importados desde subdiario/subhist cuyo comprobante fiscal
 * ya está asentado por transferencia Crown / venta ERP (mismo tipo|letra|PV|nro).
 */
final class AsientoSubdiarioDuplicadoTransferenciaSupport
{
    /**
     * @param  list<int>  $sucursales  Códigos PV Anita; vacío = todas
     * @return list<array{
     *   asiento_id: int,
     *   numeroasiento: int,
     *   fecha: string,
     *   anita_origen: string,
     *   clave: string,
     *   proceso_id: int,
     *   proceso_numeroasiento: int,
     *   venta_id: int|null,
     *   importe: float,
     *   created_at: string|null
     * }>
     */
    public static function detectar(
        int $empresaId,
        string $desde,
        string $hasta,
        array $sucursales = [],
    ): array {
        $empresaId = max(1, $empresaId);
        $desde = trim($desde);
        $hasta = trim($hasta);
        if ($desde === '' || $hasta === '') {
            return [];
        }

        $sucursales = array_values(array_filter(array_map(
            'intval',
            $sucursales,
        ), static fn (int $s) => $s > 0));

        $cubiertos = self::documentosProceso($empresaId, $desde, $hasta, $sucursales);
        if ($cubiertos === []) {
            return [];
        }

        $candidatos = Asiento::query()
            ->where('empresa_id', $empresaId)
            ->whereBetween('fecha', [$desde, $hasta])
            ->whereIn('anita_origen', [
                AsientoAnitaMetadatosSupport::ORIGEN_SUBDIARIO,
                AsientoAnitaMetadatosSupport::ORIGEN_SUBHIST,
            ])
            ->whereNotNull('anita_tipo')
            ->where('anita_sucursal', '>', 0)
            ->where('anita_nro', '>', 0)
            ->when($sucursales !== [], fn ($q) => $q->whereIn('anita_sucursal', $sucursales))
            ->orderBy('fecha')
            ->orderBy('anita_sucursal')
            ->orderBy('anita_nro')
            ->orderBy('id')
            ->get([
                'id', 'numeroasiento', 'fecha', 'anita_origen', 'anita_tipo', 'anita_letra',
                'anita_sucursal', 'anita_nro', 'created_at',
            ]);

        $aBorrar = [];
        foreach ($candidatos as $asiento) {
            $clave = AsientoAnitaMetadatosSupport::claveDocumento(
                $asiento->anita_tipo,
                $asiento->anita_letra,
                $asiento->anita_sucursal,
                $asiento->anita_nro,
            );
            if ($clave === null || ! isset($cubiertos[$clave])) {
                continue;
            }
            $proc = $cubiertos[$clave];
            $aBorrar[] = [
                'asiento_id' => (int) $asiento->id,
                'numeroasiento' => (int) $asiento->numeroasiento,
                'fecha' => (string) $asiento->fecha,
                'anita_origen' => (string) $asiento->anita_origen,
                'clave' => $clave,
                'proceso_id' => (int) $proc['id'],
                'proceso_numeroasiento' => (int) $proc['numeroasiento'],
                'venta_id' => $proc['venta_id'],
                'importe' => 0.0,
                'created_at' => $asiento->created_at !== null ? (string) $asiento->created_at : null,
            ];
        }

        if ($aBorrar === []) {
            return [];
        }

        $ids = array_column($aBorrar, 'asiento_id');
        $importes = self::importesPorAsiento($ids);
        foreach ($aBorrar as $i => $fila) {
            $aBorrar[$i]['importe'] = (float) ($importes[$fila['asiento_id']] ?? 0.0);
        }

        return $aBorrar;
    }

    /**
     * Borra asientos subdiario/subhist del mismo comprobante fiscal (con audits).
     * Usado al grabar transferencia Crown para no dejar el mayor al doble.
     *
     * @return array{asientos: int, movimientos: int}
     */
    public static function eliminarSubdiarioDelDocumento(
        int $empresaId,
        ?string $tipo,
        ?string $letra,
        int $sucursal,
        int $nro,
    ): array {
        $clave = AsientoAnitaMetadatosSupport::claveDocumento($tipo, $letra, $sucursal, $nro);
        if ($clave === null || $empresaId <= 0) {
            return ['asientos' => 0, 'movimientos' => 0];
        }

        $parts = explode('|', $clave);
        $tipoNorm = $parts[0] ?? '';
        $letraNorm = $parts[1] ?? ' ';

        $ids = Asiento::query()
            ->where('empresa_id', $empresaId)
            ->whereIn('anita_origen', [
                AsientoAnitaMetadatosSupport::ORIGEN_SUBDIARIO,
                AsientoAnitaMetadatosSupport::ORIGEN_SUBHIST,
            ])
            ->where('anita_tipo', $tipoNorm)
            ->where('anita_letra', $letraNorm)
            ->where('anita_sucursal', $sucursal)
            ->where('anita_nro', $nro)
            ->pluck('id')
            ->map(static fn ($id) => (int) $id)
            ->all();

        if ($ids === []) {
            return ['asientos' => 0, 'movimientos' => 0];
        }

        $movs = EloquentAuditDeleteSupport::each(
            Asiento_Movimiento::query()->whereIn('asiento_id', $ids)
        );
        $asis = EloquentAuditDeleteSupport::each(
            Asiento::query()->whereIn('id', $ids)
        );

        return ['asientos' => $asis, 'movimientos' => $movs];
    }

    /**
     * @param  list<int>  $asientoIds
     * @return array<int, float> Debe neto (suma de montos positivos) por asiento
     */
    public static function importesPorAsiento(array $asientoIds): array
    {
        if ($asientoIds === []) {
            return [];
        }

        $map = [];
        foreach (array_chunk($asientoIds, 500) as $chunk) {
            $rows = Asiento_Movimiento::query()
                ->whereIn('asiento_id', $chunk)
                ->where('monto', '>', 0)
                ->groupBy('asiento_id')
                ->selectRaw('asiento_id, sum(monto) as debe')
                ->get();
            foreach ($rows as $row) {
                $map[(int) $row->asiento_id] = round((float) $row->debe, 2);
            }
        }

        return $map;
    }

    /**
     * @param  list<int>  $sucursales
     * @return array<string, array{id: int, numeroasiento: int, venta_id: int|null}>
     */
    private static function documentosProceso(
        int $empresaId,
        string $desde,
        string $hasta,
        array $sucursales,
    ): array {
        /** @var Collection<int, Asiento> $proceso */
        $proceso = Asiento::query()
            ->where('empresa_id', $empresaId)
            ->whereBetween('fecha', [$desde, $hasta])
            ->where(function ($q) {
                $q->where(function ($q2) {
                    $q2->whereNotNull('venta_id')->where('venta_id', '>', 0);
                })->orWhere('anita_origen', AsientoAnitaMetadatosSupport::ORIGEN_TRANSFERENCIA);
            })
            ->whereNotNull('anita_tipo')
            ->where('anita_sucursal', '>', 0)
            ->where('anita_nro', '>', 0)
            ->when($sucursales !== [], fn ($q) => $q->whereIn('anita_sucursal', $sucursales))
            ->orderBy('id')
            ->get([
                'id', 'numeroasiento', 'anita_tipo', 'anita_letra',
                'anita_sucursal', 'anita_nro', 'venta_id',
            ]);

        $cubiertos = [];
        foreach ($proceso as $asiento) {
            $clave = AsientoAnitaMetadatosSupport::claveDocumento(
                $asiento->anita_tipo,
                $asiento->anita_letra,
                $asiento->anita_sucursal,
                $asiento->anita_nro,
            );
            if ($clave === null || isset($cubiertos[$clave])) {
                continue;
            }
            $cubiertos[$clave] = [
                'id' => (int) $asiento->id,
                'numeroasiento' => (int) $asiento->numeroasiento,
                'venta_id' => $asiento->venta_id !== null ? (int) $asiento->venta_id : null,
            ];
        }

        return $cubiertos;
    }
}
