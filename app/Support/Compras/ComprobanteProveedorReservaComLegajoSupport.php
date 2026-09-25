<?php

namespace App\Support\Compras;

/**
 * Evita que una factura del legajo consuma COM que deben quedar para otras facturas pendientes.
 *
 * Caso típico: 2 COM del mismo importe y 2 FC; si CxP asigna ambas a la primera,
 * la segunda queda sin COM disponible.
 */
final class ComprobanteProveedorReservaComLegajoSupport
{
    /**
     * Más de esto por factura suele ser malla del import Anita (todas las COM del legajo),
     * no una asignación operativa de varios remitos a un solo comprobante.
     */
    private const MAX_COM_POR_FACTURA_OPERATIVA = 3;

    /**
     * Si hay más de una COM seleccionada y alguna sola ya cubre el importe de la factura,
     * no permitir el exceso (esas COM extras son para otras facturas).
     *
     * @param  list<array{id: int, numerorecepcion?: int|string|null, provision: float}>  $comsSeleccionadas
     */
    public static function mensajeExcesoComQueCubrenSolas(
        float $importeFactura,
        array $comsSeleccionadas,
        float $toleranciaPct,
    ): ?string {
        if (count($comsSeleccionadas) <= 1) {
            return null;
        }

        foreach ($comsSeleccionadas as $com) {
            $provision = (float) ($com['provision'] ?? 0);
            if ($provision <= 0) {
                continue;
            }
            $cubreSola = ComprobanteProveedorImporteComparacionComSupport::coinciden($importeFactura, $provision)
                || ! ComprobanteProveedorToleranciaImporteSupport::excedeTolerancia(
                    $importeFactura,
                    $provision,
                    $toleranciaPct
                );
            if (! $cubreSola) {
                continue;
            }

            $nro = $com['numerorecepcion'] ?? $com['id'] ?? '?';

            return 'El importe de la factura coincide con una sola COM (#'.$nro.'). '
                .'No asigne más de una: reserve las otras para facturas pendientes del legajo.';
        }

        return null;
    }

    /**
     * Tras asignar las COM elegidas, deben quedar suficientes COM libres para el resto
     * de facturas pendientes que exigen recepción.
     *
     * @param  list<int>  $comIdsDisponiblesAntes  COM aún no facturadas (incluye las elegidas)
     * @param  list<int>  $comIdsSeleccionados
     */
    public static function mensajeInsuficienteParaOtrasPendientes(
        int $facturasPendientesQueExigenCom,
        bool $documentoActualEstaEntrePendientes,
        array $comIdsDisponiblesAntes,
        array $comIdsSeleccionados,
    ): ?string {
        if ($facturasPendientesQueExigenCom <= 0) {
            return null;
        }

        $otrosPendientes = $documentoActualEstaEntrePendientes
            ? max(0, $facturasPendientesQueExigenCom - 1)
            : $facturasPendientesQueExigenCom;

        if ($otrosPendientes <= 0) {
            return null;
        }

        $disponibles = array_values(array_unique(array_map('intval', $comIdsDisponiblesAntes)));
        $seleccionados = array_values(array_unique(array_map('intval', $comIdsSeleccionados)));
        $quedan = count(array_diff($disponibles, $seleccionados));

        if ($quedan >= $otrosPendientes) {
            return null;
        }

        return sprintf(
            'Está asignando %d COM y dejaría solo %d libre(s), pero hay %d factura(s) pendiente(s) en el legajo que también requieren COM. '
            .'Asigne solo las COM necesarias para esta factura.',
            count($seleccionados),
            $quedan,
            $otrosPendientes
        );
    }

    /**
     * No consumir una COM ya reservada en bandeja para otra precarga pendiente.
     *
     * @param  list<int>  $comIdsSeleccionados
     * @param  array<int, list<int>>  $reservasPorPrecargaPendiente  precarga_id => recepcion_ids
     */
    public static function mensajeConflictoReservaBandeja(
        array $comIdsSeleccionados,
        array $reservasPorPrecargaPendiente,
        ?int $precargaIdActual,
    ): ?string {
        $seleccionados = array_fill_keys(
            array_values(array_unique(array_map('intval', $comIdsSeleccionados))),
            true
        );
        if ($seleccionados === []) {
            return null;
        }

        foreach ($reservasPorPrecargaPendiente as $precargaId => $recepcionIds) {
            if ($precargaIdActual !== null && (int) $precargaId === (int) $precargaIdActual) {
                continue;
            }
            foreach ($recepcionIds as $recepcionId) {
                $rid = (int) $recepcionId;
                if ($rid > 0 && isset($seleccionados[$rid])) {
                    return 'La COM #'.$rid.' ya está asignada en la bandeja a otra factura pendiente del legajo (precarga #'.$precargaId.'). '
                        .'Elija otra recepción para este comprobante.';
                }
            }
        }

        return null;
    }

