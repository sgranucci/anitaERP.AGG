<?php

namespace App\Services\Ventas\Gastronomia;

use App\Models\Ventas\CierreTotemJornadaGastronomia;
use App\Models\Ventas\JornadaGastronomia;
use App\Support\Ventas\Waitry\WaitryInformeZConciliacionSupport;
use App\Support\Ventas\Waitry\WaitryMedioPagoCuentacajaSupport;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

final class GastronomiaCierreTotemInformeZService
{
    /**
     * @return array<string, mixed>
     */
    public function datosParaConciliacion(int $jornadaId): array
    {
        $cierre = $this->cierrePorJornada($jornadaId);
        $detalle = is_array($cierre->detalle_json) ? $cierre->detalle_json : [];
        $resumen = WaitryInformeZConciliacionSupport::resumenSistemaDesdeDetalleCierre($detalle);

        $plantilla = WaitryInformeZConciliacionSupport::plantillaCarga(
            (int) $cierre->empresa_id,
            $resumen,
        );
        $informeZ = is_array($cierre->informe_z_json) ? $cierre->informe_z_json : null;
        $plantilla = WaitryInformeZConciliacionSupport::fusionarInformeZEnPlantilla(
            $plantilla,
            $informeZ,
            (int) $cierre->empresa_id,
        );

        $conciliacion = $informeZ !== null
            ? WaitryInformeZConciliacionSupport::conciliar($plantilla)
            : null;

        return [
            'jornada_id' => $jornadaId,
            'cierre_totem_id' => (int) $cierre->id,
            'fecha_jornada' => $cierre->jornada?->fecha_jornada?->format('d/m/Y') ?? '',
            'informe_z_cargado' => $informeZ !== null,
            'informe_z_en' => $informeZ['informe_z_en'] ?? null,
            'usuario_informe_z' => $informeZ['usuario_nombre'] ?? null,
            'totems' => $plantilla,
            'conciliacion' => $conciliacion,
            'tolerancia' => WaitryInformeZConciliacionSupport::toleranciaMonto(),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload  totems: [{totem_id, lineas: [{tipo_waitry, monto}]}]
     * @return array<string, mixed>
     */
    public function guardarInformeZ(int $jornadaId, array $payload): array
    {
        $cierre = $this->cierrePorJornada($jornadaId);
        $detalle = is_array($cierre->detalle_json) ? $cierre->detalle_json : [];
        $resumen = WaitryInformeZConciliacionSupport::resumenSistemaDesdeDetalleCierre($detalle);

        $plantilla = WaitryInformeZConciliacionSupport::plantillaCarga(
            (int) $cierre->empresa_id,
            $resumen,
        );

        $totemsPayload = $payload['totems'] ?? [];
        if (! is_array($totemsPayload)) {
            throw new InvalidArgumentException('Debe enviar los montos del Informe Z por tótem.');
        }

        $plantilla = $this->aplicarPayloadEnPlantilla($plantilla, $totemsPayload, (int) $cierre->empresa_id);
        $conciliacion = WaitryInformeZConciliacionSupport::conciliar($plantilla);

        $informeZ = $this->armarInformeZDesdePlantilla($plantilla, $conciliacion);

        $cierre->informe_z_json = $informeZ;
        $cierre->save();

        return [
            'ok' => true,
            'mensaje' => $conciliacion['ok']
                ? 'Informe Z registrado: cuadra con el sistema.'
                : 'Informe Z registrado: hay diferencias respecto al sistema.',
            'conciliacion' => $conciliacion,
            'informe_z_cargado' => true,
        ];
    }

    /**
     * Guarda borrador del Informe Z en la jornada abierta (antes del cierre definitivo).
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $resumenSistema  por_totem + total_general del preview
     * @return array<string, mixed>
     */
    public function guardarBorradorJornadaAbierta(
        JornadaGastronomia $jornada,
        array $payload,
        array $resumenSistema,
        ?array $snapshotCierre = null,
    ): array {
        if ($jornada->estado !== JornadaGastronomia::ESTADO_ABIERTA) {
            throw new InvalidArgumentException(
                'Solo puede cargar el Informe Z mientras la jornada está abierta.'
            );
        }

        $empresaId = (int) $jornada->empresa_id;
        $plantilla = WaitryInformeZConciliacionSupport::plantillaCarga($empresaId, $resumenSistema);

        $totemsPayload = $payload['totems'] ?? [];
        if (! is_array($totemsPayload)) {
            throw new InvalidArgumentException('Debe enviar los montos del Informe Z por tótem.');
        }

        $plantilla = $this->aplicarPayloadEnPlantilla($plantilla, $totemsPayload, $empresaId);
        $conciliacion = WaitryInformeZConciliacionSupport::conciliar($plantilla);
        $informeZ = $this->armarInformeZDesdePlantilla($plantilla, $conciliacion);
        if (is_array($snapshotCierre) && is_array($snapshotCierre['resumen_totems'] ?? null)) {
            $informeZ['snapshot_cierre'] = $snapshotCierre;
        }

        $jornada->informe_z_borrador_json = $informeZ;
        $jornada->save();

        return [
            'ok' => true,
            'mensaje' => $conciliacion['ok']
                ? 'Informe Z guardado: cuadra con el sistema.'
                : 'Informe Z guardado: hay diferencias respecto al sistema.',
            'conciliacion' => $conciliacion,
            'informe_z_cargado' => true,
            'informe_z_en' => $informeZ['informe_z_en'],
            'usuario_informe_z' => $informeZ['usuario_nombre'],
            'totems' => $plantilla,
        ];
    }

    /**
     * Arma el JSON del Informe Z a partir de totems del request y resumen del sistema (p. ej. al cerrar).
     *
     * @param  array<string, mixed>  $resumenSistema
     * @param  array<int, mixed>  $totemsPayload
     * @return array<string, mixed>|null
     */
    public function construirInformeZDesdePayload(int $empresaId, array $resumenSistema, array $totemsPayload): ?array
    {
        if ($totemsPayload === []) {
            return null;
        }

        $plantilla = WaitryInformeZConciliacionSupport::plantillaCarga($empresaId, $resumenSistema);
        $plantilla = $this->aplicarPayloadEnPlantilla($plantilla, $totemsPayload, $empresaId);
        $conciliacion = WaitryInformeZConciliacionSupport::conciliar($plantilla);

        return $this->armarInformeZDesdePlantilla($plantilla, $conciliacion);
    }

    /**
     * @param  list<array<string, mixed>>  $plantilla
     * @param  array<string, mixed>  $conciliacion
     * @return array<string, mixed>
     */
    private function armarInformeZDesdePlantilla(array $plantilla, array $conciliacion): array
    {
        return [
            'totems' => array_map(function (array $bloque) {
                return [
                    'totem_id' => (int) ($bloque['totem_id'] ?? 0),
                    'waitry_table_id' => isset($bloque['waitry_table_id']) ? (int) $bloque['waitry_table_id'] : null,
                    'lineas' => array_map(fn (array $ln) => [
                        'tipo_waitry' => $ln['tipo_waitry'] ?? null,
                        'cuentacaja_id' => isset($ln['cuentacaja_id']) ? (int) $ln['cuentacaja_id'] : null,
                        'cuentacaja_codigo' => (string) ($ln['cuentacaja_codigo'] ?? ''),
                        'cuentacaja_nombre' => (string) ($ln['cuentacaja_nombre'] ?? ''),
                        'monto_sistema' => round((float) ($ln['monto_sistema'] ?? 0), 2),
                        'monto_informe_z' => round((float) ($ln['monto_informe_z'] ?? 0), 2),
                        'monto' => round((float) ($ln['monto_informe_z'] ?? 0), 2),
                    ], $bloque['lineas'] ?? []),
                ];
            }, $plantilla),
            'informe_z_en' => now()->format('Y-m-d H:i:s'),
            'usuario_id' => Auth::id(),
            'usuario_nombre' => Auth::user()?->nombre ?? '',
            'conciliacion' => $conciliacion,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $plantilla
     * @param  array<int, mixed>  $totemsPayload
     * @return list<array<string, mixed>>
     */
    private function aplicarPayloadEnPlantilla(array $plantilla, array $totemsPayload, int $empresaId): array
    {
        $porClave = [];
        foreach ($totemsPayload as $t) {
            if (! is_array($t)) {
                continue;
            }
            $tid = (int) ($t['totem_id'] ?? 0);
            if ($tid <= 0) {
                continue;
            }
            $tableId = (int) ($t['waitry_table_id'] ?? 0);
            $porClave[$tid.':'.$tableId] = $t;
            if ($tableId <= 0) {
                $porClave[(string) $tid] = $t;
            }
        }

        $salida = [];
        foreach ($plantilla as $bloque) {
            $tid = (int) ($bloque['totem_id'] ?? 0);
            $tableId = (int) ($bloque['waitry_table_id'] ?? 0);
            $payload = $porClave[$tid.':'.$tableId] ?? $porClave[(string) $tid] ?? null;
            $lineasBase = [];
            foreach ($bloque['lineas'] ?? [] as $ln) {
                $ccId = (int) ($ln['cuentacaja_id'] ?? 0);
                if ($ccId > 0) {
                    $lineasBase[$ccId] = $ln;
                }
            }

            if ($payload !== null) {
                foreach ($payload['lineas'] ?? [] as $ln) {
                    if (! is_array($ln)) {
                        continue;
                    }
                    $ccId = (int) ($ln['cuentacaja_id'] ?? 0);
                    $montoZ = round((float) ($ln['monto'] ?? $ln['monto_informe_z'] ?? 0), 2);
                    if ($ccId <= 0 && $montoZ <= 0) {
                        continue;
                    }
                    if ($ccId > 0 && WaitryMedioPagoCuentacajaSupport::esCuentacajaTotem($ccId, $empresaId)) {
                        continue;
                    }
                    if ($ccId > 0 && ! isset($lineasBase[$ccId])) {
                        $tipo = WaitryMedioPagoCuentacajaSupport::tipoParaClaveMapaInformeZ($ln['tipo_waitry'] ?? null, $empresaId);
                        $lineasBase[$ccId] = [
                            'tipo_waitry' => $tipo,
                            'etiqueta' => (string) ($ln['cuentacaja_nombre'] ?? $ln['etiqueta'] ?? '—'),
                            'cuentacaja_id' => $ccId,
                            'cuentacaja_codigo' => (string) ($ln['cuentacaja_codigo'] ?? ''),
                            'cuentacaja_nombre' => (string) ($ln['cuentacaja_nombre'] ?? ''),
                            'moneda_abreviatura' => (string) ($ln['moneda_abreviatura'] ?? 'ARS'),
                            'monto_sistema' => 0.0,
                            'cantidad_sistema' => 0,
                            'monto_informe_z' => null,
                        ];
                    }
                    if ($ccId > 0) {
                        $lineasBase[$ccId]['monto_informe_z'] = $montoZ;
                    }
                }
            }

            foreach ($lineasBase as &$ln) {
                if (! array_key_exists('monto_informe_z', $ln) || $ln['monto_informe_z'] === null) {
                    $ln['monto_informe_z'] = 0.0;
                }
            }
            unset($ln);

            $bloque['lineas'] = array_values($lineasBase);
            $salida[] = $bloque;
        }

        return $salida;
    }

    /**
     * Reconsulta Waitry (tramo histórico del cierre) y actualiza montos Sistema del Informe Z persistido.
     * Conserva los montos Z ingresados por el usuario; recalcula conciliación.
     *
     * @return array<string, mixed>
     */
    public function recalcularSistemaInformeZEnCierre(int $jornadaId): array
    {
        $cierre = $this->cierrePorJornada($jornadaId);
        $jornada = $cierre->jornada;
        if ($jornada === null) {
            throw new InvalidArgumentException('Jornada no encontrada para el cierre tótem #'.$cierre->id);
        }

        $cierreTotem = app(GastronomiaCierreTotemJornadaService::class);
        if (! $cierreTotem->habilitado()) {
            throw new InvalidArgumentException('Cierre tótem Waitry no habilitado.');
        }

        $consulta = $cierreTotem->datosTramoInformeZ($jornada);
        $resumenInformeZ = is_array($consulta['resumen_informe_z'] ?? null)
            ? $consulta['resumen_informe_z']
            : [];

        $detalle = is_array($cierre->detalle_json) ? $cierre->detalle_json : [];
        $sistemaAnterior = (float) ($detalle['resumen_informe_z']['total_general']['total_ingreso'] ?? 0);
        $detalle['resumen_informe_z'] = $resumenInformeZ;
        $auditoria = is_array($detalle['auditoria'] ?? null) ? $detalle['auditoria'] : [];
        $auditoria['resumen_informe_z_recalculado_en'] = now()->format('Y-m-d H:i:s');
        $auditoria['resumen_informe_z_sistema_anterior'] = round($sistemaAnterior, 2);
        $detalle['auditoria'] = $auditoria;
        $cierre->detalle_json = $detalle;

        $informeZAnterior = is_array($cierre->informe_z_json) ? $cierre->informe_z_json : null;
        $sistemaNuevo = (float) ($resumenInformeZ['total_general']['total_ingreso'] ?? 0);

        if ($informeZAnterior !== null && isset($informeZAnterior['totems'])) {
            $empresaId = (int) $cierre->empresa_id;
            $plantilla = WaitryInformeZConciliacionSupport::plantillaCarga($empresaId, $resumenInformeZ);
            $plantilla = WaitryInformeZConciliacionSupport::fusionarInformeZEnPlantilla(
                $plantilla,
                $informeZAnterior,
                $empresaId,
            );
            $conciliacion = WaitryInformeZConciliacionSupport::conciliar($plantilla);
            $informeZ = $this->armarInformeZDesdePlantilla($plantilla, $conciliacion);
            $informeZ['informe_z_en'] = $informeZAnterior['informe_z_en'] ?? $informeZ['informe_z_en'];
            $informeZ['usuario_id'] = $informeZAnterior['usuario_id'] ?? $informeZ['usuario_id'];
            $informeZ['usuario_nombre'] = $informeZAnterior['usuario_nombre'] ?? $informeZ['usuario_nombre'];
            $cierre->informe_z_json = $informeZ;
        }

        $cierre->save();

        $zIngresado = 0.0;
        if (is_array($cierre->informe_z_json)) {
            foreach ($cierre->informe_z_json['totems'] ?? [] as $t) {
                foreach ($t['lineas'] ?? [] as $ln) {
                    $zIngresado += (float) ($ln['monto'] ?? $ln['monto_informe_z'] ?? 0);
                }
            }
        }

        return [
            'ok' => true,
            'jornada_id' => $jornadaId,
            'cierre_totem_id' => (int) $cierre->id,
            'sistema_anterior' => round($sistemaAnterior, 2),
            'sistema_nuevo' => round($sistemaNuevo, 2),
            'z_ingresado' => round($zIngresado, 2),
            'cantidad_ordenes' => (int) ($resumenInformeZ['total_general']['cantidad_ordenes'] ?? 0),
            'conciliacion_ok' => (bool) ($cierre->informe_z_json['conciliacion']['ok'] ?? false),
            'por_totem' => array_map(static fn (array $b) => [
                'totem_id' => $b['totem_id'] ?? null,
                'ubicacion' => $b['ubicacion_nombre'] ?? '',
                'total_ingreso' => round((float) ($b['total_ingreso'] ?? 0), 2),
                'cantidad_ordenes' => (int) ($b['cantidad_ordenes'] ?? 0),
            ], $resumenInformeZ['por_totem'] ?? []),
        ];
    }

    private function cierrePorJornada(int $jornadaId): CierreTotemJornadaGastronomia
    {
        $cierre = CierreTotemJornadaGastronomia::query()
            ->with(['jornada', 'empresa'])
            ->where('jornada_gastronomia_id', $jornadaId)
            ->first();

        if ($cierre === null) {
            throw new InvalidArgumentException(
                'No hay cierre de tótem Waitry para esta jornada. Cierre la jornada primero.'
            );
        }

        return $cierre;
    }
}
