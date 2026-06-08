<?php

namespace App\Support\Ventas\Waitry;

use App\Models\Ventas\TotemWaitryGastronomia;
use Illuminate\Support\Collection;

/**
 * Agrupa ingresos Waitry por tótem y medio de pago.
 *
 * - {@see armar()}: resumen operativo del cierre tótem (MP, Totalcoin, efectivo facturado, etc.).
 * - {@see armarParaInformeZ()}: columna Sistema del Informe Z — cobros Waitry QR/MP/Posnet
 *   ({@see lineaEntraInformeZSistema()}); excluye ventas facturadas y cobradas en cuenta real Anita.
 */
final class WaitryTotemJornadaResumenSupport
{
    private const CLAVE_SIN_TOTEM = 0;

    /**
     * @param  Collection<int, TotemWaitryGastronomia>  $totems
     * @param  list<array<string, mixed>>  $lineas
     * @return array{
     *   por_totem: list<array<string, mixed>>,
     *   total_general: array{cantidad_ordenes:int,total_ingreso:float,por_medio_pago:list<array<string, mixed>>}
     * }
     */
    public static function armar(Collection $totems, array $lineas, int $empresaId = 0): array
    {
        $mapTotemsPorLayout = self::mapaTotemsPorLayoutId($totems);
        $mapTotemsPorTable = self::mapaTotemsPorTableId($totems);
        $unicoTotem = $totems->count() === 1 ? $totems->first() : null;
        if ($empresaId <= 0 && $totems->isNotEmpty()) {
            $empresaId = (int) ($totems->first()->empresa_id ?? 0);
        }

        $buckets = [];

        foreach ($lineas as $ln) {
            if (! self::lineaCuentaParaIngresoTotem($ln)) {
                continue;
            }

            $clave = self::claveTotemParaLinea($ln, $mapTotemsPorLayout, $mapTotemsPorTable, $unicoTotem, $totems);
            if (! isset($buckets[$clave])) {
                $buckets[$clave] = self::bucketVacio($clave, $unicoTotem, $totems);
            }

            $monto = self::montoIngresoLinea($ln);
            $tipo = WaitryMedioPagoCuentacajaSupport::resolverTipoMedioInformeZDesdeLinea($ln, $empresaId);
            if ($tipo === null) {
                continue;
            }
            $gateway = WaitryPaymentGatewaySupport::extraerGatewayDesdeLinea($ln);
            $medioKey = $empresaId > 0
                ? WaitryMedioPagoCuentacajaSupport::claveMedioInformeZ($tipo, $empresaId, $gateway)
                : $tipo;
            if ($medioKey === '__excl__') {
                continue;
            }

            if (! isset($buckets[$clave]['medios'][$medioKey])) {
                $buckets[$clave]['medios'][$medioKey] = [
                    'tipo' => $tipo,
                    'etiqueta' => (string) ($ln['waitry_medio_label'] ?? WaitryMedioPagoCuentacajaSupport::etiquetaTipo($tipo)),
                    'cuentacaja_label' => $ln['cuentacaja_esperada_label'] ?? null,
                    'cantidad' => 0,
                    'total' => 0.0,
                ];
            }

            $buckets[$clave]['medios'][$medioKey]['cantidad']++;
            $buckets[$clave]['medios'][$medioKey]['total'] = round(
                $buckets[$clave]['medios'][$medioKey]['total'] + $monto,
                2,
            );
            $buckets[$clave]['cantidad_ordenes']++;
            $buckets[$clave]['total_ingreso'] = round($buckets[$clave]['total_ingreso'] + $monto, 2);
            self::acumularDesglosePorTableId($buckets[$clave], $ln, $monto, $tipo, $medioKey);
        }

        ksort($buckets);
        $porTotem = [];
        $globalMedios = [];
        $globalCantidad = 0;
        $globalIngreso = 0.0;

        foreach ($buckets as $bucket) {
            $medios = array_values($bucket['medios']);
            usort($medios, fn (array $a, array $b) => strcmp($a['etiqueta'], $b['etiqueta']));

            $porTotem[] = [
                'totem_id' => $bucket['totem_id'],
                'ubicacion_nombre' => $bucket['ubicacion_nombre'],
                'detalle' => $bucket['detalle'],
                'waitry_layout_id' => $bucket['waitry_layout_id'],
                'waitry_table_id' => $bucket['waitry_table_id'],
                'cantidad_ordenes' => (int) $bucket['cantidad_ordenes'],
                'total_ingreso' => (float) $bucket['total_ingreso'],
                'por_medio_pago' => $medios,
                'por_table_id' => self::formatearDesglosePorTableId($bucket['por_table_id'] ?? []),
            ];

            $globalCantidad += (int) $bucket['cantidad_ordenes'];
            $globalIngreso = round($globalIngreso + (float) $bucket['total_ingreso'], 2);

            foreach ($medios as $medio) {
                $tipo = WaitryMedioPagoCuentacajaSupport::tipoRepresentativoInformeZ($medio['tipo'] ?? null);
                if ($tipo === null) {
                    continue;
                }
                $globalKey = $empresaId > 0
                    ? WaitryMedioPagoCuentacajaSupport::claveMedioInformeZ($tipo, $empresaId)
                    : $tipo;
                if ($globalKey === '__excl__') {
                    continue;
                }
                if (! isset($globalMedios[$globalKey])) {
                    $globalMedios[$globalKey] = [
                        'tipo' => $medio['tipo'],
                        'etiqueta' => $medio['etiqueta'],
                        'cuentacaja_label' => $medio['cuentacaja_label'],
                        'cantidad' => 0,
                        'total' => 0.0,
                    ];
                }
                $globalMedios[$globalKey]['cantidad'] += (int) $medio['cantidad'];
                $globalMedios[$globalKey]['total'] = round($globalMedios[$globalKey]['total'] + (float) $medio['total'], 2);
            }
        }

        $globalMediosList = array_values($globalMedios);
        usort($globalMediosList, fn (array $a, array $b) => strcmp($a['etiqueta'], $b['etiqueta']));

        $resumen = [
            'por_totem' => $porTotem,
            'total_general' => [
                'cantidad_ordenes' => $globalCantidad,
                'total_ingreso' => $globalIngreso,
                'por_medio_pago' => $globalMediosList,
            ],
        ];

        $resumen = self::agregarBloquesPorTotemConfigurado($resumen, $totems);

        return self::consolidarIngresoEnUnicoTotemSiAplica($resumen, $totems);
    }

