<?php

namespace App\Support\Ventas\Waitry;

use App\Models\Ventas\TotemWaitryGastronomia;

/**
 * Concilia totales Sistema (cobros Waitry QR/MP/Posnet no cobrados en caja Anita real) vs Informe Z por tótem.
 */
final class WaitryInformeZConciliacionSupport
{
    public static function toleranciaMonto(): float
    {
        return max(0.0, (float) config('gastronomia.cierre_totem_informe_z_tolerancia', 0.02));
    }

    /**
     * Resumen para plantilla Informe Z: preferir {@see resumen_informe_z} persistido al cierre.
     *
     * @param  array<string, mixed>  $detalle  detalle_json del cierre tótem
     * @return array{por_totem:list<array<string,mixed>>,total_general:array<string,mixed>}
     */
    public static function resumenSistemaDesdeDetalleCierre(array $detalle): array
    {
        $informeZ = $detalle['resumen_informe_z'] ?? null;
        if (is_array($informeZ) && is_array($informeZ['por_totem'] ?? null)) {
            return $informeZ;
        }

        return self::filtrarResumenSoloCreditCardPosnet(
            is_array($detalle['resumen_totems'] ?? null) ? $detalle['resumen_totems'] : ['por_totem' => [], 'total_general' => []],
        );
    }

    /**
     * @param  array{por_totem:list<array<string,mixed>>,total_general?:array<string,mixed>}  $resumen
     * @return array{por_totem:list<array<string,mixed>>,total_general:array<string,mixed>}
     */
    public static function filtrarResumenSoloCreditCardPosnet(array $resumen): array
    {
        $porTotem = [];
        $globalCantidad = 0;
        $globalIngreso = 0.0;
        $globalMedios = [];

        foreach ($resumen['por_totem'] ?? [] as $bloque) {
            if (! is_array($bloque)) {
                continue;
            }
            $mediosFiltrados = [];
            $totalBloque = 0.0;
            $cantBloque = 0;

            foreach ($bloque['por_medio_pago'] ?? [] as $m) {
                if (! is_array($m)) {
                    continue;
                }
                $tipo = WaitryMedioPagoCuentacajaSupport::normalizarTipo($m['tipo'] ?? null);
                if (! WaitryMedioPagoCuentacajaSupport::esTipoPagoInformeZSistema($tipo)) {
                    continue;
                }
                $mediosFiltrados[] = $m;
                $totalBloque = round($totalBloque + (float) ($m['total'] ?? 0), 2);
                $cantBloque += (int) ($m['cantidad'] ?? 0);
            }

            if ($mediosFiltrados === [] && (float) ($bloque['total_ingreso'] ?? 0) <= 0.0001) {
                continue;
            }

            $porTotem[] = [
                ...$bloque,
                'cantidad_ordenes' => $cantBloque > 0 ? $cantBloque : (int) ($bloque['cantidad_ordenes'] ?? 0),
                'total_ingreso' => $totalBloque > 0.0001 ? $totalBloque : round((float) ($bloque['total_ingreso'] ?? 0), 2),
                'por_medio_pago' => $mediosFiltrados,
                'por_table_id' => self::filtrarPorTableIdInformeZ($bloque['por_table_id'] ?? []),
            ];

            $globalCantidad += (int) ($porTotem[array_key_last($porTotem)]['cantidad_ordenes'] ?? 0);
            $globalIngreso = round($globalIngreso + (float) ($porTotem[array_key_last($porTotem)]['total_ingreso'] ?? 0), 2);

            foreach ($mediosFiltrados as $medio) {
                $tipo = WaitryMedioPagoCuentacajaSupport::tipoRepresentativoInformeZ($medio['tipo'] ?? null);
                if ($tipo === null) {
                    continue;
                }
                if (! isset($globalMedios[$tipo])) {
                    $globalMedios[$tipo] = $medio;
                } else {
                    $globalMedios[$tipo]['cantidad'] += (int) ($medio['cantidad'] ?? 0);
                    $globalMedios[$tipo]['total'] = round(
                        (float) ($globalMedios[$tipo]['total'] ?? 0) + (float) ($medio['total'] ?? 0),
                        2,
                    );
                }
            }
        }

        return [
            'por_totem' => $porTotem,
            'total_general' => [
                'cantidad_ordenes' => $globalCantidad,
                'total_ingreso' => $globalIngreso,
                'por_medio_pago' => array_values($globalMedios),
            ],
        ];
    }

