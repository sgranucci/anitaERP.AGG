<?php

declare(strict_types=1);

namespace App\Support\Contable\ConciliacionBancaria;

use App\ApiAnita;
use App\Models\Caja\Cuentacaja;
use App\Support\Caja\AnitaSync\CobranzaAnitaCheBanEsquemaSupport;
use App\Support\Caja\IngresoEgresoAnitaTesmovSupport;
use App\Support\Contable\Sicore\SicoreEmpresaAnitaSupport;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Transferencias (TRA del ERP = TED de Anita) que Anita ya dio por conciliadas en tesorería
 * (`tesmov.tesv_conciliado = '*'`) y que el ERP no pudo cruzar contra una línea de banco.
 *
 * Anita marca la conciliación sobre el movimiento de tesorería sin exigir línea de extracto. El ERP
 * exige la línea de Interbanking. Cuando esa línea no llegó, la transferencia quedaba «sin conciliar»
 * en el ERP y «conciliada» en Anita. Esta marca solo SACA del pendiente lo que el matcher no
 * pudo cruzar: no inventa un par, no escribe en ninguna base y no toca lo que sí tiene banco.
 *
 * El enlace con Anita es el número que Anita antepone a la descripción: «TRA 2398 Trf bi bank a Macro».
 */
final class ConciliacionBancariaMarcaAnitaSupport
{
    /**
     * @param  list<array<string, mixed>>  $pendientesContables
     * @return list<array<string, mixed>>
     */
    public static function quitarTransferenciasConciliadasEnAnita(
        array $pendientesContables,
        Cuentacaja $cuentacaja,
        int $empresaId,
    ): array {
        $candidatas = [];
        $desdeYmd = 0;
        foreach ($pendientesContables as $i => $mov) {
            if (strtoupper(trim((string) ($mov['tipo_comp'] ?? ''))) !== 'TRA') {
                continue;
            }
            $nro = self::numeroTransferencia($mov);
            if ($nro <= 0) {
                continue;
            }
            $candidatas[$i] = $nro;
            $fecha = (int) preg_replace('/\D/', '', (string) ($mov['fecha'] ?? ''));
            if ($fecha > 0 && ($desdeYmd === 0 || $fecha < $desdeYmd)) {
                $desdeYmd = $fecha;
            }
        }

        if ($candidatas === []) {
            return $pendientesContables;
        }

        $codigo = trim((string) ($cuentacaja->codigo ?? ''));
        if ($codigo === '') {
            return $pendientesContables;
        }

        $conciliadasAnita = self::leerTedConciliadosAnita($codigo, $empresaId, $desdeYmd);
        if ($conciliadasAnita === []) {
            return $pendientesContables;
        }

        $quitar = [];
        foreach ($candidatas as $i => $nro) {
            $importe = self::importeAbs($pendientesContables[$i]);
            $clave = $nro.'|'.number_format($importe, 2, '.', '');
            if (($conciliadasAnita[$clave] ?? 0) > 0) {
                $conciliadasAnita[$clave]--;
                $quitar[$i] = true;
            }
        }

        if ($quitar === []) {
            return $pendientesContables;
        }

        return array_values(array_filter(
            $pendientesContables,
            static fn ($_, $i) => ! isset($quitar[$i]),
            ARRAY_FILTER_USE_BOTH,
        ));
    }

    /**
     * @param  array<string, mixed>  $mov
     */
    private static function numeroTransferencia(array $mov): int
    {
        return (int) preg_replace('/\D/', '', (string) ($mov['nro'] ?? ''));
    }

    /**
     * @param  array<string, mixed>  $mov
     */
    private static function importeAbs(array $mov): float
    {
        return abs((float) ($mov['debe'] ?? 0) - (float) ($mov['haber'] ?? 0));
    }

    /**
     * @return array<string, int> clave «nro|importe» → cantidad de TED conciliados en Anita
     */
    private static function leerTedConciliadosAnita(string $codigoCuentacaja, int $empresaId, int $desdeYmd): array
    {
        try {
            $empresaAnita = SicoreEmpresaAnitaSupport::codigoEmpresaAnita($empresaId);
            $raw = (new ApiAnita)->apiCall([
                'acc' => 'list',
                'sistema' => IngresoEgresoAnitaTesmovSupport::sistema(),
                'tabla' => 'tesmov',
                'campos' => 'tesv_importe,tesv_desc_mov',
                'whereArmado' => " WHERE tesv_cuenta = '".str_pad($codigoCuentacaja, 8, '0', STR_PAD_LEFT)."'"
                    ." AND tesv_tipo = 'TED' AND tesv_conciliado = '*'"
                    .($desdeYmd > 0 ? ' AND tesv_fecha_mov >= '.$desdeYmd : '')
                    .CobranzaAnitaCheBanEsquemaSupport::andFiltroEmpresaTesmov($empresaAnita),
            ]);

            $parsed = ApiAnita::parsearRespuestaLista(is_string($raw) ? $raw : null);
            if ($parsed['error_lectura'] !== null) {
                Log::warning('conciliacion_bancaria.marca_anita_lectura', [
                    'cuentacaja' => $codigoCuentacaja,
                    'error' => $parsed['error_lectura'],
                ]);

                return [];
            }

            $conteo = [];
            foreach ($parsed['filas'] as $fila) {
                if (! preg_match('/^\s*TRA\s+(\d+)\b/i', (string) ($fila->tesv_desc_mov ?? ''), $m)) {
                    continue;
                }
                $clave = ((int) $m[1]).'|'.number_format(abs((float) ($fila->tesv_importe ?? 0)), 2, '.', '');
                $conteo[$clave] = ($conteo[$clave] ?? 0) + 1;
            }

            return $conteo;
        } catch (Throwable $e) {
            Log::warning('conciliacion_bancaria.marca_anita_excepcion', [
                'cuentacaja' => $codigoCuentacaja,
                'mensaje' => $e->getMessage(),
            ]);

            return [];
        }
    }
}