    /**
     * Totales Sistema del Informe Z: suma por tótem cobros Waitry QR/MP/Posnet (ver {@see lineaEntraInformeZSistema()}).
     *
     * @param  Collection<int, TotemWaitryGastronomia>  $totems
     * @param  list<array<string, mixed>>  $lineas
     * @return array{
     *   por_totem: list<array<string, mixed>>,
     *   total_general: array{cantidad_ordenes:int,total_ingreso:float,por_medio_pago:list<array<string, mixed>>}
     * }
     */
    public static function armarParaInformeZ(Collection $totems, array $lineas, int $empresaId = 0): array
    {
        $mapTotemsPorLayout = self::mapaTotemsPorLayoutId($totems);
        $mapTotemsPorTable = self::mapaTotemsPorTableId($totems);
        $unicoTotem = $totems->count() === 1 ? $totems->first() : null;
        if ($empresaId <= 0 && $totems->isNotEmpty()) {
            $empresaId = (int) ($totems->first()->empresa_id ?? 0);
        }

        $buckets = [];

        foreach ($lineas as $ln) {
            if (! self::lineaEntraInformeZSistema($ln)) {
                continue;
            }

            $clave = self::claveTotemParaLinea($ln, $mapTotemsPorLayout, $mapTotemsPorTable, $unicoTotem, $totems);
            if (! isset($buckets[$clave])) {
                $buckets[$clave] = self::bucketVacio($clave, $unicoTotem, $totems);
            }

            $monto = self::montoIngresoLinea($ln);
            $gateway = WaitryPaymentGatewaySupport::extraerGatewayDesdeLinea($ln);
            $tipo = WaitryMedioPagoCuentacajaSupport::resolverTipoMedioInformeZDesdeLinea($ln, $empresaId);
            if ($tipo === null) {
                continue;
            }
            $tipoCanon = WaitryMedioPagoCuentacajaSupport::tipoRepresentativoInformeZ($tipo, $gateway) ?? $tipo;
            $medioKey = $empresaId > 0
                ? WaitryMedioPagoCuentacajaSupport::claveMedioInformeZ($tipo, $empresaId, $gateway)
                : $tipoCanon;
            if ($medioKey === '__excl__') {
                continue;
            }

            if (! isset($buckets[$clave]['medios'][$medioKey])) {
                $buckets[$clave]['medios'][$medioKey] = [
                    'tipo' => $tipoCanon,
                    'etiqueta' => (string) ($ln['waitry_medio_label'] ?? WaitryMedioPagoCuentacajaSupport::etiquetaTipo($tipoCanon, $gateway)),
                    'cuentacaja_label' => $ln['cuentacaja_esperada_label'] ?? null,
                    'cantidad' => 0,
                    'total' => 0.0,
                ];
            }

            $buckets[$clave]['medios'][$medioKey]['cantidad']++;
            $buckets[$clave]['medios'][$medioKey]['total'] = round(
                $buckets[$clave]['medios'][$medioKey]['total'] + $monto,
                2,
            );
            $buckets[$clave]['cantidad_ordenes']++;
            $buckets[$clave]['total_ingreso'] = round($buckets[$clave]['total_ingreso'] + $monto, 2);
            self::acumularDesglosePorTableId($buckets[$clave], $ln, $monto, $tipoCanon, $medioKey);
        }

        ksort($buckets);
        $porTotem = [];
        $globalMedios = [];
        $globalCantidad = 0;
        $globalIngreso = 0.0;

        foreach ($buckets as $bucket) {
            $medios = array_values($bucket['medios']);
            usort($medios, fn (array $a, array $b) => strcmp($a['etiqueta'], $b['etiqueta']));

            $porTotem[] = [
                'totem_id' => $bucket['totem_id'],
                'ubicacion_nombre' => $bucket['ubicacion_nombre'],
                'detalle' => $bucket['detalle'],
                'waitry_layout_id' => $bucket['waitry_layout_id'],
                'waitry_table_id' => $bucket['waitry_table_id'],
                'cantidad_ordenes' => (int) $bucket['cantidad_ordenes'],
                'total_ingreso' => (float) $bucket['total_ingreso'],
                'por_medio_pago' => $medios,
                'por_table_id' => self::formatearDesglosePorTableId($bucket['por_table_id'] ?? []),
            ];

            $globalCantidad += (int) $bucket['cantidad_ordenes'];
            $globalIngreso = round($globalIngreso + (float) $bucket['total_ingreso'], 2);

            foreach ($medios as $medio) {
                $tipo = WaitryMedioPagoCuentacajaSupport::tipoRepresentativoInformeZ($medio['tipo'] ?? null);
                if ($tipo === null) {
                    continue;
                }
                $globalKey = $empresaId > 0
                    ? WaitryMedioPagoCuentacajaSupport::claveMedioInformeZ($tipo, $empresaId)
                    : $tipo;
                if ($globalKey === '__excl__') {
                    continue;
                }
                if (! isset($globalMedios[$globalKey])) {
                    $globalMedios[$globalKey] = $medio;
                } else {
                    $globalMedios[$globalKey]['cantidad'] += (int) ($medio['cantidad'] ?? 0);
                    $globalMedios[$globalKey]['total'] = round(
                        (float) ($globalMedios[$globalKey]['total'] ?? 0) + (float) ($medio['total'] ?? 0),
                        2,
                    );
                }
            }
        }

        $globalMediosList = array_values($globalMedios);
        usort($globalMediosList, fn (array $a, array $b) => strcmp($a['etiqueta'], $b['etiqueta']));

        $resumen = [
            'por_totem' => $porTotem,
            'total_general' => [
                'cantidad_ordenes' => $globalCantidad,
                'total_ingreso' => $globalIngreso,
                'por_medio_pago' => $globalMediosList,
            ],
        ];

        $resumen = self::agregarBloquesPorTotemConfigurado($resumen, $totems);

        return self::consolidarIngresoEnUnicoTotemSiAplica($resumen, $totems);
    }

