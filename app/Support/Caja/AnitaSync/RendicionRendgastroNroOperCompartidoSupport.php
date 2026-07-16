<?php

declare(strict_types=1);

namespace App\Support\Caja\AnitaSync;

use App\ApiAnita;
use App\Models\Caja\RendicionEstacionamientoCaja;
use App\Models\Caja\RendicionEstacionamientoSecuenciaEmpresa;
use App\Models\Caja\RendicionGastronomiaCaja;
use App\Models\Caja\RendicionGastronomiaSecuenciaEmpresa;
use App\Support\Caja\RendicionEstacionamientoSecuenciaSupport;
use App\Support\Caja\RendicionGastronomiaSecuenciaSupport;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Numerador único rendg_nro_oper (850000+) para gastronomía y estacionamiento.
 *
 * Misma semilla global (3 empresas): max(Anita en rango, ERP gastro+estac en rango) + 1.
 * No incluye rendmaquina / rendbingo (rangos 600k / 700k).
 */
final class RendicionRendgastroNroOperCompartidoSupport
{
    public const LOCK_KEY = 'rendgastro.nro_oper.compartido';

    public const FUENTE_ANITA = 'anita';

    public const FUENTE_ERP = 'erp';

    public const FUENTE_COMBINADO = 'combinado';

    public const FUENTE_ERP_FALLBACK = 'erp_fallback';

    public static function piso(): int
    {
        return max(0, (int) config('rendicion_rendgastro_nro_oper.piso', 850000));
    }

    public static function techo(): int
    {
        return max(0, (int) config('rendicion_rendgastro_nro_oper.techo', 0));
    }

    public static function enRango(int $nroOper): bool
    {
        if ($nroOper <= 0) {
            return false;
        }

        $piso = self::piso();
        $techo = self::techo();

        if ($piso > 0 && $nroOper < $piso) {
            return false;
        }

        if ($techo > 0 && $nroOper >= $techo) {
            return false;
        }

        return true;
    }

    public static function filtroSqlAnita(string $columna = 'rendg_nro_oper'): string
    {
        $piso = self::piso();
        $techo = self::techo();
        $partes = '';

        if ($piso > 0) {
            $partes .= " AND {$columna} >= '".$piso."' ";
        }

        if ($techo > 0) {
            $partes .= " AND {$columna} < '".$techo."' ";
        }

        return $partes;
    }

    /**
     * MAX(rendg_nro_oper) en Anita dentro del rango dedicado (todas las empresas).
     */
    public static function ultimoNroOperEnAnita(
        ApiAnita $api,
        string $sistema,
        string $tablaCabecera,
        string $tipoOper,
    ): int {
        $where = " WHERE rendg_tipo_oper = '".$tipoOper."' "
            .self::filtroSqlAnita('rendg_nro_oper');

        $rows = ApiAnita::decodificarListaFilas($api->apiCall([
            'acc' => 'list',
            'sistema' => $sistema,
            'tabla' => $tablaCabecera,
            'campos' => 'rendg_nro_oper',
            'orderBy' => 'rendg_nro_oper desc',
            'whereArmado' => $where,
        ]));

        if ($rows === []) {
            return 0;
        }

        return max(0, (int) ($rows[0]->rendg_nro_oper ?? 0));
    }

    /**
     * MAX(nro_oper_anita / código / proximo_nro reservado) en ERP:
     * gastronomía + estacionamiento, rango dedicado.
     * Incluye proximo_nro de secuencias para no reutilizar un nro ya propuesto bajo lock.
     */
    public static function ultimoNroOperEnErp(): int
    {
        $piso = self::piso();
        $techo = self::techo();

        $maxGastro = self::maxNroOperTablaErp(RendicionGastronomiaCaja::class, $piso, $techo);
        $maxEstac = self::maxNroOperTablaErp(RendicionEstacionamientoCaja::class, $piso, $techo);
        $maxReservado = self::maxProximoNroReservado($piso, $techo);

        return max($maxGastro, $maxEstac, $maxReservado);
    }

    /**
     * Máximo proximo_nro ya propuesto (reserva) en secuencias gastro y estac.
     */
    private static function maxProximoNroReservado(int $piso, int $techo): int
    {
        $max = 0;
        foreach (
            [
                RendicionGastronomiaSecuenciaEmpresa::query()->max('proximo_nro'),
                RendicionEstacionamientoSecuenciaEmpresa::query()->max('proximo_nro'),
            ] as $valor
        ) {
            $n = (int) ($valor ?? 0);
            if ($n <= 0 || ! self::enRango($n)) {
                continue;
            }
            if ($piso > 0 && $n < $piso) {
                continue;
            }
            if ($techo > 0 && $n >= $techo) {
                continue;
            }
            if ($n > $max) {
                $max = $n;
            }
        }

        return $max;
    }

    /**
     * @return array{
     *   siguiente: int,
     *   fuente: string,
     *   ultimo_anita: int,
     *   ultimo_erp: int
     * }
     */
    public static function calcularSiguiente(?int $ultimoAnita, int $ultimoErp): array
    {
        return RendicionGastronomiaSecuenciaSupport::calcularSiguiente(
            $ultimoAnita,
            $ultimoErp,
            self::piso(),
            self::techo(),
        );
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public static function conLock(callable $callback): mixed
    {
        $segundos = max(1, (int) config('rendicion_rendgastro_nro_oper.lock_segundos', 15));

        try {
            return Cache::lock(self::LOCK_KEY, $segundos)->block($segundos, $callback);
        } catch (\Throwable $e) {
            Log::warning('rendgastro.nro_oper.lock_fallback', [
                'mensaje' => $e->getMessage(),
            ]);

            return $callback();
        }
    }

    /**
     * @param  class-string<RendicionGastronomiaCaja|RendicionEstacionamientoCaja>  $modelo
     */
    private static function maxNroOperTablaErp(string $modelo, int $piso, int $techo): int
    {
        $queryColumna = $modelo::query()->whereNotNull('nro_oper_anita');
        if ($piso > 0) {
            $queryColumna->where('nro_oper_anita', '>=', $piso);
        }
        if ($techo > 0) {
            $queryColumna->where('nro_oper_anita', '<', $techo);
        }
        $maxDesdeColumna = (int) ($queryColumna->max('nro_oper_anita') ?? 0);

        $maxDesdeCodigo = 0;
        $extraer = $modelo === RendicionEstacionamientoCaja::class
            ? [RendicionEstacionamientoSecuenciaSupport::class, 'extraerNroOperDesdeCodigo']
            : [RendicionGastronomiaSecuenciaSupport::class, 'extraerNroOperDesdeCodigo'];

        foreach ($modelo::query()->pluck('codigo') as $codigo) {
            $n = $extraer($codigo);
            if ($n === null || ! self::enRango($n)) {
                continue;
            }
            if ($n > $maxDesdeCodigo) {
                $maxDesdeCodigo = $n;
            }
        }

        return max($maxDesdeColumna, $maxDesdeCodigo);
    }
}
