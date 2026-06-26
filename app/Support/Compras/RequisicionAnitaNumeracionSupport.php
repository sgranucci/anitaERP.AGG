<?php

namespace App\Support\Compras;

use App\ApiAnita;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Numeración de requisiciones de compras alineada a Anita:
 * max(ERP, reqmae, numabm shared código 21) + hueco libre; actualiza numa_ult_numero.
 */
final class RequisicionAnitaNumeracionSupport
{
    public static function sistemaShared(): string
    {
        return (string) config('requisicion.anita.sistema_shared', 'shared');
    }

    public static function codigoNumerador(): int
    {
        return (int) config('requisicion.anita.numerador.codigo', 21);
    }

    public static function whereArmadoNumabm(): string
    {
        $cfg = config('requisicion.anita.numerador', []);
        $codigo = (int) ($cfg['codigo'] ?? 21);

        if ($codigo > 0) {
            return ' WHERE numa_codigo = '.$codigo;
        }

        $sistema = self::escSqlLiteral((string) ($cfg['sistema_abm'] ?? 'compras'));
        $programa = self::escSqlLiteral((string) ($cfg['programa'] ?? 'a-reqmae.c'));
        $referencia = self::escSqlLiteral((string) ($cfg['referencia'] ?? '1'));

        return " WHERE numa_sistema='{$sistema}' AND numa_programa='{$programa}' AND numa_referencia='{$referencia}'";
    }

    public static function leerUltimoNumeroNumabm(): int
    {
        $api = new ApiAnita;
        $raw = $api->apiCallEscritura([
            'acc' => 'list',
            'sistema' => self::sistemaShared(),
            'tabla' => (string) config('requisicion.anita.numerador.tabla', 'numabm'),
            'campos' => 'numa_ult_numero',
            'whereArmado' => self::whereArmadoNumabm(),
        ], 'requisicion numabm lectura');

        $err = ApiAnita::extraerMensajeError($raw);
        if ($err !== null) {
            throw new \RuntimeException('No se pudo leer numabm Anita (código '.self::codigoNumerador().'): '.$err);
        }

        $fila = ApiAnita::primeraFilaLista((string) $raw);
        if ($fila === null || ! isset($fila->numa_ult_numero)) {
            throw new \RuntimeException(
                'numabm inexistente o sin numa_ult_numero (código '.self::codigoNumerador().').'
            );
        }

        return max(0, (int) $fila->numa_ult_numero);
    }

    public static function actualizarNumeradorNumabm(int $numero): void
    {
        if ($numero <= 0) {
            throw new \InvalidArgumentException('Número de requisición Anita inválido.');
        }

        $api = new ApiAnita;
        $raw = $api->apiCallEscritura([
            'acc' => 'update',
            'sistema' => self::sistemaShared(),
            'tabla' => (string) config('requisicion.anita.numerador.tabla', 'numabm'),
            'valores' => 'numa_ult_numero = '.(int) $numero,
            'whereArmado' => self::whereArmadoNumabm(),
        ], 'requisicion numabm update');

        $err = ApiAnita::extraerMensajeError($raw);
        if ($err !== null) {
            throw new \RuntimeException('No se pudo actualizar numabm Anita (código '.self::codigoNumerador().'): '.$err);
        }
    }

    /** Máximo global: ERP + reqmae + numabm (numerador único Anita, todas las empresas). */
    public static function ultimoNumeroGlobal(): int
    {
        $maxErp = (int) DB::table('requisicion')->max('numerorequisicion');

        $maxReqmae = 0;
        try {
            $maxReqmae = RequisicionAnitaColisionSupport::maxNumeroReqmaeGlobal();
        } catch (\Throwable $e) {
            Log::warning('RequisicionAnitaNumeracion: no se pudo leer max reqmae', [
                'error' => $e->getMessage(),
            ]);
        }

        $maxNumerador = 0;
        try {
            $maxNumerador = self::leerUltimoNumeroNumabm();
        } catch (\Throwable $e) {
            Log::warning('RequisicionAnitaNumeracion: no se pudo leer numabm', [
                'error' => $e->getMessage(),
            ]);
        }

        return max($maxErp, $maxReqmae, $maxNumerador);
    }