    /**
     * Informe Z — columna Sistema: cobros Waitry que Anita aún no presentó en caja real.
     *
     * Incluye:
     * - Sin facturar en Anita: tótem (QR kiosco, MP, Posnet) y QR por celular ({@code interface} + gateway).
     * - Facturadas con cobro puente TOTEM o {@code waitry_cobro_totem}.
     *
     * Excluye: facturadas y cobradas en cuenta operativa Anita (incl. Posnet ya rendido por Anita).
     *
     * @param  array<string, mixed>  $ln
     */
    public static function lineaEntraInformeZSistema(array $ln): bool
    {
        if (WaitryOrdenEstadoSupport::esCanceladaLinea($ln)) {
            return false;
        }

        if (WaitryOrdenEstadoSupport::esAnuladaPorDescuentoTotalLinea($ln)) {
            return false;
        }

        if (! self::cobradaEnWaitryLinea($ln)) {
            return false;
        }

        if (! WaitryMedioPagoCuentacajaSupport::lineaEntraInformeZSistema($ln)) {
            return false;
        }

        $empresaId = (int) ($ln['empresa_id'] ?? 0);
        if (WaitryMedioPagoCuentacajaSupport::resolverTipoMedioInformeZDesdeLinea($ln, $empresaId) === null) {
            return false;
        }

        if (! empty($ln['facturada_erp'])) {
            if (! empty($ln['anita_es_totem']) || ! empty($ln['waitry_cobro_totem'])) {
                return true;
            }

            return (int) ($ln['anita_cuentacaja_id'] ?? 0) <= 0;
        }

        return true;
    }