    /**
     * Plantilla de carga: un bloque por tótem de la empresa y medios presentes en sistema + catálogo Waitry.
     *
     * @param  array{por_totem:list<array<string,mixed>>,total_general:array<string,mixed>}  $resumenSistema
     * @return list<array<string, mixed>>
     */
    public static function plantillaCarga(int $empresaId, array $resumenSistema): array
    {
        $porTotemSistema = [];
        foreach ($resumenSistema['por_totem'] ?? [] as $bloque) {
            $tid = (int) ($bloque['totem_id'] ?? 0);
            if ($tid > 0) {
                $porTotemSistema[$tid] = $bloque;
            }
        }

        $bloques = [];

        $totems = TotemWaitryGastronomia::query()
            ->with('ubicacion')
            ->where('empresa_id', $empresaId)
            ->where('informe_z_habilitado', true)
            ->orderBy('ubicacion_id')
            ->get();

        $huerfano = null;
        foreach ($resumenSistema['por_totem'] ?? [] as $bloque) {
            if ((int) ($bloque['totem_id'] ?? 0) <= 0 && (float) ($bloque['total_ingreso'] ?? 0) > 0.0001) {
                $huerfano = $bloque;
            }
        }

        foreach ($totems as $totem) {
            $tid = (int) $totem->id;
            $sistema = $porTotemSistema[$tid] ?? null;
            $mediosSistema = [];
            foreach ($sistema['por_medio_pago'] ?? [] as $m) {
                $tipo = WaitryMedioPagoCuentacajaSupport::normalizarTipo($m['tipo'] ?? null);
                if ($tipo === null
                    || WaitryMedioPagoCuentacajaSupport::esTipoExcluidoInformeZ($tipo)
                    || ! WaitryMedioPagoCuentacajaSupport::esTipoPagoInformeZSistema($tipo)) {
                    continue;
                }
                $claveMedio = WaitryMedioPagoCuentacajaSupport::claveMedioInformeZ($tipo, $empresaId);
                if ($claveMedio === '__excl__') {
                    continue;
                }

                if (! isset($mediosSistema[$claveMedio])) {
                    $mediosSistema[$claveMedio] = $m;
                } else {
                    $mediosSistema[$claveMedio]['cantidad'] = (int) ($mediosSistema[$claveMedio]['cantidad'] ?? 0)
                        + (int) ($m['cantidad'] ?? 0);
                    $mediosSistema[$claveMedio]['total'] = round(
                        (float) ($mediosSistema[$claveMedio]['total'] ?? 0) + (float) ($m['total'] ?? 0),
                        2,
                    );
                }
            }

            $lineas = self::lineasPlantillaDesdeMediosSistema($empresaId, $mediosSistema);
            if ($lineas === [] && $mediosSistema === []) {
                $lineas = self::lineasPlantillaDesdeTiposCatalogo($empresaId);
            }

            $bloques[] = [
                'totem_id' => $tid,
                'ubicacion_nombre' => (string) ($totem->ubicacion?->nombre ?? '—'),
                'detalle' => trim((string) ($totem->detalle ?? '')),
                'waitry_table_id' => $totem->waitry_table_id,
                'total_ingreso_sistema' => self::totalIngresoSistemaDesdeMedios($mediosSistema),
                'lineas' => $lineas,
                'aviso_sin_table_id' => $huerfano !== null && empty($totem->waitry_table_id),
            ];
        }

        if ($huerfano !== null && $bloques !== []) {
            $bloques = self::fusionarBloqueHuerfanoEnPlantilla($bloques, $huerfano, $empresaId);
        }

        $bloquesExpandidos = [];
        foreach ($bloques as $bloque) {
            $tid = (int) ($bloque['totem_id'] ?? 0);
            $totem = $totems->firstWhere('id', $tid);
            $sistema = $porTotemSistema[$tid] ?? null;
            if ($totem !== null && count($totem->waitryTableIds()) > 1) {
                foreach (self::expandirBloquePlantillaPorTableIds($empresaId, $totem, $bloque, $sistema) as $sub) {
                    $bloquesExpandidos[] = $sub;
                }
            } else {
                $bloquesExpandidos[] = $bloque;
            }
        }
        $bloques = $bloquesExpandidos;

        if ($bloques === [] && ($resumenSistema['por_totem'] ?? []) !== []) {
            foreach ($resumenSistema['por_totem'] as $sistema) {
                $mediosSistema = [];
                foreach ($sistema['por_medio_pago'] ?? [] as $m) {
                    $tipo = WaitryMedioPagoCuentacajaSupport::normalizarTipo($m['tipo'] ?? null);
                    if ($tipo === null
                        || WaitryMedioPagoCuentacajaSupport::esTipoExcluidoInformeZ($tipo)
                        || ! WaitryMedioPagoCuentacajaSupport::esTipoPagoInformeZSistema($tipo)) {
                        continue;
                    }
                    $claveMedio = WaitryMedioPagoCuentacajaSupport::claveMedioInformeZ($tipo, $empresaId);
                    if ($claveMedio === '__excl__') {
                        continue;
                    }
                    if (! isset($mediosSistema[$claveMedio])) {
                        $mediosSistema[$claveMedio] = $m;
                    } else {
                        $mediosSistema[$claveMedio]['cantidad'] = (int) ($mediosSistema[$claveMedio]['cantidad'] ?? 0)
                            + (int) ($m['cantidad'] ?? 0);
                        $mediosSistema[$claveMedio]['total'] = round(
                            (float) ($mediosSistema[$claveMedio]['total'] ?? 0) + (float) ($m['total'] ?? 0),
                            2,
                        );
                    }
                }

                $bloques[] = [
                    'totem_id' => $sistema['totem_id'] ?? null,
                    'ubicacion_nombre' => $sistema['ubicacion_nombre'] ?? 'Tótem',
                    'detalle' => $sistema['detalle'] ?? '',
                    'waitry_table_id' => $sistema['waitry_table_id'] ?? null,
                    'total_ingreso_sistema' => self::totalIngresoSistemaDesdeMedios($mediosSistema),
                    'lineas' => self::lineasPlantillaDesdeMediosSistema($empresaId, $mediosSistema),
                ];
            }
        }

        return $bloques;
    }

