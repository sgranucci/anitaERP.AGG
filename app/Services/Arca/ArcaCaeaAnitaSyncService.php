<?php

namespace App\Services\Arca;

use App\ApiAnita;
use App\Models\Ventas\ArcaCaea;
use Illuminate\Support\Facades\Log;

/**
 * Réplica CAEA autorizado (arca_caea) → tabla Informix caea vía bridge Anita.
 * Temporal mientras Anita siga operativa como respaldo de facturación.
 */
class ArcaCaeaAnitaSyncService
{
    private const TABLA = 'caea';

    private const LOG_EVENTO = 'arca_caea.anita_bridge.fallo';

    public function estaHabilitado(): bool
    {
        return (bool) config('arca.caea.replicar_en_anita', true);
    }

    /**
     * Tras solicitud ARCA exitosa: replica sin abortar el flujo principal.
     */
    public function intentarGrabarTrasSolicitud(ArcaCaea $registro): ?string
    {
        if (! $this->estaHabilitado() || ! $registro->estaAutorizado()) {
            return null;
        }

        try {
            $resultado = $this->grabarEnAnita($registro);

            return $resultado['ok'] ? null : 'Advertencia Anita: '.$resultado['mensaje'];
        } catch (\Throwable $e) {
            Log::warning(self::LOG_EVENTO, [
                'contexto' => 'caea insert tras solicitud',
                'arca_caea_id' => $registro->id,
                'mensaje' => $e->getMessage(),
            ]);

            return 'Advertencia Anita: '.$e->getMessage();
        }
    }

    /**
     * @return array{ok: bool, mensaje: string, accion: string}
     */
    public function grabarEnAnita(ArcaCaea $registro): array
    {
        if (! $this->estaHabilitado()) {
            return [
                'ok' => false,
                'mensaje' => 'Réplica en Anita deshabilitada (ARCA_CAEA_REPLICAR_ANITA=false).',
                'accion' => 'omitido',
            ];
        }

        if (! $registro->estaAutorizado()) {
            return [
                'ok' => false,
                'mensaje' => 'El CAEA no está autorizado en anitaERP.',
                'accion' => 'omitido',
            ];
        }

        $cuit = $this->normalizarCuit($registro->cuit);
        $nroCaea = trim((string) $registro->nro_caea);
        if ($cuit === '' || $nroCaea === '') {
            return [
                'ok' => false,
                'mensaje' => 'Faltan CUIT o número CAEA.',
                'accion' => 'omitido',
            ];
        }

        $existente = $this->buscarEnAnita($cuit, (int) $registro->periodo, (int) $registro->orden);
        if ($existente !== null) {
            $nroAnita = trim((string) ($existente->caea_nro_caea ?? ''));
            if ($nroAnita === $nroCaea) {
                return [
                    'ok' => true,
                    'mensaje' => 'CAEA ya registrado en Anita.',
                    'accion' => 'existente',
                ];
            }

            $this->actualizarEnAnita($registro, $cuit);

            return [
                'ok' => true,
                'mensaje' => 'CAEA actualizado en Anita.',
                'accion' => 'actualizado',
            ];
        }

        $this->insertarEnAnita($registro, $cuit);

        return [
            'ok' => true,
            'mensaje' => 'CAEA grabado en Anita.',
            'accion' => 'insertado',
        ];
    }

    private function insertarEnAnita(ArcaCaea $registro, string $cuit): void
    {
        $api = new ApiAnita;
        $serial = $api->obtenerSiguienteNumerador(self::TABLA, 'caea_serial');
        $valores = $this->armarValoresInsert($registro, $cuit, $serial);

        $api->apiCallEscritura([
            'acc' => 'insert',
            'tabla' => self::TABLA,
            'campos' => implode(', ', [
                'caea_serial',
                'caea_nro_caea',
                'caea_periodo',
                'caea_orden',
                'caea_desde_fecha',
                'caea_hasta_fecha',
                'caea_fecha_tope',
                'caea_fecha_proc',
                'caea_cuit',
            ]),
            'valores' => $valores,
        ], 'caea insert', self::LOG_EVENTO);
    }

