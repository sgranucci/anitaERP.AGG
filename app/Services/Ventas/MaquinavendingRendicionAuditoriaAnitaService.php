<?php

declare(strict_types=1);

namespace App\Services\Ventas;

use App\Models\Ventas\MaquinavendingRendicion;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Concilia rendiciones vending ERP ↔ rendgastro (Anita) y repara diferencias.
 */
final class MaquinavendingRendicionAuditoriaAnitaService
{
    private const LOG_EVENTO = 'maquinavending_rendicion.auditoria_anita';

    public function __construct(
        private readonly MaquinavendingRendicionAnitaSyncService $anitaSyncService,
        private readonly MaquinavendingRendicionResincronizarAnitaService $resincronizarService,
    ) {
    }

    /**
     * @return array{
     *   fecha_jornada: string,
     *   empresa_id: int,
     *   tolerancia: float,
     *   filas: list<array<string, mixed>>,
     *   resumen: array<string, mixed>
     * }
     */
    public function auditarFechaJornada(int $empresaId, string $fechaJornada, float $tolerancia = 0.02): array
    {
        if (! $this->anitaSyncService->sincronizacionHabilitada()) {
            throw new \RuntimeException('RENDICION_MAQUINAVENDING_SINCRONIZAR_ANITA está deshabilitado.');
        }

        $fechaJornada = Carbon::parse($fechaJornada)->toDateString();
        $tolerancia = max(0.0, $tolerancia);

        $rendiciones = MaquinavendingRendicion::query()
            ->with(['maquinavending.puntoventa', 'rendicionCaja'])
            ->where('empresa_id', $empresaId)
            ->whereDate('fecha_jornada', $fechaJornada)
            ->orderBy('numero_cierre')
            ->get();

        $filas = [];
        $conteo = [
            'rendiciones' => 0,
            'ok' => 0,
            'sin_cabecera' => 0,
            'dif_total_x' => 0,
            'dif_total_z' => 0,
            'requiere_reparacion' => 0,
        ];

        foreach ($rendiciones as $rendicion) {
            $fila = $this->auditarRendicion($rendicion, $tolerancia);
            $filas[] = $fila;
            $conteo['rendiciones']++;

            if ($fila['estado'] === 'OK') {
                $conteo['ok']++;
            }
            if ($fila['sin_cabecera_anita']) {
                $conteo['sin_cabecera']++;
            }
            if ($fila['dif_total_x']) {
                $conteo['dif_total_x']++;
            }
            if ($fila['dif_total_z']) {
                $conteo['dif_total_z']++;
            }
            if ($fila['requiere_reparacion']) {
                $conteo['requiere_reparacion']++;
            }
        }

        return [
            'fecha_jornada' => $fechaJornada,
            'empresa_id' => $empresaId,
            'tolerancia' => $tolerancia,
            'filas' => $filas,
            'resumen' => [
                'conteo' => $conteo,
                'requiere_alerta' => $conteo['requiere_reparacion'] > 0,
                'filtro_erp' => 'maquinavending_rendicion.fecha_jornada',
                'filtro_anita' => 'rendgastro rendg_nro_oper + tipo F (vending)',
            ],
        ];
    }

