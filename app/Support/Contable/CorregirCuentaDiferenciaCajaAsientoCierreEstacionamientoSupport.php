<?php

namespace App\Support\Contable;

use App\ApiAnita;
use App\Models\Configuracion\Empresa;
use App\Models\Contable\Asiento;
use App\Models\Contable\Asiento_Movimiento;
use App\Models\Contable\Cuentacontable;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Backfill: cuenta contable de diferencia de caja en asientos cierre estacionamiento (ERP + ctamov).
 */
final class CorregirCuentaDiferenciaCajaAsientoCierreEstacionamientoSupport
{
    public const CODIGO_CUENTA_ANTIGUA = '411010001';

    public const CODIGO_CUENTA_NUEVA = '521280004';

    public function __construct(
        private readonly CorregirCentrocostoAsientoCierreEstacionamientoSupport $alcanceSupport,
    ) {}

    /**
     * @return Collection<int, Asiento>
     */
    public function asientosAfectados(string $fecha, ?int $empresaId = null): Collection
    {
        return $this->alcanceSupport->asientosAfectados($fecha, $empresaId);
    }

    /**
     * @return array{
     *   fecha:string,
     *   rendiciones_cerradas:int,
     *   asientos:int,
     *   por_empresa:array<int,array{rendiciones:int,asientos:int}>
     * }
     */
    public function resumenAlcance(string $fecha, ?int $empresaId = null): array
    {
        return $this->alcanceSupport->resumenAlcance($fecha, $empresaId);
    }

    /**
     * @return array{
     *   asientos_erp:int,
     *   lineas_erp:int,
     *   lineas_anita:int,
     *   ya_ok:int,
     *   errores:list<string>
     * }
     */
    public function ejecutar(string $fecha, bool $dryRun = false, ?int $empresaId = null): array
    {
        return $this->ejecutarSobreColeccion($this->asientosAfectados($fecha, $empresaId), $dryRun);
    }

    /**
     * @param  Collection<int, Asiento>  $asientos
     * @return array{
     *   asientos_erp:int,
     *   lineas_erp:int,
     *   lineas_anita:int,
     *   ya_ok:int,
     *   errores:list<string>
     * }
     */
    private function ejecutarSobreColeccion(Collection $asientos, bool $dryRun): array
    {
        $resultado = [
            'asientos_erp' => 0,
            'lineas_erp' => 0,
            'lineas_anita' => 0,
            'ya_ok' => 0,
            'errores' => [],
        ];

        /** @var array<int, int> $cacheCuentaNuevaPorEmpresa */
        $cacheCuentaNuevaPorEmpresa = [];
        /** @var array<int, int> $cacheCuentaAntiguaPorEmpresa */
        $cacheCuentaAntiguaPorEmpresa = [];

        foreach ($asientos as $asiento) {
            $empresaId = (int) $asiento->empresa_id;
            $cuentaNuevaId = $this->resolverCuentaIdPorEmpresa(
                $empresaId,
                self::CODIGO_CUENTA_NUEVA,
                $cacheCuentaNuevaPorEmpresa,
            );
            $cuentaAntiguaId = $this->resolverCuentaIdPorEmpresa(
                $empresaId,
                self::CODIGO_CUENTA_ANTIGUA,
                $cacheCuentaAntiguaPorEmpresa,
            );

            if ($cuentaNuevaId <= 0) {
                $resultado['errores'][] = 'Asiento ERP #'.$asiento->id
                    .': no existe cuenta '.self::CODIGO_CUENTA_NUEVA.' para empresa '.$empresaId.'.';

                continue;
            }

            $lineas = Asiento_Movimiento::query()
                ->where('asiento_id', $asiento->id)
                ->orderBy('id')
                ->get();

            $huboCambioErp = false;
            $indicesActualizados = [];

            foreach ($lineas->values() as $idx => $linea) {
                if ((int) ($linea->cuentacontable_id ?? 0) !== $cuentaAntiguaId) {
                    continue;
                }

                if (! $dryRun) {
                    $linea->cuentacontable_id = $cuentaNuevaId;
                    $linea->save();
                }

                $resultado['lineas_erp']++;
                $huboCambioErp = true;
                $indicesActualizados[] = $idx;
            }

            if ($huboCambioErp) {
                $resultado['asientos_erp']++;
            } elseif ($indicesActualizados === []) {
                $tieneDifCajaNueva = $lineas->contains(
                    fn (Asiento_Movimiento $l) => (int) ($l->cuentacontable_id ?? 0) === $cuentaNuevaId,
                );
                if ($tieneDifCajaNueva) {
                    $resultado['ya_ok']++;
                }
            }

            $empresa = Empresa::query()->find($asiento->empresa_id);
            $codigoEmpresa = (string) ($empresa->codigo ?? '1');
            $nroAsiento = trim((string) ($asiento->numeroasiento ?? ''));

            if ($nroAsiento === '') {
                if ($huboCambioErp) {
                    $resultado['errores'][] = 'Asiento ERP #'.$asiento->id.': sin numeroasiento para ctamov.';
                }

                continue;
            }

            if ($indicesActualizados === [] && ! $huboCambioErp) {
                continue;
            }

            if ($dryRun) {
                $resultado['lineas_anita'] += count($indicesActualizados);

                continue;
            }

            try {
                $resultado['lineas_anita'] += $this->actualizarLineasAnita(
                    $codigoEmpresa,
                    $nroAsiento,
                    $indicesActualizados,
                );
            } catch (RuntimeException $e) {
                $resultado['errores'][] = 'Asiento ERP #'.$asiento->id.' (Anita '.$nroAsiento.'): '.$e->getMessage();
            }
        }

        return $resultado;
    }

