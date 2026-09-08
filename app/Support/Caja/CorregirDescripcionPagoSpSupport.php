<?php

declare(strict_types=1);

namespace App\Support\Caja;

use App\ApiAnita;
use App\Models\Configuracion\Empresa;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Backfill: reemplaza «Pago SP N» por el detalle de la solicitud de pago
 * en ERP (asiento / asiento_movimiento / caja_movimiento) y en Anita ctamov.
 */
final class CorregirDescripcionPagoSpSupport
{
    /**
     * @return array{
     *   candidatos: int,
     *   erp_asiento: int,
     *   erp_movimiento: int,
     *   erp_caja: int,
     *   ctamov_asientos: int,
     *   ctamov_lineas: int,
     *   omitidos_sin_detalle: int,
     *   omitidos_sin_ctamov: int,
     *   errores: list<string>
     * }
     */
    public function ejecutar(bool $dryRun = false, ?int $empresaId = null): array
    {
        $resultado = [
            'candidatos' => 0,
            'erp_asiento' => 0,
            'erp_movimiento' => 0,
            'erp_caja' => 0,
            'ctamov_asientos' => 0,
            'ctamov_lineas' => 0,
            'omitidos_sin_detalle' => 0,
            'omitidos_sin_ctamov' => 0,
            'errores' => [],
        ];

        $filas = $this->candidatos($empresaId);
        $resultado['candidatos'] = count($filas);

        foreach ($filas as $fila) {
            $detalleErp = IngresoEgresoSolicitudpagoSupport::descripcionMovimientoDesdeSp((object) [
                'id' => (int) $fila->sp_id,
                'codigo' => (string) $fila->sp_codigo,
                'detalle' => (string) $fila->sp_detalle,
            ]);

            if ($detalleErp === '' || IngresoEgresoSolicitudpagoSupport::esDescripcionGenericaPagoSp($detalleErp)) {
                $resultado['omitidos_sin_detalle']++;

                continue;
            }

            $detalleCtamov = $this->descripcionCtamov($detalleErp);

            try {
                if (! $dryRun) {
                    $resultado['erp_asiento'] += $this->actualizarAsientoErp((int) $fila->asiento_id, $detalleErp);
                    $resultado['erp_movimiento'] += $this->actualizarMovimientosErp((int) $fila->asiento_id, $detalleErp);
                    if ((int) ($fila->caja_movimiento_id ?? 0) > 0) {
                        $resultado['erp_caja'] += $this->actualizarCajaErp((int) $fila->caja_movimiento_id, $detalleErp);
                    }
                } else {
                    if ($this->necesitaUpdateTexto((string) $fila->observacion, $detalleErp)) {
                        $resultado['erp_asiento']++;
                    }
                }

                $nroAnita = (int) ($fila->anita_nro_asiento ?: $fila->numeroasiento);
                $codigoEmpresa = trim((string) $fila->empresa_codigo);
                if ($nroAnita <= 0 || $codigoEmpresa === '') {
                    $resultado['omitidos_sin_ctamov']++;

                    continue;
                }

                $lineas = $this->lineasCtamovPagoSp($codigoEmpresa, (string) $nroAnita);
                if ($lineas === []) {
                    $resultado['omitidos_sin_ctamov']++;

                    continue;
                }

                if ($dryRun) {
                    $resultado['ctamov_asientos']++;
                    $resultado['ctamov_lineas'] += count($lineas);

                    continue;
                }

                $upd = $this->actualizarLineasCtamov($codigoEmpresa, (string) $nroAnita, $lineas, $detalleCtamov);
                if ($upd > 0) {
                    $resultado['ctamov_asientos']++;
                    $resultado['ctamov_lineas'] += $upd;
                }
            } catch (\Throwable $e) {
                $resultado['errores'][] = 'Asiento ERP #'.$fila->asiento_id
                    .' (Anita '.$fila->numeroasiento.'): '.$e->getMessage();
            }
        }

        // Movimientos huérfanos «Pago SP N» sin solicitudpago_id en cabecera.
        $extra = $this->corregirMovimientosHuerfanos($dryRun, $empresaId);
        $resultado['erp_movimiento'] += $extra['movimiento'];
        $resultado['ctamov_asientos'] += $extra['ctamov_asientos'];
        $resultado['ctamov_lineas'] += $extra['ctamov_lineas'];
        foreach ($extra['errores'] as $err) {
            $resultado['errores'][] = $err;
        }

        return $resultado;
    }