    /**
     * Asignaciones del legajo que comparten alguna de las COM tocadas en este guardado.
     * Si no se toca ninguna COM (p.ej. NC/ND sin recepción), no hay nada que validar.
     *
     * @param  array<int|string, list<int>>  $asignaciones
     * @param  list<int>  $comIdsTocadas
     * @return array<int|string, list<int>>
     */
    public static function asignacionesQueTocanComs(array $asignaciones, array $comIdsTocadas): array
    {
        $tocadas = [];
        foreach ($comIdsTocadas as $comId) {
            $rid = (int) $comId;
            if ($rid > 0) {
                $tocadas[$rid] = true;
            }
        }
        if ($tocadas === []) {
            return [];
        }

        $out = [];
        foreach ($asignaciones as $clave => $recepcionIds) {
            $rids = [];
            $toca = false;
            foreach ((array) $recepcionIds as $recepcionId) {
                $rid = (int) $recepcionId;
                if ($rid <= 0) {
                    continue;
                }
                $rids[$rid] = $rid;
                if (isset($tocadas[$rid])) {
                    $toca = true;
                }
            }
            if ($toca) {
                $out[$clave] = array_values($rids);
            }
        }

        return $out;
    }

    /**
     * Detecta factura declarada en ME con importes que parecen moneda local
     * (importe ≈ provisión_ME × cotización), típico de heredar moneda de la OC
     * cuando el PDF vino en pesos.
     *
     * @param  array<int|string, list<int>>  $asignacionesPorPrecarga
     * @param  array<int, float>  $provisionPorCom  ya en moneda de la factura (o ME si misma moneda)
     * @param  array<int|string, float>  $importePorFactura
     * @param  array<int|string, array{moneda_id: int, cotizacion: float, fecha?: mixed}>  $contextoMoneda
     * @param  array<int, string>  $etiquetasCom
     */
    public static function mensajeMonedaIncoherenteFacturaVsCom(
        array $asignacionesPorPrecarga,
        array $provisionPorCom,
        array $importePorFactura,
        array $contextoMoneda,
        array $etiquetasCom = [],
    ): ?string {
        foreach ($asignacionesPorPrecarga as $precargaId => $recepcionIds) {
            $clave = is_numeric($precargaId) ? (int) $precargaId : trim((string) $precargaId);
            if ($clave === 0 || $clave === '0' || $clave === '') {
                continue;
            }
            $importe = abs((float) ($importePorFactura[$clave] ?? $importePorFactura[(string) $clave] ?? 0));
            if ($importe <= 0.00001) {
                continue;
            }

            $ctx = $contextoMoneda[$clave] ?? $contextoMoneda[(string) $clave] ?? null;
            if ($ctx === null) {
                continue;
            }
            $monedaId = (int) ($ctx['moneda_id'] ?? 1);
            // Sin MonedaMotor aquí: este support se testea sin bootstrap Laravel (config()).
            $cotizacion = (float) ($ctx['cotizacion'] ?? 0);
            if ($monedaId <= 1 || $cotizacion < 1.0001) {
                continue;
            }

            $rids = [];
            foreach ((array) $recepcionIds as $recepcionId) {
                $rid = (int) $recepcionId;
                if ($rid > 0) {
                    $rids[] = $rid;
                }
            }
            if ($rids === [] || count($rids) > self::MAX_COM_POR_FACTURA_OPERATIVA) {
                continue;
            }

            $sumaProvision = 0.0;
            foreach ($rids as $rid) {
                $sumaProvision += abs((float) ($provisionPorCom[$rid] ?? 0));
            }
            if ($sumaProvision <= 0.00001) {
                continue;
            }

            // Misma moneda ME: la provisión no se convirtió; el importe en "ME" ≈ provisión × cotización.
            $factor = $importe / $sumaProvision;
            $umbral = $cotizacion / 2;
            if ($factor < $umbral) {
                continue;
            }

            $etiqueta = trim((string) ($etiquetasCom[$rids[0]] ?? ''));
            $comLabel = $etiqueta !== '' ? $etiqueta : '#'.$rids[0];
            $provisionEnPesos = round($sumaProvision * $cotizacion, 2);

            return sprintf(
                'La factura está declarada en moneda extranjera (cotización %s) con importe %s, '
                .'pero la COM %s provisionó %s en esa moneda (≈ %s en pesos = provisión × cotización). '
                .'Si la factura está en pesos, cambie la moneda a pesos antes de asignar la COM; '
                .'si está en dólares, el neto debería ser %s (no %s).',
                number_format($cotizacion, 2, ',', '.'),
                number_format($importe, 2, ',', '.'),
                $comLabel,
                number_format($sumaProvision, 2, ',', '.'),
                number_format($provisionEnPesos, 2, ',', '.'),
                number_format($sumaProvision, 2, ',', '.'),
                number_format($importe, 2, ',', '.'),
            );
        }

        return null;
    }