    /**
     * @param  list<array<string, mixed>>  $plantillaTotems  Salida de plantillaCarga con montos informe_z completados
     * @return array{ok:bool,tolerancia:float,totems:list<array<string,mixed>>}
     */
    public static function conciliar(array $plantillaTotems): array
    {
        $tol = self::toleranciaMonto();
        $resultadoTotems = [];
        $okGlobal = true;

        foreach ($plantillaTotems as $bloque) {
            $lineasConc = [];
            $okTotem = true;
            $totalSistema = 0.0;
            $totalZ = 0.0;

            foreach ($bloque['lineas'] ?? [] as $ln) {
                $mSistema = round((float) ($ln['monto_sistema'] ?? 0), 2);
                $mZ = round((float) ($ln['monto_informe_z'] ?? 0), 2);
                $diff = round($mZ - $mSistema, 2);
                $okLinea = abs($diff) <= $tol
                    || ($mSistema <= $tol && $mZ <= $tol);

                if (! $okLinea) {
                    $okTotem = false;
                    $okGlobal = false;
                }

                $totalSistema = round($totalSistema + $mSistema, 2);
                $totalZ = round($totalZ + $mZ, 2);

                $lineasConc[] = [
                    'tipo_waitry' => $ln['tipo_waitry'] ?? null,
                    'etiqueta' => $ln['etiqueta'] ?? '—',
                    'monto_sistema' => $mSistema,
                    'cantidad_sistema' => (int) ($ln['cantidad_sistema'] ?? 0),
                    'monto_informe_z' => $mZ,
                    'diferencia' => $diff,
                    'ok' => $okLinea,
                ];
            }

            $diffTotal = round($totalZ - $totalSistema, 2);
            if (abs($diffTotal) > $tol) {
                $okTotem = false;
                $okGlobal = false;
            }

            $resultadoTotems[] = [
                'totem_id' => $bloque['totem_id'] ?? null,
                'ubicacion_nombre' => $bloque['ubicacion_nombre'] ?? '',
                'detalle' => $bloque['detalle'] ?? '',
                'waitry_table_id' => $bloque['waitry_table_id'] ?? null,
                'ok' => $okTotem,
                'total_sistema' => $totalSistema,
                'total_informe_z' => $totalZ,
                'diferencia_total' => $diffTotal,
                'lineas' => $lineasConc,
            ];
        }

        return [
            'ok' => $okGlobal,
            'tolerancia' => $tol,
            'totems' => $resultadoTotems,
        ];
    }