    /**
     * Orden de origen tótem físico (no facturada en Anita sin paso por cobro en Posnet).
     *
     * @param  array<string, mixed>  $ln
     */
    public static function lineaEsCobroPosnetTotemFisico(array $ln): bool
    {
        if (empty($ln['importada_erp'])) {
            return true;
        }

        return ! empty($ln['waitry_cobro_totem']);
    }

    /**
     * Si la empresa tiene un solo tótem, suma ingresos del bucket "sin tótem asignado" a ese tótem
     * (órdenes Waitry sin tableId o con waitry_table_id sin match).
     *
     * @param  array{por_totem:list<array<string,mixed>>,total_general:array<string,mixed>}  $resumen
     * @return array{por_totem:list<array<string,mixed>>,total_general:array<string,mixed>}
     */
    public static function consolidarIngresoEnUnicoTotemSiAplica(array $resumen, Collection $totems): array
    {
        if ($totems->count() !== 1) {
            return $resumen;
        }

        $unico = $totems->first();
        if ($unico === null) {
            return $resumen;
        }

        $unicoId = (int) $unico->id;
        $huerfano = null;
        $destinoIdx = null;
        $porTotem = $resumen['por_totem'] ?? [];

        foreach ($porTotem as $idx => $bloque) {
            $tid = (int) ($bloque['totem_id'] ?? 0);
            if ($tid === $unicoId) {
                $destinoIdx = $idx;
            } elseif ($tid <= 0) {
                $huerfano = $bloque;
            }
        }

        if ($huerfano === null || ($huerfano['total_ingreso'] ?? 0) <= 0.0001) {
            return $resumen;
        }

        if ($destinoIdx === null) {
            $porTotem[] = [
                'totem_id' => $unicoId,
                'ubicacion_nombre' => (string) ($unico->ubicacion?->nombre ?? '—'),
                'detalle' => trim((string) ($unico->detalle ?? '')),
                'waitry_layout_id' => $unico->waitry_layout_id,
                'waitry_table_id' => $unico->waitry_table_id,
                'cantidad_ordenes' => 0,
                'total_ingreso' => 0.0,
                'por_medio_pago' => [],
            ];
            $destinoIdx = count($porTotem) - 1;
        }

        $destino = $porTotem[$destinoIdx];
        $mediosDest = [];
        foreach ($destino['por_medio_pago'] ?? [] as $m) {
            $tipo = WaitryMedioPagoCuentacajaSupport::tipoRepresentativoInformeZ($m['tipo'] ?? null);
            if ($tipo === null) {
                continue;
            }
            $mediosDest[$tipo] = $m;
        }
        foreach ($huerfano['por_medio_pago'] ?? [] as $m) {
            $tipo = WaitryMedioPagoCuentacajaSupport::tipoRepresentativoInformeZ($m['tipo'] ?? null);
            if ($tipo === null) {
                continue;
            }
            if (! isset($mediosDest[$tipo])) {
                $mediosDest[$tipo] = [
                    'tipo' => $m['tipo'] ?? null,
                    'etiqueta' => $m['etiqueta'] ?? WaitryMedioPagoCuentacajaSupport::etiquetaTipo($tipo),
                    'cuentacaja_label' => $m['cuentacaja_label'] ?? null,
                    'cantidad' => 0,
                    'total' => 0.0,
                ];
            }
            $mediosDest[$tipo]['cantidad'] += (int) ($m['cantidad'] ?? 0);
            $mediosDest[$tipo]['total'] = round((float) $mediosDest[$tipo]['total'] + (float) ($m['total'] ?? 0), 2);
        }

        $porTotem[$destinoIdx] = [
            'totem_id' => $unicoId,
            'ubicacion_nombre' => $destino['ubicacion_nombre'] ?? (string) ($unico->ubicacion?->nombre ?? '—'),
            'detalle' => $destino['detalle'] ?? trim((string) ($unico->detalle ?? '')),
            'waitry_layout_id' => $destino['waitry_layout_id'] ?? $unico->waitry_layout_id,
            'waitry_table_id' => $destino['waitry_table_id'] ?? $unico->waitry_table_id,
            'cantidad_ordenes' => (int) ($destino['cantidad_ordenes'] ?? 0) + (int) ($huerfano['cantidad_ordenes'] ?? 0),
            'total_ingreso' => round((float) ($destino['total_ingreso'] ?? 0) + (float) ($huerfano['total_ingreso'] ?? 0), 2),
            'por_medio_pago' => array_values($mediosDest),
        ];

        $porTotem = array_values(array_filter(
            $porTotem,
            fn (array $b) => (int) ($b['totem_id'] ?? 0) > 0,
        ));

        $resumen['por_totem'] = $porTotem;

        return $resumen;
    }