    /**
     * @return array{
     *   pre: array<string, mixed>,
     *   post: array<string, mixed>,
     *   replicacion: array<string, mixed>
     * }
     */
    public function auditarYRepararFechaJornada(
        int $empresaId,
        string $fechaJornada,
        bool $dryRun = false,
        float $tolerancia = 0.02,
    ): array {
        $pre = $this->auditarFechaJornada($empresaId, $fechaJornada, $tolerancia);

        $replicacion = [
            'circuito' => 'vending',
            'fecha_jornada' => $pre['fecha_jornada'],
            'faltantes' => (int) ($pre['resumen']['conteo']['requiere_reparacion'] ?? 0),
            'replicadas' => 0,
            'errores' => [],
            'detalle' => [],
            'omitida' => ($pre['resumen']['conteo']['requiere_reparacion'] ?? 0) === 0,
        ];

        foreach ($pre['filas'] as $fila) {
            if (! ($fila['requiere_reparacion'] ?? false)) {
                continue;
            }

            $rendicionId = (int) ($fila['rendicion_id'] ?? 0);
            if ($rendicionId <= 0) {
                continue;
            }

            if ($dryRun) {
                $replicacion['replicadas']++;
                $replicacion['detalle'][] = [
                    'estado' => 'simulada',
                    'codigo' => (string) ($fila['nro_oper_anita'] ?? ''),
                    'puntoventa' => (string) ($fila['maquina'] ?? ''),
                    'total' => (float) ($fila['erp_total_x'] ?? 0),
                    'mensaje' => (string) ($fila['observacion'] ?? ''),
                ];

                continue;
            }

            try {
                $informe = $this->resincronizarService->ejecutar([], $rendicionId, false);
                $err = $informe['errores'][0] ?? null;
                if ($err !== null) {
                    throw new \RuntimeException((string) ($err['mensaje'] ?? 'Error al re-sincronizar.'));
                }

                $replicacion['replicadas']++;
                $replicacion['detalle'][] = [
                    'estado' => 'reparada',
                    'codigo' => (string) ($fila['nro_oper_anita'] ?? ''),
                    'puntoventa' => (string) ($fila['maquina'] ?? ''),
                    'total' => (float) ($fila['erp_total_x'] ?? 0),
                    'mensaje' => (string) ($fila['observacion'] ?? ''),
                ];
            } catch (\Throwable $e) {
                Log::warning(self::LOG_EVENTO.'.reparacion_fallo', [
                    'rendicion_id' => $rendicionId,
                    'empresa_id' => $empresaId,
                    'fecha_jornada' => $fechaJornada,
                    'mensaje' => $e->getMessage(),
                ]);
                $replicacion['errores'][] = [
                    'rendicion_id' => $rendicionId,
                    'codigo' => (string) ($fila['nro_oper_anita'] ?? ''),
                    'mensaje' => $e->getMessage(),
                ];
            }
        }

        $post = $this->auditarFechaJornada($empresaId, $fechaJornada, $tolerancia);

        return [
            'pre' => $pre,
            'post' => $post,
            'replicacion' => $replicacion,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function auditarRendicion(MaquinavendingRendicion $rendicion, float $tolerancia): array
    {
        $erpTotalX = round((float) $rendicion->total_ventas, 2);
        $presentada = $rendicion->rendicionCaja !== null;
        $erpTotalZ = $presentada
            ? round((float) ($rendicion->rendicionCaja->totalfactura ?? 0), 2)
            : 0.0;

        $nroOper = (int) ($rendicion->nro_oper_anita ?? 0);
        $anita = $nroOper > 0 ? $this->anitaSyncService->leerTotalesCabeceraEnAnita($rendicion) : null;

        $sinCabecera = $anita === null;
        $anitaTotalX = $sinCabecera ? null : (float) $anita['total_x'];
        $anitaTotalZ = $sinCabecera ? null : (float) $anita['total_z'];

        $difX = ! $sinCabecera && abs($erpTotalX - $anitaTotalX) > $tolerancia;
        $difZ = $presentada
            && ! $sinCabecera
            && abs($erpTotalZ - $anitaTotalZ) > $tolerancia;

        $requiereReparacion = $sinCabecera || $difX || $difZ;

        $observaciones = [];
        if ($sinCabecera) {
            $observaciones[] = 'Sin cabecera rendgastro';
        }
        if ($difX) {
            $observaciones[] = sprintf(
                'rendg_total_x ERP $%s vs Anita $%s',
                number_format($erpTotalX, 2, ',', '.'),
                number_format((float) $anitaTotalX, 2, ',', '.'),
            );
        }
        if ($difZ) {
            $observaciones[] = sprintf(
                'rendg_total_z ERP $%s vs Anita $%s',
                number_format($erpTotalZ, 2, ',', '.'),
                number_format((float) $anitaTotalZ, 2, ',', '.'),
            );
        }

        $estado = 'OK';
        if ($sinCabecera) {
            $estado = 'SIN RENDG';
        } elseif ($difX && $difZ) {
            $estado = 'DIF X+Z';
        } elseif ($difX) {
            $estado = 'DIF total_x';
        } elseif ($difZ) {
            $estado = 'DIF total_z';
        }

        $maquina = trim(
            ($rendicion->maquinavending?->puntoventa?->codigo ?? '').' — '.($rendicion->maquinavending?->nombre ?? ''),
            ' —',
        );

        return [
            'rendicion_id' => (int) $rendicion->id,
            'numero_cierre' => (int) $rendicion->numero_cierre,
            'nro_oper_anita' => $nroOper,
            'maquina' => $maquina,
            'presentada_caja' => $presentada,
            'erp_total_x' => $erpTotalX,
            'erp_total_z' => $erpTotalZ,
            'anita_total_x' => $anitaTotalX,
            'anita_total_z' => $anitaTotalZ,
            'sin_cabecera_anita' => $sinCabecera,
            'dif_total_x' => $difX,
            'dif_total_z' => $difZ,
            'requiere_reparacion' => $requiereReparacion,
            'estado' => $estado,
            'observacion' => implode('; ', $observaciones),
        ];
    }
}