    /**
     * Aplica montos guardados en informe_z_json sobre la plantilla.
     *
     * @param  list<array<string, mixed>>  $plantilla
     * @param  array<string, mixed>|null  $informeZ
     * @return list<array<string, mixed>>
     */
    public static function fusionarInformeZEnPlantilla(array $plantilla, ?array $informeZ, int $empresaId = 0): array
    {
        if ($informeZ === null || ($informeZ['totems'] ?? []) === []) {
            return $plantilla;
        }

        $mapZPorCuenta = [];
        $mapZPorTipo = [];
        $mapZPorTotemLegacy = [];
        foreach ($informeZ['totems'] ?? [] as $t) {
            $tid = (int) ($t['totem_id'] ?? 0);
            if ($tid <= 0) {
                continue;
            }
            $tableId = (int) ($t['waitry_table_id'] ?? 0);
            $claveBloque = self::claveBloqueInformeZ($tid, $tableId);
            $mapZPorCuenta[$claveBloque] = [];
            $mapZPorTipo[$claveBloque] = [];
            if ($tableId <= 0) {
                $mapZPorTotemLegacy[$tid] = ['cuentas' => [], 'tipos' => []];
            }
            foreach ($t['lineas'] ?? [] as $ln) {
                $monto = round((float) ($ln['monto'] ?? $ln['monto_informe_z'] ?? 0), 2);
                $ccId = (int) ($ln['cuentacaja_id'] ?? 0);
                if ($ccId > 0 && ! WaitryMedioPagoCuentacajaSupport::esCuentacajaTotem($ccId, $empresaId)) {
                    $mapZPorCuenta[$claveBloque][$ccId] = $monto;
                    if ($tableId <= 0) {
                        $mapZPorTotemLegacy[$tid]['cuentas'][$ccId] = $monto;
                    }
                }
                $tipo = WaitryMedioPagoCuentacajaSupport::tipoParaClaveMapaInformeZ($ln['tipo_waitry'] ?? null, $empresaId);
                if ($tipo !== null) {
                    $mapZPorTipo[$claveBloque][$tipo] = $monto;
                    if ($tableId <= 0) {
                        $mapZPorTotemLegacy[$tid]['tipos'][$tipo] = $monto;
                    }
                }
            }
        }

        foreach ($plantilla as &$bloque) {
            $tid = (int) ($bloque['totem_id'] ?? 0);
            $tableId = (int) ($bloque['waitry_table_id'] ?? 0);
            $claveBloque = self::claveBloqueInformeZ($tid, $tableId);
            $zCuentas = $mapZPorCuenta[$claveBloque] ?? [];
            $zTipos = $mapZPorTipo[$claveBloque] ?? [];
            if ($zCuentas === [] && $zTipos === [] && isset($mapZPorTotemLegacy[$tid])) {
                $zCuentas = $mapZPorTotemLegacy[$tid]['cuentas'];
                $zTipos = $mapZPorTotemLegacy[$tid]['tipos'];
            }
            foreach ($bloque['lineas'] as &$ln) {
                $ccId = (int) ($ln['cuentacaja_id'] ?? 0);
                if ($ccId > 0 && array_key_exists($ccId, $zCuentas)) {
                    $ln['monto_informe_z'] = $zCuentas[$ccId];

                    continue;
                }
                $tipo = WaitryMedioPagoCuentacajaSupport::tipoParaClaveMapaInformeZ($ln['tipo_waitry'] ?? null, $empresaId);
                if ($tipo !== null && array_key_exists($tipo, $zTipos)) {
                    $ln['monto_informe_z'] = $zTipos[$tipo];
                }
            }
            unset($ln);
        }
        unset($bloque);

        return $plantilla;
    }