    /**
     * @param  array<int, int>  $cache
     */
    private function resolverCuentaIdPorEmpresa(int $empresaId, string $codigo, array &$cache): int
    {
        if (isset($cache[$empresaId])) {
            return $cache[$empresaId];
        }

        $id = (int) (Cuentacontable::query()
            ->where('empresa_id', $empresaId)
            ->where('codigo', $codigo)
            ->value('id') ?? 0);
        $cache[$empresaId] = $id;

        return $id;
    }

    /**
     * @param  list<int>  $indicesErp
     */
    private function actualizarLineasAnita(string $codigoEmpresa, string $nroAsiento, array $indicesErp): int
    {
        $lineasAnita = $this->lineasCtamov($codigoEmpresa, $nroAsiento);
        $actualizadas = 0;
        $api = new ApiAnita;

        foreach ($indicesErp as $idx) {
            $anita = $lineasAnita[$idx] ?? null;
            if ($anita === null) {
                throw new RuntimeException('No se encontró línea ctamov #'.$idx.' para el asiento.');
            }

            $cuentaActual = (string) ($anita['ctav_cuenta'] ?? '');
            if ($cuentaActual === self::CODIGO_CUENTA_NUEVA) {
                continue;
            }

            if ($cuentaActual !== self::CODIGO_CUENTA_ANTIGUA) {
                throw new RuntimeException(
                    'Línea ctamov #'.$idx.' tiene cuenta '.$cuentaActual
                    .'; se esperaba '.self::CODIGO_CUENTA_ANTIGUA.'.',
                );
            }

            $lineaAnita = (string) ($anita['ctav_nro_linea'] ?? '');

            $respuesta = $api->apiCallEscritura([
                'acc' => 'update',
                'tabla' => 'ctamov',
                'sistema' => 'contab',
                'valores' => " ctav_cuenta = '".self::CODIGO_CUENTA_NUEVA."' ",
                'whereArmado' => " WHERE ctav_empresa = '".$codigoEmpresa
                    ."' AND ctav_nro_asiento = '".$nroAsiento
                    ."' AND ctav_nro_linea = '".$lineaAnita."' ",
            ], 'ctamov update cuenta dif caja cierre estacionamiento');

            if (! ApiAnita::respuestaBridgeEscrituraExitosa($respuesta)) {
                $err = ApiAnita::extraerMensajeError($respuesta) ?? trim($respuesta);
                throw new RuntimeException('Bridge Anita: '.($err !== '' ? $err : 'respuesta no exitosa'));
            }

            $actualizadas++;
        }

        return $actualizadas;
    }

    /**
     * @return array<int, array{ctav_nro_linea:string,ctav_cuenta:string}>
     */
    private function lineasCtamov(string $codigoEmpresa, string $nroAsiento): array
    {
        $api = new ApiAnita;
        $filas = ApiAnita::decodificarListaFilas($api->apiCall([
            'acc' => 'list',
            'tabla' => 'ctamov',
            'sistema' => 'contab',
            'campos' => 'ctav_nro_linea,ctav_cuenta',
            'whereArmado' => " WHERE ctav_empresa = '".$codigoEmpresa."' AND ctav_nro_asiento = '".$nroAsiento."'",
        ]));

        if ($filas === []) {
            throw new RuntimeException('No se encontraron líneas ctamov en Anita.');
        }

        usort($filas, static function ($a, $b): int {
            $a = is_array($a) ? $a : (array) $a;
            $b = is_array($b) ? $b : (array) $b;

            return ((int) ($a['ctav_nro_linea'] ?? 0)) <=> ((int) ($b['ctav_nro_linea'] ?? 0));
        });

        $out = [];
        foreach ($filas as $fila) {
            $row = is_array($fila) ? $fila : (array) $fila;
            $out[] = [
                'ctav_nro_linea' => (string) ($row['ctav_nro_linea'] ?? ''),
                'ctav_cuenta' => (string) ($row['ctav_cuenta'] ?? ''),
            ];
        }

        return $out;
    }
}