    /**
     * @return list<object>
     */
    private function candidatos(?int $empresaId): array
    {
        $q = DB::table('asiento as a')
            ->join('solicitudpago as sp', 'sp.id', '=', 'a.solicitudpago_id')
            ->join('empresa as e', 'e.id', '=', 'a.empresa_id')
            ->leftJoin('caja_movimiento as cm', 'cm.id', '=', 'a.caja_movimiento_id')
            ->whereNotNull('a.solicitudpago_id')
            ->orderBy('a.empresa_id')
            ->orderBy('a.id')
            ->select([
                'a.id as asiento_id',
                'a.empresa_id',
                'e.codigo as empresa_codigo',
                'a.numeroasiento',
                'a.anita_nro_asiento',
                'a.observacion',
                'a.caja_movimiento_id',
                'sp.id as sp_id',
                'sp.codigo as sp_codigo',
                'sp.detalle as sp_detalle',
                'cm.detalle as cm_detalle',
            ]);

        if ($empresaId !== null && $empresaId > 0) {
            $q->where('a.empresa_id', $empresaId);
        }

        return $q->get()->all();
    }

    private function actualizarAsientoErp(int $asientoId, string $detalle): int
    {
        $actual = (string) (DB::table('asiento')->where('id', $asientoId)->value('observacion') ?? '');
        if (! $this->necesitaUpdateTexto($actual, $detalle)) {
            return 0;
        }

        return DB::table('asiento')->where('id', $asientoId)->update(['observacion' => $detalle]);
    }

    private function actualizarMovimientosErp(int $asientoId, string $detalle): int
    {
        $n = 0;
        $movs = DB::table('asiento_movimiento')->where('asiento_id', $asientoId)->get(['id', 'observacion']);
        foreach ($movs as $mov) {
            $obs = (string) ($mov->observacion ?? '');
            if (! $this->necesitaUpdateTexto($obs, $detalle)) {
                continue;
            }
            $n += DB::table('asiento_movimiento')->where('id', $mov->id)->update(['observacion' => $detalle]);
        }

        return $n;
    }

    private function actualizarCajaErp(int $cajaMovimientoId, string $detalle): int
    {
        $actual = (string) (DB::table('caja_movimiento')->where('id', $cajaMovimientoId)->value('detalle') ?? '');
        if (! $this->necesitaUpdateTexto($actual, $detalle)) {
            return 0;
        }

        return DB::table('caja_movimiento')->where('id', $cajaMovimientoId)->update(['detalle' => $detalle]);
    }

    private function necesitaUpdateTexto(string $actual, string $detalle): bool
    {
        $actual = trim($actual);
        if ($actual === $detalle) {
            return false;
        }

        return $actual === ''
            || IngresoEgresoSolicitudpagoSupport::esDescripcionGenericaPagoSp($actual);
    }

    /**
     * @return list<array{ctav_nro_linea: string, ctav_desc_mov: string}>
     */
    private function lineasCtamovPagoSp(string $codigoEmpresa, string $nroAsiento): array
    {
        $api = new ApiAnita;
        $filas = ApiAnita::decodificarListaFilas($api->apiCall([
            'acc' => 'list',
            'tabla' => 'ctamov',
            'sistema' => 'contab',
            'campos' => 'ctav_nro_linea,ctav_desc_mov',
            'whereArmado' => " WHERE ctav_empresa = '".$codigoEmpresa."' AND ctav_nro_asiento = '".$nroAsiento."'",
        ]));

        $out = [];
        foreach ($filas as $fila) {
            $row = is_array($fila) ? $fila : (array) $fila;
            $desc = trim((string) ($row['ctav_desc_mov'] ?? ''));
            if (! IngresoEgresoSolicitudpagoSupport::esDescripcionGenericaPagoSp($desc)) {
                continue;
            }
            $out[] = [
                'ctav_nro_linea' => (string) ($row['ctav_nro_linea'] ?? ''),
                'ctav_desc_mov' => $desc,
            ];
        }

        return $out;
    }