    /**
     * Alinea numabm (código 21) con max(ERP, reqmae, numerador actual).
     *
     * @return array{antes: int, despues: int, numa_codigo: int, max_erp: int, max_reqmae: int}
     */
    public static function sincronizarNumeradorGlobal(): array
    {
        $antes = self::leerUltimoNumeroNumabm();
        $maxErp = (int) DB::table('requisicion')->max('numerorequisicion');
        $maxReqmae = RequisicionAnitaColisionSupport::maxNumeroReqmaeGlobal();
        $despues = max($maxErp, $maxReqmae, $antes);

        if ($despues > $antes) {
            self::actualizarNumeradorNumabm($despues);
        }

        Log::info('RequisicionAnitaNumeracion: numabm sincronizado', [
            'numa_codigo' => self::codigoNumerador(),
            'antes' => $antes,
            'despues' => $despues,
            'max_erp' => $maxErp,
            'max_reqmae' => $maxReqmae,
        ]);

        return [
            'antes' => $antes,
            'despues' => $despues,
            'numa_codigo' => self::codigoNumerador(),
            'max_erp' => $maxErp,
            'max_reqmae' => $maxReqmae,
        ];
    }

    /** Tras asignar numerorequisicion en ERP, deja numabm ≥ max(ERP, reqmae, número asignado). */
    public static function registrarNumeroAsignadoEnNumerador(int $numero): void
    {
        if ($numero <= 0) {
            return;
        }

        try {
            $ultimoNumerador = self::leerUltimoNumeroNumabm();
            $maxErp = (int) DB::table('requisicion')->max('numerorequisicion');
            $maxReqmae = 0;
            try {
                $maxReqmae = RequisicionAnitaColisionSupport::maxNumeroReqmaeGlobal();
            } catch (\Throwable $e) {
                Log::warning('RequisicionAnitaNumeracion: no se pudo leer max reqmae al registrar', [
                    'numero' => $numero,
                    'error' => $e->getMessage(),
                ]);
            }

            $objetivo = max($numero, $ultimoNumerador, $maxErp, $maxReqmae);
            if ($objetivo > $ultimoNumerador) {
                self::actualizarNumeradorNumabm($objetivo);
            }
        } catch (\Throwable $e) {
            Log::warning('RequisicionAnitaNumeracion: no se pudo actualizar numabm tras asignar', [
                'numero' => $numero,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Reserva el siguiente número en numabm (opcional) y devuelve el valor asignado.
     *
     * @param  int|null  $ultimoErpGlobal  Máximo numerorequisicion ya usado en ERP.
     */
    public static function reservarSiguienteNumero(?int $ultimoErpGlobal = null): int
    {
        $ultimoAnita = self::leerUltimoNumeroNumabm();
        $base = $ultimoAnita;
        if ($ultimoErpGlobal !== null && $ultimoErpGlobal > $base) {
            $base = $ultimoErpGlobal;
        }

        $siguiente = $base + 1;
        self::actualizarNumeradorNumabm($siguiente);

        Log::info('RequisicionAnitaNumeracion: número reservado en numabm', [
            'numa_codigo' => self::codigoNumerador(),
            'ultimo_anita' => $ultimoAnita,
            'ultimo_erp' => $ultimoErpGlobal,
            'asignado' => $siguiente,
        ]);

        return $siguiente;
    }

    /**
     * Primer número libre (ERP + reqmae) y avance de numabm shared (código 21).
     *
     * @param  int|null  $excluirRequisicionId  Al renumerar borrador, excluir la fila actual en ERP.
     * @param  int|null  $pisoMinimo  Mínimo para max(base global, piso) antes de buscar hueco.
     */
    public static function asignarNumeroGlobalLibre(?int $excluirRequisicionId = null, ?int $pisoMinimo = null): int
    {
        $base = self::ultimoNumeroGlobal();
        if ($pisoMinimo !== null && $pisoMinimo > $base) {
            $base = $pisoMinimo;
        }

        $numero = RequisicionAnitaColisionSupport::primerNumeroDisponible($base + 1, $excluirRequisicionId);

        if (config('requisicion.anita.reservar_numerador_anita', false)) {
            try {
                $numeroReservado = self::reservarSiguienteNumero($base > 0 ? $base : null);
                if ($numeroReservado > $numero) {
                    $numero = RequisicionAnitaColisionSupport::primerNumeroDisponible(
                        $numeroReservado,
                        $excluirRequisicionId
                    );
                }
            } catch (\Throwable $e) {
                Log::warning('RequisicionAnitaNumeracion: reserva numabm no disponible', [
                    'base' => $base,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        self::registrarNumeroAsignadoEnNumerador($numero);

        return $numero;
    }

    public static function siguienteNumero(): int
    {
        return self::asignarNumeroGlobalLibre();
    }

    private static function escSqlLiteral(string $value): string
    {
        return str_replace("'", "''", trim($value));
    }
}