    /**
     * Cobro en el POS físico Waitry que entra al Informe Z (MP, Totalcoin, etc.).
     * La cuenta puente TOTEM de facturación Anita no cuenta aquí.
     *
     * @param  array<string, mixed>  $ln
     */
    public static function lineaCuentaParaIngresoTotem(array $ln): bool
    {
        if (WaitryOrdenEstadoSupport::esCanceladaLinea($ln)) {
            return false;
        }

        if (WaitryOrdenEstadoSupport::esAnuladaPorDescuentoTotalLinea($ln)) {
            return false;
        }

        if (! self::cobradaEnWaitryLinea($ln)) {
            return false;
        }

        $empresaId = (int) ($ln['empresa_id'] ?? 0);

        return WaitryMedioPagoCuentacajaSupport::resolverTipoMedioInformeZDesdeLinea($ln, $empresaId) !== null;
    }

    /**
     * Mismo criterio que {@see \App\Support\Ventas\Gastronomia\CierreJornadaProcesoGrillaSupport} (fila Waitry pagado).
     *
     * @param  array<string, mixed>  $ln
     */
    public static function cobradaEnWaitryLinea(array $ln): bool
    {
        if (! empty($ln['waitry_cobro_totem'])) {
            return true;
        }

        if (($ln['paid_waitry'] ?? null) === true) {
            return true;
        }

        return (float) ($ln['monto_cobro_waitry'] ?? 0) > 0.0001;
    }

    /**
     * @param  array<string, mixed>  $ln
     */
    private static function montoIngresoLinea(array $ln): float
    {
        $montoCobro = (float) ($ln['monto_cobro_waitry'] ?? 0);
        if ($montoCobro > 0.0001) {
            return round($montoCobro, 2);
        }

        return round((float) ($ln['total'] ?? 0), 2);
    }