    private function actualizarEnAnita(ArcaCaea $registro, string $cuit): void
    {
        $api = new ApiAnita;
        $valores = $this->armarValoresUpdate($registro);

        $api->apiCallEscritura([
            'acc' => 'update',
            'tabla' => self::TABLA,
            'valores' => $valores,
            'whereArmado' => $this->whereEmpresaQuincena($cuit, (int) $registro->periodo, (int) $registro->orden),
        ], 'caea update', self::LOG_EVENTO);
    }

    private function buscarEnAnita(string $cuit, int $periodo, int $orden): ?object
    {
        $api = new ApiAnita;
        $payload = [
            'acc' => 'list',
            'tabla' => self::TABLA,
            'campos' => 'caea_serial, caea_nro_caea',
            'whereArmado' => $this->whereEmpresaQuincena($cuit, $periodo, $orden),
        ];

        return ApiAnita::primeraFilaLista($api->apiCall($payload));
    }

    private function whereEmpresaQuincena(string $cuit, int $periodo, int $orden): string
    {
        return " WHERE caea_cuit = '".$this->escSql($cuit)."'"
            ." AND caea_periodo = {$periodo}"
            ." AND caea_orden = {$orden}";
    }

    private function armarValoresInsert(ArcaCaea $registro, string $cuit, int $serial): string
    {
        $fechas = $this->fechasAnita($registro);

        return implode(",\n", [
            (string) $serial,
            "'".$this->escSql($this->formatearNroCaea($registro))."'",
            (string) (int) $registro->periodo,
            (string) (int) $registro->orden,
            (string) $fechas['desde'],
            (string) $fechas['hasta'],
            (string) $fechas['tope'],
            "'".$this->escSql($fechas['proceso'])."'",
            "'".$this->escSql($cuit)."'",
        ]);
    }

    private function armarValoresUpdate(ArcaCaea $registro): string
    {
        $fechas = $this->fechasAnita($registro);

        return implode(', ', [
            "caea_nro_caea = '".$this->escSql($this->formatearNroCaea($registro))."'",
            'caea_desde_fecha = '.$fechas['desde'],
            'caea_hasta_fecha = '.$fechas['hasta'],
            'caea_fecha_tope = '.$fechas['tope'],
            "caea_fecha_proc = '".$this->escSql($fechas['proceso'])."'",
        ]);
    }

    /**
     * @return array{desde: int, hasta: int, tope: int, proceso: string}
     */
    private function fechasAnita(ArcaCaea $registro): array
    {
        $desde = $registro->fecha_vigencia_desde
            ? (int) $registro->fecha_vigencia_desde->format('Ymd')
            : 0;
        $hasta = $registro->fecha_vigencia_hasta
            ? (int) $registro->fecha_vigencia_hasta->format('Ymd')
            : 0;
        $tope = $registro->fecha_tope_informe
            ? (int) $registro->fecha_tope_informe->format('Ymd')
            : 0;
        $proceso = $registro->fecha_proceso
            ? $registro->fecha_proceso->format('YmdHis')
            : now()->format('YmdHis');

        return [
            'desde' => $desde,
            'hasta' => $hasta,
            'tope' => $tope,
            'proceso' => $proceso,
        ];
    }

    private function formatearNroCaea(ArcaCaea $registro): string
    {
        return substr(str_pad(trim((string) $registro->nro_caea), 20, ' ', STR_PAD_RIGHT), 0, 20);
    }

    private function normalizarCuit(mixed $valor): string
    {
        return preg_replace('/\D+/', '', (string) $valor) ?? '';
    }

    private function escSql(string $valor): string
    {
        return str_replace("'", "''", $valor);
    }
}