    /**
     * @param  list<array{ctav_nro_linea: string, ctav_desc_mov: string}>  $lineas
     */
    private function actualizarLineasCtamov(
        string $codigoEmpresa,
        string $nroAsiento,
        array $lineas,
        string $descNueva,
    ): int {
        $api = new ApiAnita;
        $actualizadas = 0;
        $descSql = str_replace("'", "''", $descNueva);

        foreach ($lineas as $fila) {
            $linea = $fila['ctav_nro_linea'];
            if ($linea === '' || $fila['ctav_desc_mov'] === $descNueva) {
                continue;
            }

            $respuesta = $api->apiCallEscritura([
                'acc' => 'update',
                'tabla' => 'ctamov',
                'sistema' => 'contab',
                'valores' => " ctav_desc_mov = '".$descSql."' ",
                'whereArmado' => " WHERE ctav_empresa = '".$codigoEmpresa
                    ."' AND ctav_nro_asiento = '".$nroAsiento
                    ."' AND ctav_nro_linea = '".$linea."' ",
            ], 'ctamov update descripcion Pago SP', exigirFilasAfectadas: true);

            if (! ApiAnita::respuestaBridgeEscrituraExitosa($respuesta)) {
                $err = ApiAnita::extraerMensajeError($respuesta) ?? trim($respuesta);
                throw new RuntimeException('Bridge Anita: '.($err !== '' ? $err : 'respuesta no exitosa'));
            }

            $actualizadas++;
        }

        return $actualizadas;
    }

    /**
     * ctav_desc_mov Informix: 30 chars, sin caracteres que rompan el bridge.
     */
    public function descripcionCtamov(string $detalleErp): string
    {
        $texto = str_replace(['/', '\\', '|', '"', "'"], ' ', $detalleErp);
        $limpio = preg_replace('/[^A-Za-z0-9 .%\-]+/u', '', $texto) ?? '';
        $limpio = trim((string) preg_replace('/\s+/u', ' ', $limpio));

        return mb_substr($limpio, 0, 30);
    }

    /**
     * @return array{movimiento: int, ctamov_asientos: int, ctamov_lineas: int, errores: list<string>}
     */
    private function corregirMovimientosHuerfanos(bool $dryRun, ?int $empresaId): array
    {
        $out = ['movimiento' => 0, 'ctamov_asientos' => 0, 'ctamov_lineas' => 0, 'errores' => []];

        $q = DB::table('asiento_movimiento as am')
            ->join('asiento as a', 'a.id', '=', 'am.asiento_id')
            ->join('empresa as e', 'e.id', '=', 'a.empresa_id')
            ->where('am.observacion', 'like', 'Pago SP%')
            ->select([
                'am.id as mov_id',
                'am.observacion',
                'a.id as asiento_id',
                'a.numeroasiento',
                'a.anita_nro_asiento',
                'a.solicitudpago_id',
                'e.codigo as empresa_codigo',
            ]);
        if ($empresaId !== null && $empresaId > 0) {
            $q->where('a.empresa_id', $empresaId);
        }

        foreach ($q->get() as $fila) {
            if (! preg_match('/^Pago SP\s+(\d+)/i', (string) $fila->observacion, $m)) {
                continue;
            }
            $codigoSp = $m[1];
            $sp = DB::table('solicitudpago')->where('codigo', $codigoSp)->first(['id', 'codigo', 'detalle']);
            if ($sp === null) {
                continue;
            }
            $detalle = IngresoEgresoSolicitudpagoSupport::descripcionMovimientoDesdeSp($sp);
            if ($detalle === '' || IngresoEgresoSolicitudpagoSupport::esDescripcionGenericaPagoSp($detalle)) {
                continue;
            }

            try {
                if (! $dryRun) {
                    $out['movimiento'] += DB::table('asiento_movimiento')
                        ->where('id', $fila->mov_id)
                        ->update(['observacion' => $detalle]);
                } else {
                    $out['movimiento']++;
                }

                $nroAnita = (int) ($fila->anita_nro_asiento ?: $fila->numeroasiento);
                $codigoEmpresa = trim((string) $fila->empresa_codigo);
                if ($nroAnita <= 0 || $codigoEmpresa === '') {
                    continue;
                }
                $lineas = $this->lineasCtamovPagoSp($codigoEmpresa, (string) $nroAnita);
                if ($lineas === []) {
                    continue;
                }
                if ($dryRun) {
                    $out['ctamov_asientos']++;
                    $out['ctamov_lineas'] += count($lineas);

                    continue;
                }
                $upd = $this->actualizarLineasCtamov(
                    $codigoEmpresa,
                    (string) $nroAnita,
                    $lineas,
                    $this->descripcionCtamov($detalle),
                );
                if ($upd > 0) {
                    $out['ctamov_asientos']++;
                    $out['ctamov_lineas'] += $upd;
                }
            } catch (\Throwable $e) {
                $out['errores'][] = 'Mov huérfano #'.$fila->mov_id.': '.$e->getMessage();
            }
        }

        return $out;
    }
}
