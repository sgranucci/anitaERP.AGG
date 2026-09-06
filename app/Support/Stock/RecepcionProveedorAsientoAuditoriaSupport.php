<?php

namespace App\Support\Stock;

use App\ApiAnita;
use App\Models\Stock\Recepcion_Proveedor;
use Illuminate\Support\Collection;

/**
 * Compara asientos contables ERP ↔ ctamov Anita para recepciones COM.
 */
final class RecepcionProveedorAsientoAuditoriaSupport
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function lineasCtamovPorCom(Recepcion_Proveedor $recepcion): array
    {
        $clave = RecepcionProveedorAnitaClaveSupport::resolver($recepcion);
        $empresaCodigo = RecepcionProveedorAnitaClaveSupport::codigoEmpresaContabAnita($recepcion);

        return self::consultarCtamov(
            " WHERE ctav_empresa='".self::esc((string) $empresaCodigo)."'"
            ." AND ctav_tipo='".self::esc($clave['tipo'])."'"
            ." AND ctav_letra='".self::esc($clave['letra'])."'"
            .' AND ctav_sucursal='.(int) $clave['sucursal']
            .' AND ctav_nro='.(int) $clave['nro']
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function lineasCtamovPorNumeroAsiento(int $empresaCodigo, string $numeroAsiento): array
    {
        $numeroAsiento = trim($numeroAsiento);
        if ($empresaCodigo <= 0 || $numeroAsiento === '') {
            return [];
        }

        return self::consultarCtamov(
            " WHERE ctav_empresa='".self::esc((string) $empresaCodigo)."'"
            ." AND ctav_nro_asiento='".self::esc($numeroAsiento)."'"
        );
    }

    /**
     * @param  Collection<int, object>  $movimientos
     * @return list<array{cuenta: string, dh: string, importe: float, cc: int, moneda: string, cotizacion: float}>
     */
    public static function normalizarLineasErp(Collection $movimientos): array
    {
        $lineas = [];

        foreach ($movimientos as $mov) {
            $monto = round((float) ($mov->monto ?? 0), 2);
            if (abs($monto) < 0.001) {
                continue;
            }

            $lineas[] = [
                'cuenta' => (string) ($mov->cuentacontables->codigo ?? ''),
                'dh' => $monto > 0 ? 'D' : 'H',
                'importe' => round(abs($monto), 2),
                'cc' => (int) ($mov->centrocostos->codigo ?? 0),
                'moneda' => (string) ($mov->monedas->codigo ?? ''),
                'cotizacion' => round((float) ($mov->cotizacion ?? 0), 4),
            ];
        }

        usort($lineas, [self::class, 'ordenarLineaNormalizada']);

        return $lineas;
    }

    /**
     * @param  list<array<string, mixed>>  $filasCtamov
     * @return list<array{cuenta: string, dh: string, importe: float, cc: int, moneda: string, cotizacion: float}>
     */
    public static function normalizarLineasAnita(array $filasCtamov): array
    {
        $lineas = [];

        foreach ($filasCtamov as $fila) {
            $importe = round((float) ($fila['ctav_importe'] ?? 0), 2);
            if ($importe <= 0) {
                continue;
            }

            $lineas[] = [
                'cuenta' => trim((string) ($fila['ctav_cuenta'] ?? '')),
                'dh' => strtoupper(trim((string) ($fila['ctav_d_h'] ?? 'D'))) === 'H' ? 'H' : 'D',
                'importe' => $importe,
                'cc' => (int) ($fila['ctav_ccosto'] ?? 0),
                'moneda' => trim((string) ($fila['ctav_cod_mon'] ?? '')),
                'cotizacion' => round((float) ($fila['ctav_cotizacion'] ?? 0), 4),
            ];
        }

        usort($lineas, [self::class, 'ordenarLineaNormalizada']);

        return $lineas;
    }

    /**
     * @param  list<array{cuenta: string, dh: string, importe: float, cc: int, moneda: string, cotizacion: float}>  $erp
     * @param  list<array{cuenta: string, dh: string, importe: float, cc: int, moneda: string, cotizacion: float}>  $anita
     * @return list<string>
     */
    public static function diferenciasLineas(array $erp, array $anita, float $tol): array
    {
        if (count($erp) !== count($anita)) {
            return [sprintf('Cantidad de líneas distinta (ERP %d vs Anita %d).', count($erp), count($anita))];
        }

        $mensajes = [];
        foreach ($erp as $i => $lineaErp) {
            $lineaAnita = $anita[$i] ?? null;
            if ($lineaAnita === null) {
                continue;
            }

            if ($lineaErp['cuenta'] !== $lineaAnita['cuenta']) {
                $mensajes[] = sprintf('Línea %d: cuenta ERP %s vs Anita %s.', $i + 1, $lineaErp['cuenta'], $lineaAnita['cuenta']);
            }
            if ($lineaErp['dh'] !== $lineaAnita['dh']) {
                $mensajes[] = sprintf('Línea %d: debe/haber ERP %s vs Anita %s.', $i + 1, $lineaErp['dh'], $lineaAnita['dh']);
            }
            if (abs($lineaErp['importe'] - $lineaAnita['importe']) >= $tol) {
                $mensajes[] = sprintf(
                    'Línea %d: importe ERP %s vs Anita %s.',
                    $i + 1,
                    number_format($lineaErp['importe'], 2, ',', '.'),
                    number_format($lineaAnita['importe'], 2, ',', '.'),
                );
            }
            if ($lineaErp['cc'] !== $lineaAnita['cc']) {
                $mensajes[] = sprintf('Línea %d: centro de costo ERP %d vs Anita %d.', $i + 1, $lineaErp['cc'], $lineaAnita['cc']);
            }
            if ($lineaErp['moneda'] !== '' && $lineaAnita['moneda'] !== '' && $lineaErp['moneda'] !== $lineaAnita['moneda']) {
                $mensajes[] = sprintf('Línea %d: moneda ERP %s vs Anita %s.', $i + 1, $lineaErp['moneda'], $lineaAnita['moneda']);
            }
            if (abs($lineaErp['cotizacion'] - $lineaAnita['cotizacion']) >= max($tol, 0.0001)) {
                $mensajes[] = sprintf(
                    'Línea %d: cotización ERP %s vs Anita %s.',
                    $i + 1,
                    number_format($lineaErp['cotizacion'], 4, ',', '.'),
                    number_format($lineaAnita['cotizacion'], 4, ',', '.'),
                );
            }
        }

        return $mensajes;
    }

    /**
     * @param  list<array<string, mixed>>  $filasCtamov
     * @return array{debe: float, haber: float, lineas: int}
     */
    public static function totalesDesdeCtamov(array $filasCtamov): array
    {
        $debe = 0.0;
        $haber = 0.0;

        foreach ($filasCtamov as $fila) {
            $importe = (float) ($fila['ctav_importe'] ?? 0);
            $dh = strtoupper(trim((string) ($fila['ctav_d_h'] ?? 'D')));
            if ($dh === 'H') {
                $haber += $importe;
            } else {
                $debe += $importe;
            }
        }

        return [
            'debe' => round($debe, 2),
            'haber' => round($haber, 2),
            'lineas' => count($filasCtamov),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $filasCtamov
     * @return list<string>
     */
    public static function validarCabeceraCtamov(
        Recepcion_Proveedor $recepcion,
        array $filasCtamov,
        string $numeroAsientoErp,
        string $fechaAsientoErp,
        int $empresaCodigo,
    ): array {
        if ($filasCtamov === []) {
            return [];
        }

        $clave = RecepcionProveedorAnitaClaveSupport::resolver($recepcion);
        $fechaEsperada = str_replace('-', '', substr($fechaAsientoErp, 0, 10));
        $mensajes = [];

        $primera = $filasCtamov[0];
        $nroAsientoAnita = trim((string) ($primera['ctav_nro_asiento'] ?? ''));
        if ($nroAsientoAnita !== '' && $numeroAsientoErp !== '' && $nroAsientoAnita !== $numeroAsientoErp) {
            $mensajes[] = 'Número de asiento ERP '.$numeroAsientoErp.' vs ctamov '.$nroAsientoAnita.'.';
        }

        $empresaAnita = trim((string) ($primera['ctav_empresa'] ?? ''));
        if ($empresaAnita !== '' && (string) $empresaCodigo !== $empresaAnita) {
            $mensajes[] = 'Empresa ctamov '.$empresaAnita.' vs ERP '.$empresaCodigo.'.';
        }

        foreach ($filasCtamov as $fila) {
            $fechaAnita = trim((string) ($fila['ctav_fecha'] ?? ''));
            if ($fechaEsperada !== '' && $fechaAnita !== '' && $fechaAnita !== $fechaEsperada) {
                $mensajes[] = 'Fecha ctamov '.$fechaAnita.' distinta de asiento ERP '.$fechaEsperada.'.';
                break;
            }

            $tipo = trim((string) ($fila['ctav_tipo'] ?? ''));
            $letra = trim((string) ($fila['ctav_letra'] ?? ''));
            $sucursal = (int) ($fila['ctav_sucursal'] ?? 0);
            $nro = (int) ($fila['ctav_nro'] ?? 0);

            // Numeración COM obligatoria en ctamov: vacío/cero no es OK (antes se omitía y la
            // auditoría marcaba OK al encontrar el asiento solo por ctav_nro_asiento).
            if ($tipo === '' || $tipo !== $clave['tipo']) {
                $mensajes[] = $tipo === ''
                    ? 'Numeración COM ausente en ctamov (tipo vacío; esperado '.$clave['tipo'].' '
                        .$clave['letra'].' '.$clave['sucursal'].' '.$clave['nro'].').'
                    : 'Numeración COM: tipo ctamov '.$tipo.' vs esperado '.$clave['tipo'].'.';
                break;
            }
            if ($letra === '' || $letra !== $clave['letra']) {
                $mensajes[] = $letra === ''
                    ? 'Numeración COM ausente en ctamov (letra vacía; esperada '.$clave['letra'].').'
                    : 'Numeración COM: letra ctamov '.$letra.' vs esperado '.$clave['letra'].'.';
                break;
            }
            if ($sucursal <= 0 || $sucursal !== (int) $clave['sucursal']) {
                $mensajes[] = $sucursal <= 0
                    ? 'Numeración COM ausente en ctamov (sucursal vacía; esperada '.$clave['sucursal'].').'
                    : 'Numeración COM: sucursal ctamov '.$sucursal.' vs esperado '.$clave['sucursal'].'.';
                break;
            }
            if ($nro <= 0 || $nro !== (int) $clave['nro']) {
                $mensajes[] = $nro <= 0
                    ? 'Numeración COM ausente en ctamov (número vacío; esperado '.$clave['nro'].').'
                    : 'Numeración COM: número ctamov '.$nro.' vs esperado '.$clave['nro'].'.';
                break;
            }
        }

        return $mensajes;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function consultarCtamov(string $whereArmado): array
    {
        $api = new ApiAnita;
        $raw = $api->apiCall([
            'acc' => 'list',
            'sistema' => 'contab',
            'tabla' => 'ctamov',
            'campos' => implode(',', [
                'ctav_empresa',
                'ctav_nro_asiento',
                'ctav_nro_linea',
                'ctav_d_h',
                'ctav_cuenta',
                'ctav_fecha',
                'ctav_tipo',
                'ctav_letra',
                'ctav_sucursal',
                'ctav_nro',
                'ctav_importe',
                'ctav_cotizacion',
                'ctav_cod_mon',
                'ctav_ccosto',
                'ctav_usuario_umod',
                'ctav_fecha_umod',
                'ctav_hora_umod',
            ]),
            'whereArmado' => $whereArmado,
        ]);

        $filas = ApiAnita::decodificarListaFilas($raw);
        if ($filas === []) {
            return [];
        }

        $normalizadas = [];
        foreach ($filas as $fila) {
            $normalizadas[] = is_array($fila) ? $fila : get_object_vars($fila);
        }

        usort($normalizadas, static function (array $a, array $b): int {
            return ((int) ($a['ctav_nro_linea'] ?? 0)) <=> ((int) ($b['ctav_nro_linea'] ?? 0));
        });

        return $normalizadas;
    }

    private static function esc(string $valor): string
    {
        return str_replace("'", "''", $valor);
    }

    /**
     * @param  array{cuenta: string, dh: string, importe: float, cc: int, moneda: string, cotizacion: float}  $a
     * @param  array{cuenta: string, dh: string, importe: float, cc: int, moneda: string, cotizacion: float}  $b
     */
    private static function ordenarLineaNormalizada(array $a, array $b): int
    {
        return [$a['cuenta'], $a['dh'], $a['importe'], $a['cc'], $a['moneda']]
            <=> [$b['cuenta'], $b['dh'], $b['importe'], $b['cc'], $b['moneda']];
    }
}
