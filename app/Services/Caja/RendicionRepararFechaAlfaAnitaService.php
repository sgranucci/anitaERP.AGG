<?php

namespace App\Services\Caja;

use App\ApiAnita;
use App\Support\Caja\AnitaSync\RendicionAnitaFechaAlfaSupport;
use App\Support\Caja\AnitaSync\RendicionGastronomiaCabeceraAnitaMapper;
use Carbon\Carbon;
use RuntimeException;

/**
 * Corrige rendg_fecha_alfa en rendgastro (Ymd → DD/MM/YY) vía bridge Anita.
 */
final class RendicionRepararFechaAlfaAnitaService
{
    private const LOG_EVENTO = 'rendicion_reparar_fecha_alfa.anita_bridge.fallo';

    /**
     * @return list<array<string, mixed>>
     */
    public function reparar(string $fechaDesde, ?int $empresaId, bool $dryRun): array
    {
        $fechaDesdeEntera = (int) Carbon::parse($fechaDesde)->format('Ymd');
        $filas = $this->listarCandidatas($fechaDesdeEntera, $empresaId);

        $resultados = [];
        foreach ($filas as $fila) {
            $nroOper = (int) ($fila->rendg_nro_oper ?? 0);
            $tipoOper = trim((string) ($fila->rendg_tipo_oper ?? ''));
            $fechaEntera = (int) ($fila->rendg_fecha ?? 0);
            $alfaActual = trim((string) ($fila->rendg_fecha_alfa ?? ''));
            $alfaNueva = RendicionAnitaFechaAlfaSupport::desdeFechaEntera($fechaEntera);

            if ($nroOper <= 0 || $tipoOper === '' || $alfaNueva === '') {
                $resultados[] = [
                    'nro_oper' => $nroOper,
                    'tipo_oper' => $tipoOper,
                    'empresa' => (int) ($fila->rendg_empresa ?? 0),
                    'fecha' => $fechaEntera,
                    'sucursal' => (int) ($fila->rendg_sucursal ?? 0),
                    'turno' => trim((string) ($fila->rendg_turno ?? '')),
                    'alfa_actual' => $alfaActual,
                    'alfa_nueva' => $alfaNueva,
                    'estado' => 'omitido',
                    'motivo' => 'Clave o fecha inválida',
                ];

                continue;
            }

            if (! RendicionAnitaFechaAlfaSupport::necesitaReparacion($alfaActual)) {
                $resultados[] = [
                    'nro_oper' => $nroOper,
                    'tipo_oper' => $tipoOper,
                    'empresa' => (int) ($fila->rendg_empresa ?? 0),
                    'fecha' => $fechaEntera,
                    'sucursal' => (int) ($fila->rendg_sucursal ?? 0),
                    'turno' => trim((string) ($fila->rendg_turno ?? '')),
                    'alfa_actual' => $alfaActual,
                    'alfa_nueva' => $alfaNueva,
                    'estado' => 'ok',
                    'motivo' => 'Ya en DD/MM/YY',
                ];

                continue;
            }

            if ($dryRun) {
                $resultados[] = [
                    'nro_oper' => $nroOper,
                    'tipo_oper' => $tipoOper,
                    'empresa' => (int) ($fila->rendg_empresa ?? 0),
                    'fecha' => $fechaEntera,
                    'sucursal' => (int) ($fila->rendg_sucursal ?? 0),
                    'turno' => trim((string) ($fila->rendg_turno ?? '')),
                    'alfa_actual' => $alfaActual,
                    'alfa_nueva' => $alfaNueva,
                    'estado' => 'simulado',
                    'motivo' => '',
                ];

                continue;
            }

            $this->actualizarFechaAlfa($nroOper, $tipoOper, $alfaNueva);

            $resultados[] = [
                'nro_oper' => $nroOper,
                'tipo_oper' => $tipoOper,
                'empresa' => (int) ($fila->rendg_empresa ?? 0),
                'fecha' => $fechaEntera,
                'sucursal' => (int) ($fila->rendg_sucursal ?? 0),
                'turno' => trim((string) ($fila->rendg_turno ?? '')),
                'alfa_actual' => $alfaActual,
                'alfa_nueva' => $alfaNueva,
                'estado' => 'actualizado',
                'motivo' => '',
            ];
        }

        return $resultados;
    }

    /**
     * @return list<object>
     */
    private function listarCandidatas(int $fechaDesdeEntera, ?int $empresaId): array
    {
        $whereEmpresa = $empresaId !== null && $empresaId > 0
            ? ' AND rendg_empresa = '.(int) $empresaId
            : '';

        $api = new ApiAnita;
        $raw = $api->apiCall([
            'acc' => 'list',
            'sistema' => $this->sistema(),
            'tabla' => 'rendgastro',
            'campos' => 'rendg_nro_oper, rendg_tipo_oper, rendg_empresa, rendg_fecha, rendg_fecha_alfa, rendg_sucursal, rendg_turno',
            'whereArmado' => ' WHERE rendg_fecha >= '.$fechaDesdeEntera.$whereEmpresa,
            'orderBy' => 'rendg_fecha, rendg_nro_oper',
        ]);

        $parsed = ApiAnita::parsearRespuestaLista($raw);
        if ($parsed['error_lectura'] !== null) {
            throw new RuntimeException('Error al listar rendgastro en Anita: '.$parsed['error_lectura']);
        }

        return array_values(array_filter(
            $parsed['filas'],
            fn (object $fila) => RendicionAnitaFechaAlfaSupport::necesitaReparacion(
                isset($fila->rendg_fecha_alfa) ? (string) $fila->rendg_fecha_alfa : null,
            ),
        ));
    }

    private function actualizarFechaAlfa(int $nroOper, string $tipoOper, string $alfaNueva): void
    {
        $api = new ApiAnita;
        $api->apiCallEscritura([
            'acc' => 'update',
            'tabla' => 'rendgastro',
            'sistema' => $this->sistema(),
            'valores' => " rendg_fecha_alfa = '".RendicionGastronomiaCabeceraAnitaMapper::texto($alfaNueva, 8)."' ",
            'whereArmado' => RendicionGastronomiaCabeceraAnitaMapper::whereClave($nroOper, $tipoOper),
        ], 'rendgastro update fecha_alfa', self::LOG_EVENTO);
    }

    private function sistema(): string
    {
        return (string) config('rendicion_gastronomia_anita.sistema', 'caja');
    }
}
