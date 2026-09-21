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
     * La suma de las facturas asignadas a una COM no puede superar su provisión.
     *
     * Complementa la unicidad: cuando el legajo permite compartir COM (OC anticipada /
     * contrato) este es el único freno, y cuando no la permite atrapa igual la COM
     * equivocada (importe que no corresponde a esa recepción).
     *
     * El importe de cada factura es el comparable con la provisión (neto gravado en letra A,
     * total en B/C o monotributo). Las facturas sin ese importe (precarga en cero) suman 0:
     * no se pueden validar, pero tampoco deben bloquear al resto.
     *
     * @param  array<int|string, list<int>>  $asignacionesPorPrecarga  precarga_id|cp-N => recepcion_ids
     * @param  array<int, float>  $provisionPorCom  recepcion_id => provisión (0/ausente = no validable)
     * @param  array<int|string, float>  $importePorFactura  precarga_id|cp-N => importe de la factura
     * @param  array<int, string>  $etiquetasCom  recepcion_id => etiqueta visible (opcional)
     */
    public static function mensajeExcesoProvisionPorCom(
        array $asignacionesPorPrecarga,
        array $provisionPorCom,
        array $importePorFactura,
        array $etiquetasCom = [],
        float $toleranciaPct = 0.0,
    ): ?string {
        $asignadoPorCom = [];
        foreach ($asignacionesPorPrecarga as $precargaId => $recepcionIds) {
            $clave = is_numeric($precargaId) ? (int) $precargaId : trim((string) $precargaId);
            if ($clave === 0 || $clave === '0' || $clave === '') {
                continue;
            }
            $importe = abs((float) ($importePorFactura[$clave] ?? $importePorFactura[(string) $clave] ?? 0));
            foreach ((array) $recepcionIds as $recepcionId) {
                $rid = (int) $recepcionId;
                if ($rid <= 0) {
                    continue;
                }
                $asignadoPorCom[$rid] ??= ['importe' => 0.0, 'facturas' => 0];
                $asignadoPorCom[$rid]['importe'] += $importe;
                $asignadoPorCom[$rid]['facturas']++;
            }
        }

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
            if (! ComprobanteProveedorToleranciaImporteSupport::excedeTolerancia($asignado, $provision, $toleranciaPct)) {
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
     */
    public static function mensajeComDuplicadaEntreFacturas(
        array $asignacionesPorPrecarga,
        array $etiquetasCom = [],
    ): ?string {
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