    /**
     * @param  array<int, TotemWaitryGastronomia>  $mapTotemsPorLayout
     * @param  array<int, TotemWaitryGastronomia>  $mapTotemsPorTable
     * @param  array<string, mixed>  $ln
     */
    private static function claveTotemParaLinea(
        array $ln,
        array $mapTotemsPorLayout,
        array $mapTotemsPorTable,
        ?TotemWaitryGastronomia $unicoTotem,
        Collection $totems,
    ): int {
        $layoutId = isset($ln['waitry_layout_id']) ? (int) $ln['waitry_layout_id'] : 0;
        if ($layoutId > 0 && isset($mapTotemsPorLayout[$layoutId])) {
            return (int) $mapTotemsPorLayout[$layoutId]->id;
        }

        $tableId = isset($ln['waitry_table_id']) ? (int) $ln['waitry_table_id'] : 0;
        if ($tableId > 0 && isset($mapTotemsPorTable[$tableId])) {
            $totemTable = $mapTotemsPorTable[$tableId];
            if ($layoutId <= 0 || $totemTable->waitryLayoutId() <= 0 || $totemTable->waitryLayoutId() === $layoutId) {
                return (int) $totemTable->id;
            }
        }

        if ($unicoTotem !== null) {
            return (int) $unicoTotem->id;
        }

        if (self::totemsSinConfiguracionMatchWaitry($totems) && $totems->count() > 0) {
            $orderId = (int) ($ln['waitry_order_id'] ?? 0);
            $lista = $totems->values();
            $idx = $orderId > 0 ? $orderId % $lista->count() : 0;

            return (int) $lista[$idx]->id;
        }

        return self::CLAVE_SIN_TOTEM;
    }