    /**
     * La suma asignada a cada COM no puede superar su provisión.
     *
     * Dos sentidos que no se pueden mezclar a ciegas:
     * - 1 COM ← N facturas (anticipo / contrato): se acumula el neto de cada factura
     *   sobre esa recepción.
     * - N COM ← 1 factura (varios remitos en un solo comprobante): el neto se reparte
     *   entre las COM a prorrata de su provisión. Antes se cargaba el neto entero a cada
     *   una y la COM chica disparaba un falso exceso (OC 223753).
     *
     * El importe de cada factura es el comparable con la provisión (neto gravado en letra A,
     * total en B/C o monotributo). Las facturas sin ese importe (precarga en cero) suman 0:
     * no se pueden validar, pero tampoco deben bloquear al resto.
     *
     * Mallas N:M del import Anita (cada factura vinculada a docenas de COM del legajo)
     * no son asignaciones operativas: se ignoran para no bloquear NC/altas nuevas.
     *
     * @param  array<int|string, list<int>>  $asignacionesPorPrecarga  precarga_id|cp-N => recepcion_ids
     * @param  array<int, float>  $provisionPorCom  recepcion_id => provisión (0/ausente = no validable)
     * @param  array<int|string, float>  $importePorFactura  precarga_id|cp-N => importe de la factura
     * @param  array<int, string>  $etiquetasCom  recepcion_id => etiqueta visible (opcional)
     * @param  float  $cupoNcDisponible  neto comparable de NC del legajo que cubre exceso FC−COM
     */
    public static function mensajeExcesoProvisionPorCom(
        array $asignacionesPorPrecarga,
        array $provisionPorCom,
        array $importePorFactura,
        array $etiquetasCom = [],
        float $toleranciaPct = 0.0,
        float $cupoNcDisponible = 0.0,
    ): ?string {
        $asignadoPorCom = [];
        foreach ($asignacionesPorPrecarga as $precargaId => $recepcionIds) {
            $clave = is_numeric($precargaId) ? (int) $precargaId : trim((string) $precargaId);
            if ($clave === 0 || $clave === '0' || $clave === '') {
                continue;
            }
            $importe = abs((float) ($importePorFactura[$clave] ?? $importePorFactura[(string) $clave] ?? 0));
            if ($importe <= 0.00001) {
                continue;
            }

            $rids = [];
            foreach ((array) $recepcionIds as $recepcionId) {
                $rid = (int) $recepcionId;
                if ($rid > 0) {
                    $rids[$rid] = $rid;
                }
            }
            $rids = array_values($rids);
            if ($rids === []) {
                continue;
            }
            // Import Anita: factura↔todas las COM del legajo. No es un remito real.
            if (count($rids) > self::MAX_COM_POR_FACTURA_OPERATIVA) {
                continue;
            }

            if (count($rids) === 1) {
                $rid = $rids[0];
                $asignadoPorCom[$rid] ??= ['importe' => 0.0, 'facturas' => 0];
                $asignadoPorCom[$rid]['importe'] += $importe;
                $asignadoPorCom[$rid]['facturas']++;

                continue;
            }

            $provisiones = [];
            $sumaProvision = 0.0;
            foreach ($rids as $rid) {
                $provision = abs((float) ($provisionPorCom[$rid] ?? 0));
                $provisiones[$rid] = $provision;
                $sumaProvision += $provision;
            }
            if ($sumaProvision <= 0.00001) {
                // Ninguna COM con provisión conocida: no validable.
                continue;
            }

            // Primer freno claro: el neto de la factura vs la suma de las COM elegidas.
            // NC del legajo puede cubrir el exceso (misma regla que el control por COM).
            if ($importe > $sumaProvision
                && ! ComprobanteProveedorCupoNcLegajoSupport::dentroDeToleranciaTrasCupoNc(
                    $importe,
                    $sumaProvision,
                    $cupoNcDisponible,
                    $toleranciaPct
                )
            ) {
                return sprintf(
                    'La factura tiene importe %s y las %d COM asignadas suman provisión %s. '
                    .'Revise si corresponden a esta recepción.',
                    number_format($importe, 2, ',', '.'),
                    count($rids),
                    number_format($sumaProvision, 2, ',', '.'),
                );
            }

            // Reparto a prorrata para acumular si esa COM también recibe otras facturas.
            $repartido = 0.0;
            $ultimo = count($rids) - 1;
            foreach ($rids as $i => $rid) {
                $share = $i === $ultimo
                    ? round($importe - $repartido, 2)
                    : round($importe * ($provisiones[$rid] / $sumaProvision), 2);
                $repartido = round($repartido + $share, 2);
                $asignadoPorCom[$rid] ??= ['importe' => 0.0, 'facturas' => 0];
                $asignadoPorCom[$rid]['importe'] += $share;
                $asignadoPorCom[$rid]['facturas']++;
            }
        }

        $excesosPorCom = [];
        foreach ($asignadoPorCom as $rid => $acumulado) {
            $provision = abs((float) ($provisionPorCom[$rid] ?? 0));
            if ($provision <= 0.00001) {
                continue;
            }
            $asignado = (float) $acumulado['importe'];
            $exceso = ComprobanteProveedorCupoNcLegajoSupport::excesoSobreProvision($asignado, $provision);
            if ($exceso <= 0.00001) {
                continue;
            }
            if (! ComprobanteProveedorToleranciaImporteSupport::excedeTolerancia($asignado, $provision, $toleranciaPct)) {
                continue;
            }
            $excesosPorCom[$rid] = $exceso;
        }

        $cupoAplicadoPorCom = ComprobanteProveedorCupoNcLegajoSupport::consumirCupoContraExcesos(
            $cupoNcDisponible,
            $excesosPorCom,
        )['efectivos_reduccion'];

        foreach ($asignadoPorCom as $rid => $acumulado) {
            $provision = abs((float) ($provisionPorCom[$rid] ?? 0));
            if ($provision <= 0.00001) {
                // Sin provisión conocida (COM histórica sin asiento ni líneas): no validable.
                continue;
            }
            $asignado = (float) $acumulado['importe'];
            if ($asignado <= 0.00001) {
                continue;
            }
            if ($asignado <= $provision) {
                continue;
            }
            $efectivo = ComprobanteProveedorCupoNcLegajoSupport::asignadoEfectivoTrasCupo(
                $asignado,
                (float) ($cupoAplicadoPorCom[$rid] ?? 0),
            );
            if (! ComprobanteProveedorToleranciaImporteSupport::excedeTolerancia($efectivo, $provision, $toleranciaPct)) {
                continue;
            }

            $etiqueta = trim((string) ($etiquetasCom[$rid] ?? ''));
            $comLabel = $etiqueta !== '' ? $etiqueta : '#'.$rid;

            return sprintf(
                'La COM %s tiene provisión de %s y le está asignando %s en %d factura(s). '
                .'Revise si la factura corresponde a esta recepción.',
                $comLabel,
                number_format($provision, 2, ',', '.'),
                number_format($asignado, 2, ',', '.'),
                (int) $acumulado['facturas'],
            );
        }

        return null;
    }