    /**
     * @param  list<array<string, mixed>>  $porTableId
     * @return list<array<string, mixed>>
     */
    private static function filtrarPorTableIdInformeZ(array $porTableId): array
    {
        $out = [];
        foreach ($porTableId as $bloque) {
            if (! is_array($bloque)) {
                continue;
            }
            $mediosFiltrados = [];
            $totalBloque = 0.0;
            $cantBloque = 0;
            foreach ($bloque['por_medio_pago'] ?? [] as $m) {
                if (! is_array($m)) {
                    continue;
                }
                $tipo = WaitryMedioPagoCuentacajaSupport::normalizarTipo($m['tipo'] ?? null);
                if (! WaitryMedioPagoCuentacajaSupport::esTipoPagoInformeZSistema($tipo)) {
                    continue;
                }
                $mediosFiltrados[] = $m;
                $totalBloque = round($totalBloque + (float) ($m['total'] ?? 0), 2);
                $cantBloque += (int) ($m['cantidad'] ?? 0);
            }
            if ($mediosFiltrados === [] && (float) ($bloque['total_ingreso'] ?? 0) <= 0.0001) {
                continue;
            }
            $out[] = [
                ...$bloque,
                'cantidad_ordenes' => $cantBloque > 0 ? $cantBloque : (int) ($bloque['cantidad_ordenes'] ?? 0),
                'total_ingreso' => $totalBloque > 0.0001 ? $totalBloque : round((float) ($bloque['total_ingreso'] ?? 0), 2),
                'por_medio_pago' => $mediosFiltrados,
            ];
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>|null  $sistemaBloque
     * @return list<array<string, mixed>>
     */
    private static function expandirBloquePlantillaPorTableIds(
        int $empresaId,
        TotemWaitryGastronomia $totem,
        array $bloqueAgregado,
        ?array $sistemaBloque,
    ): array {
        $porTable = [];
        foreach ($sistemaBloque['por_table_id'] ?? [] as $tbl) {
            if (! is_array($tbl)) {
                continue;
            }
            $tableId = (int) ($tbl['waitry_table_id'] ?? 0);
            if ($tableId > 0) {
                $porTable[$tableId] = $tbl;
            }
        }

        $bloques = [];
        foreach ($totem->waitryTableIds() as $tableId) {
            $tbl = $porTable[$tableId] ?? null;
            $mediosSistema = self::mediosInformeZDesdePorTable($empresaId, $tbl);
            $lineas = self::lineasPlantillaDesdeMediosSistema($empresaId, $mediosSistema);
            if ($lineas === [] && $mediosSistema === []) {
                $lineas = self::lineasPlantillaDesdeTiposCatalogo($empresaId);
            }

            $bloques[] = [
                'totem_id' => (int) $totem->id,
                'ubicacion_nombre' => (string) ($bloqueAgregado['ubicacion_nombre'] ?? $totem->ubicacion?->nombre ?? '—'),
                'detalle' => trim((string) ($bloqueAgregado['detalle'] ?? $totem->detalle ?? '')),
                'waitry_table_id' => $tableId,
                'waitry_table_name' => $tbl['waitry_table_name'] ?? null,
                'total_ingreso_sistema' => self::totalIngresoSistemaDesdeMedios($mediosSistema),
                'lineas' => $lineas,
                'aviso_sin_table_id' => (bool) ($bloqueAgregado['aviso_sin_table_id'] ?? false),
            ];
        }

        return $bloques !== [] ? $bloques : [$bloqueAgregado];
    }

    /**
     * @param  array<string, mixed>|null  $tbl
     * @return array<string, array<string, mixed>>
     */
    private static function mediosInformeZDesdePorTable(int $empresaId, ?array $tbl): array
    {
        if ($tbl === null) {
            return [];
        }

        $mediosSistema = [];
        foreach ($tbl['por_medio_pago'] ?? [] as $m) {
            if (! is_array($m)) {
                continue;
            }
            $tipo = WaitryMedioPagoCuentacajaSupport::normalizarTipo($m['tipo'] ?? null);
            if ($tipo === null
                || WaitryMedioPagoCuentacajaSupport::esTipoExcluidoInformeZ($tipo)
                || ! WaitryMedioPagoCuentacajaSupport::esTipoPagoInformeZSistema($tipo)) {
                continue;
            }
            $claveMedio = WaitryMedioPagoCuentacajaSupport::claveMedioInformeZ($tipo, $empresaId);
            if ($claveMedio === '__excl__') {
                continue;
            }
            if (! isset($mediosSistema[$claveMedio])) {
                $mediosSistema[$claveMedio] = $m;
            } else {
                $mediosSistema[$claveMedio]['cantidad'] = (int) ($mediosSistema[$claveMedio]['cantidad'] ?? 0)
                    + (int) ($m['cantidad'] ?? 0);
                $mediosSistema[$claveMedio]['total'] = round(
                    (float) ($mediosSistema[$claveMedio]['total'] ?? 0) + (float) ($m['total'] ?? 0),
                    2,
                );
            }
        }

        return $mediosSistema;
    }

    private static function claveBloqueInformeZ(int $totemId, int $tableId): string
    {
        return $totemId.':'.$tableId;
    }

    /**
     * @param  array<string, array<string, mixed>>  $mediosSistema
     */
    private static function totalIngresoSistemaDesdeMedios(array $mediosSistema): float
    {
        $total = 0.0;
        foreach ($mediosSistema as $m) {
            $total = round($total + (float) ($m['total'] ?? 0), 2);
        }

        return $total;
    }

    private static function lineaPlantillaDesdeTipo(int $empresaId, string $tipo, ?array $medioSistema): array
    {
        $tipoCanon = WaitryMedioPagoCuentacajaSupport::tipoRepresentativoInformeZ($tipo) ?? $tipo;
        $cuenta = WaitryMedioPagoCuentacajaSupport::cuentaParaTipoInformeZ($tipoCanon, $empresaId);
        $etiquetaTipo = WaitryMedioPagoCuentacajaSupport::etiquetaTipo($tipoCanon);
        $etiquetaCuenta = $cuenta !== null
            ? trim(($cuenta['codigo'] ?? '').' — '.($cuenta['nombre'] ?? ''))
            : $etiquetaTipo;

        return [
            'tipo_waitry' => $tipoCanon,
            'etiqueta' => (string) ($medioSistema['etiqueta'] ?? $etiquetaCuenta),
            'cuentacaja_id' => $cuenta['id'] ?? null,
            'cuentacaja_codigo' => $cuenta['codigo'] ?? '',
            'cuentacaja_nombre' => $cuenta['nombre'] ?? '',
            'moneda_abreviatura' => $cuenta['moneda_abreviatura'] ?? 'ARS',
            'monto_sistema' => round((float) ($medioSistema['total'] ?? 0), 2),
            'cantidad_sistema' => (int) ($medioSistema['cantidad'] ?? 0),
            'monto_informe_z' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $linea
     */
    private static function lineaInformeZValida(array $linea, int $empresaId): bool
    {
        $ccId = (int) ($linea['cuentacaja_id'] ?? 0);
        if ($ccId > 0 && WaitryMedioPagoCuentacajaSupport::esCuentacajaTotem($ccId, $empresaId)) {
            return false;
        }

        $tipo = WaitryMedioPagoCuentacajaSupport::normalizarTipo($linea['tipo_waitry'] ?? null);

        return $tipo !== null
            && ! WaitryMedioPagoCuentacajaSupport::esTipoExcluidoInformeZ($tipo)
            && WaitryMedioPagoCuentacajaSupport::esTipoPagoInformeZSistema($tipo);
    }

    /**
     * @param  array<string, array<string, mixed>>  $mediosSistema  claveMedioInformeZ => medio resumen
     * @return list<array<string, mixed>>
     */
    private static function lineasPlantillaDesdeMediosSistema(int $empresaId, array $mediosSistema): array
    {
        $lineasPorClave = [];

        foreach ($mediosSistema as $m) {
            $tipoRaw = WaitryMedioPagoCuentacajaSupport::normalizarTipo($m['tipo'] ?? null);
            if (! WaitryMedioPagoCuentacajaSupport::esTipoPagoInformeZSistema($tipoRaw)) {
                continue;
            }
            $linea = self::lineaPlantillaDesdeTipo($empresaId, $tipoRaw, $m);
            if (! self::lineaInformeZValida($linea, $empresaId)) {
                continue;
            }
            $lineasPorClave = self::fusionarLineaEnMapa($lineasPorClave, $linea, $empresaId);
        }

        $lineas = array_values($lineasPorClave);
        usort($lineas, fn (array $a, array $b) => strcmp((string) ($a['etiqueta'] ?? ''), (string) ($b['etiqueta'] ?? '')));

        return $lineas;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function lineasPlantillaDesdeTiposCatalogo(int $empresaId): array
    {
        $lineasPorClave = [];

        foreach (self::tiposRepresentativosCatalogoInformeZ($empresaId) as $tipo) {
            $linea = self::lineaPlantillaDesdeTipo($empresaId, $tipo, null);
            if (! self::lineaInformeZValida($linea, $empresaId)) {
                continue;
            }
            $lineasPorClave = self::fusionarLineaEnMapa($lineasPorClave, $linea, $empresaId);
        }

        return array_values($lineasPorClave);
    }

    /**
     * Un tipo por cuenta de caja (Totalcoin + credit_card → una sola fila).
     *
     * @param  array<string, string>  $tiposCatalogo
     * @return list<string>
     */
    private static function tiposRepresentativosCatalogo(int $empresaId, array $tiposCatalogo): array
    {
        $porCuenta = [];

        foreach (array_keys($tiposCatalogo) as $tipoRaw) {
            $tipo = WaitryMedioPagoCuentacajaSupport::tipoRepresentativoInformeZ($tipoRaw);
            if ($tipo === null) {
                continue;
            }
            $ccId = WaitryMedioPagoCuentacajaSupport::cuentacajaIdPorTipo($tipo, $empresaId) ?? 0;
            if ($ccId <= 0) {
                continue;
            }
            $porCuenta[$ccId] = $tipo;
        }

        $tipos = array_values($porCuenta);
        sort($tipos);

        return $tipos;
    }

    /**
     * @param  array<string, array<string, mixed>>  $lineasPorClave
     * @return array<string, array<string, mixed>>
     */
    private static function fusionarLineaEnMapa(array $lineasPorClave, array $linea, int $empresaId): array
    {
        $ccId = (int) ($linea['cuentacaja_id'] ?? 0);
        $clave = $ccId > 0
            ? 'cc:'.$ccId
            : WaitryMedioPagoCuentacajaSupport::claveMedioInformeZ($linea['tipo_waitry'] ?? null, $empresaId);
        if ($clave === '__excl__') {
            return $lineasPorClave;
        }

        if (! isset($lineasPorClave[$clave])) {
            $lineasPorClave[$clave] = $linea;

            return $lineasPorClave;
        }

        $lineasPorClave[$clave]['tipo_waitry'] = WaitryMedioPagoCuentacajaSupport::tipoRepresentativoInformeZ(
            $lineasPorClave[$clave]['tipo_waitry'] ?? null,
        ) ?? $lineasPorClave[$clave]['tipo_waitry'];

        $lineasPorClave[$clave]['monto_sistema'] = round(
            (float) ($lineasPorClave[$clave]['monto_sistema'] ?? 0) + (float) ($linea['monto_sistema'] ?? 0),
            2,
        );
        $lineasPorClave[$clave]['cantidad_sistema'] = (int) ($lineasPorClave[$clave]['cantidad_sistema'] ?? 0)
            + (int) ($linea['cantidad_sistema'] ?? 0);

        return $lineasPorClave;
    }

    /**
     * Reparte ingreso huérfano (totem_id null) entre bloques de plantilla cuando aún quedó sin asignar.
     *
     * @param  list<array<string, mixed>>  $bloques
     * @param  array<string, mixed>  $huerfano
     * @return list<array<string, mixed>>
     */
    private static function fusionarBloqueHuerfanoEnPlantilla(array $bloques, array $huerfano, int $empresaId): array
    {
        $n = count($bloques);
        if ($n <= 0) {
            return $bloques;
        }

        foreach ($huerfano['por_medio_pago'] ?? [] as $medio) {
            $tipo = WaitryMedioPagoCuentacajaSupport::normalizarTipo($medio['tipo'] ?? null);
            if ($tipo === null
                || WaitryMedioPagoCuentacajaSupport::esTipoExcluidoInformeZ($tipo)
                || ! WaitryMedioPagoCuentacajaSupport::esTipoPagoInformeZSistema($tipo)) {
                continue;
            }
            $claveMedio = WaitryMedioPagoCuentacajaSupport::claveMedioInformeZ($tipo, $empresaId);
            if ($claveMedio === '__excl__') {
                continue;
            }
            $tipoCanon = WaitryMedioPagoCuentacajaSupport::tipoRepresentativoInformeZ($tipo) ?? $tipo;
            $total = round((float) ($medio['total'] ?? 0), 2);
            $cant = (int) ($medio['cantidad'] ?? 0);
            $parte = round($total / $n, 2);
            $parteCant = (int) floor($cant / $n);

            foreach ($bloques as $i => &$bloque) {
                $lineasPorClave = [];
                foreach ($bloque['lineas'] ?? [] as $ln) {
                    $ccId = (int) ($ln['cuentacaja_id'] ?? 0);
                    $clave = $ccId > 0
                        ? 'cc:'.$ccId
                        : WaitryMedioPagoCuentacajaSupport::claveMedioInformeZ($ln['tipo_waitry'] ?? null, $empresaId);
                    if ($clave !== '__excl__') {
                        $lineasPorClave[$clave] = $ln;
                    }
                }

                $montoLinea = $parte;
                if ($i === $n - 1) {
                    $montoLinea = round($total - $parte * ($n - 1), 2);
                }
                $cantLinea = $parteCant;
                if ($i === $n - 1) {
                    $cantLinea = $cant - $parteCant * ($n - 1);
                }

                $medioParcial = [
                    'tipo' => $tipoCanon,
                    'etiqueta' => $medio['etiqueta'] ?? WaitryMedioPagoCuentacajaSupport::etiquetaTipo($tipoCanon),
                    'cantidad' => $cantLinea,
                    'total' => $montoLinea,
                ];
                $nueva = self::lineaPlantillaDesdeTipo($empresaId, $tipoCanon, $medioParcial);
                $lineasPorClave = self::fusionarLineaEnMapa($lineasPorClave, $nueva, $empresaId);
                $bloque['lineas'] = array_values($lineasPorClave);
                $bloque['total_ingreso_sistema'] = round(
                    (float) ($bloque['total_ingreso_sistema'] ?? 0) + $montoLinea,
                    2,
                );
            }
            unset($bloque);
        }

        return $bloques;
    }

    /**
     * Un tipo por cuenta de caja para filas vacías del Informe Z (QR + MP + Posnet fusionados si comparten cuenta).
     *
     * @return list<string>
     */
    private static function tiposRepresentativosCatalogoInformeZ(int $empresaId): array
    {
        return self::tiposRepresentativosCatalogo($empresaId, WaitryMedioPagoCuentacajaSupport::mapaTipoCuentacaja());
    }
}