    /**
     * Tótems sin layout ni tableId en ABM: no se puede mapear comandas Waitry al tótem físico.
     */
    private static function totemsSinConfiguracionMatchWaitry(Collection $totems): bool
    {
        if ($totems->isEmpty()) {
            return false;
        }

        foreach ($totems as $totem) {
            if ($totem->tieneConfiguracionMatchWaitry()) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<int, TotemWaitryGastronomia>
     */
    private static function mapaTotemsPorLayoutId(Collection $totems): array
    {
        $map = [];
        foreach ($totems as $totem) {
            foreach ($totem->waitryLayoutIds() as $layoutId) {
                $map[$layoutId] = $totem;
            }
        }

        return $map;
    }

    /**
     * @return array<int, TotemWaitryGastronomia>
     */
    private static function mapaTotemsPorTableId(Collection $totems): array
    {
        $map = [];
        foreach ($totems as $totem) {
            foreach ($totem->waitryTableIds() as $tableId) {
                $map[$tableId] = $totem;
            }
        }

        return $map;
    }

    /**
     * @return array<string, mixed>
     */
    private static function bucketVacio(
        int $clave,
        ?TotemWaitryGastronomia $unicoTotem,
        Collection $totems,
    ): array {
        if ($clave === self::CLAVE_SIN_TOTEM) {
            return [
                'totem_id' => null,
                'ubicacion_nombre' => 'Sin tótem asignado',
                'detalle' => $totems->isEmpty()
                    ? 'Configure tótems Waitry en Ventas → Tótem Waitry'
                    : 'Sin match: cargue waitry_layout_id (punto de acceso) o waitry_table_id en cada tótem',
                'waitry_layout_id' => null,
                'waitry_table_id' => null,
                'cantidad_ordenes' => 0,
                'total_ingreso' => 0.0,
                'medios' => [],
                'por_table_id' => [],
            ];
        }

        $totem = $unicoTotem !== null && (int) $unicoTotem->id === $clave
            ? $unicoTotem
            : $totems->firstWhere('id', $clave);

        return [
            'totem_id' => $totem?->id,
            'ubicacion_nombre' => (string) ($totem?->ubicacion?->nombre ?? '—'),
            'detalle' => trim((string) ($totem?->detalle ?? '')),
            'waitry_layout_id' => $totem?->waitry_layout_id,
            'waitry_table_id' => $totem?->waitry_table_id,
            'cantidad_ordenes' => 0,
            'total_ingreso' => 0.0,
            'medios' => [],
            'por_table_id' => [],
        ];
    }

    /**
     * @param  array<string, mixed>  $bucket
     * @param  array<string, mixed>  $ln
     */
    private static function acumularDesglosePorTableId(
        array &$bucket,
        array $ln,
        float $monto,
        ?string $tipo,
        string $medioKey,
    ): void {
        $tableId = isset($ln['waitry_table_id']) ? (int) $ln['waitry_table_id'] : 0;
        if ($tableId <= 0) {
            return;
        }

        if (! isset($bucket['por_table_id'][$tableId])) {
            $bucket['por_table_id'][$tableId] = [
                'waitry_table_id' => $tableId,
                'waitry_table_name' => $ln['waitry_table_name'] ?? null,
                'cantidad_ordenes' => 0,
                'total_ingreso' => 0.0,
                'medios' => [],
            ];
        }

        $bucket['por_table_id'][$tableId]['cantidad_ordenes']++;
        $bucket['por_table_id'][$tableId]['total_ingreso'] = round(
            $bucket['por_table_id'][$tableId]['total_ingreso'] + $monto,
            2,
        );

        if ($tipo === null || $medioKey === '__excl__') {
            return;
        }

        if (! isset($bucket['por_table_id'][$tableId]['medios'][$medioKey])) {
            $bucket['por_table_id'][$tableId]['medios'][$medioKey] = [
                'tipo' => $tipo,
                'etiqueta' => WaitryMedioPagoCuentacajaSupport::etiquetaTipo($tipo),
                'cantidad' => 0,
                'total' => 0.0,
            ];
        }

        $bucket['por_table_id'][$tableId]['medios'][$medioKey]['cantidad']++;
        $bucket['por_table_id'][$tableId]['medios'][$medioKey]['total'] = round(
            $bucket['por_table_id'][$tableId]['medios'][$medioKey]['total'] + $monto,
            2,
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $porTableId
     * @return list<array<string, mixed>>
     */
    private static function formatearDesglosePorTableId(array $porTableId): array
    {
        if ($porTableId === []) {
            return [];
        }

        ksort($porTableId);
        $out = [];
        foreach ($porTableId as $tableId => $bloque) {
            $medios = array_values($bloque['medios'] ?? []);
            usort($medios, fn (array $a, array $b) => strcmp($a['etiqueta'] ?? '', $b['etiqueta'] ?? ''));

            $out[] = [
                'waitry_table_id' => (int) $tableId,
                'waitry_table_name' => $bloque['waitry_table_name'] ?? null,
                'cantidad_ordenes' => (int) ($bloque['cantidad_ordenes'] ?? 0),
                'total_ingreso' => (float) ($bloque['total_ingreso'] ?? 0),
                'por_medio_pago' => $medios,
            ];
        }

        return $out;
    }

    /**
     * Garantiza un bloque por tótem configurado en el ABM (aunque el ingreso sea 0).
     *
     * @param  array{por_totem:list<array<string,mixed>>,total_general:array<string,mixed>}  $resumen
     * @return array{por_totem:list<array<string,mixed>>,total_general:array<string,mixed>}
     */
    private static function agregarBloquesPorTotemConfigurado(array $resumen, Collection $totems): array
    {
        if ($totems->isEmpty()) {
            return $resumen;
        }

        $porId = [];
        foreach ($resumen['por_totem'] ?? [] as $bloque) {
            $tid = (int) ($bloque['totem_id'] ?? 0);
            if ($tid > 0) {
                $porId[$tid] = $bloque;
            }
        }

        $porTotem = [];
        foreach ($totems as $totem) {
            $tid = (int) $totem->id;
            if (isset($porId[$tid])) {
                $porTotem[] = $porId[$tid];

                continue;
            }

            $porTotem[] = [
                'totem_id' => $tid,
                'ubicacion_nombre' => (string) ($totem->ubicacion?->nombre ?? '—'),
                'detalle' => trim((string) ($totem->detalle ?? '')),
                'waitry_layout_id' => $totem->waitry_layout_id,
                'waitry_table_id' => $totem->waitry_table_id,
                'cantidad_ordenes' => 0,
                'total_ingreso' => 0.0,
                'por_medio_pago' => [],
                'por_table_id' => [],
            ];
        }

        $resumen['por_totem'] = $porTotem;

        return $resumen;
    }
}