    /**
     * Una misma COM no puede quedar asignada a dos facturas del legajo.
     *
     * @param  array<int|string, list<int>>  $asignacionesPorPrecarga  precarga_id|cp-N => recepcion_ids
     * @param  array<int, string>  $etiquetasCom  recepcion_id => etiqueta visible (opcional)
     * @param  list<int>|null  $soloComIds  si se indica, solo se controlan esas COM (las tocadas ahora)
     */
    public static function mensajeComDuplicadaEntreFacturas(
        array $asignacionesPorPrecarga,
        array $etiquetasCom = [],
        ?array $soloComIds = null,
    ): ?string {
        $filtro = null;
        if ($soloComIds !== null) {
            $filtro = [];
            foreach ($soloComIds as $comId) {
                $rid = (int) $comId;
                if ($rid > 0) {
                    $filtro[$rid] = true;
                }
            }
            if ($filtro === []) {
                return null;
            }
        }

        $duenoPorCom = [];
        foreach ($asignacionesPorPrecarga as $precargaId => $recepcionIds) {
            $dueno = is_numeric($precargaId) ? (int) $precargaId : trim((string) $precargaId);
            if ($dueno === 0 || $dueno === '0' || $dueno === '') {
                continue;
            }
            foreach ((array) $recepcionIds as $recepcionId) {
                $rid = (int) $recepcionId;
                if ($rid <= 0) {
                    continue;
                }
                if ($filtro !== null && ! isset($filtro[$rid])) {
                    continue;
                }
                if (isset($duenoPorCom[$rid]) && $duenoPorCom[$rid] !== $dueno) {
                    $etiqueta = trim((string) ($etiquetasCom[$rid] ?? ''));
                    $comLabel = $etiqueta !== '' ? $etiqueta : '#'.$rid;

                    return 'La COM '.$comLabel.' ya está asignada a otra factura del legajo. '
                        .'Cada recepción solo puede vincularse a un comprobante.';
                }
                $duenoPorCom[$rid] = $dueno;
            }
        }

        return null;
    }
}
