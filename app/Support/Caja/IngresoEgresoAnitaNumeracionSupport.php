<?php

namespace App\Support\Caja;

use App\ApiAnita;
use App\Models\Caja\Tipotransaccion_Caja;
use App\Support\Compras\AnitaSync\Pagoproveedor\PagoproveedorAnitaNumeracionSupport;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Configuracion\SistemaNumeradorSupport;
use App\Support\Contable\Sicore\SicoreEmpresaAnitaSupport;
use Illuminate\Support\Facades\Log;

/**
 * Numeración IE (OPP/OPA/EGR/ING/TRA) alineada a Anita ventas.numerador (num_clave por empresa).
 *
 * Semillas default (num_clave AGG):
 * - OPP: 223/224/225 (emp 1/2/3) — misma semilla que OP MultiEmpresa O1/O2/O3
 * - OPA MultiEmpresa: mismas claves que OPP (pago.c nro_op usa O{nroemp}; pag_tipo diferencia)
 * - EGR: 361/362/363
 * - ING: 346/347/348
 * - TRA: 334/335/336
 *
 * Solo Ferli (no usa esas semillas):
 * - OPP: t_comp OPP
 * - OPA: t_comp OPA (pago.c in_tcomp, no OPP)
 * - ING/EGR: tctes_numero (ambos 304)
 * - TRA: sin numerador de documento en Anita (tesmov nativo TED/TEH 308/309)
 *
 * El resto de tipos (COB/REM/RMI/…) sigue con semilla propia ERP (MAX+1).
 * Se puede apagar con CAJA_IE_ANITA_NUMERACION_HABILITADA=false.
 */
final class IngresoEgresoAnitaNumeracionSupport
{
    public static function estaHabilitada(): bool
    {
        return filter_var(
            config('caja.ingresoegreso_anita_numeracion_habilitada', true),
            FILTER_VALIDATE_BOOLEAN
        );
    }

    public static function sistemaNumerador(): string
    {
        return (string) config('caja.ingresoegreso_anita_sistema_numerador', 'ventas');
    }

    /**
     * @return array<string, array<int, int>> abreviatura => [empresaAnita => num_clave]
     */
    public static function mapaSemillas(): array
    {
        $cfg = config('caja.ingresoegreso_anita_semillas', []);
        if (! is_array($cfg) || $cfg === []) {
            return self::semillasDefault();
        }

        $out = [];
        foreach ($cfg as $abrev => $porEmpresa) {
            $abrevKey = strtoupper(trim((string) $abrev));
            if ($abrevKey === '' || ! is_array($porEmpresa)) {
                continue;
            }
            foreach ($porEmpresa as $emp => $clave) {
                $empId = (int) $emp;
                $claveInt = (int) $clave;
                if ($empId > 0 && $claveInt > 0) {
                    $out[$abrevKey][$empId] = $claveInt;
                }
            }
        }

        return $out !== [] ? $out : self::semillasDefault();
    }

    /**
     * @return array<string, array<int, int>>
     */
    public static function semillasDefault(): array
    {
        return [
            'OPP' => [1 => 223, 2 => 224, 3 => 225],
            'OPA' => [1 => 223, 2 => 224, 3 => 225],
            'EGR' => [1 => 361, 2 => 362, 3 => 363],
            'ING' => [1 => 346, 2 => 347, 3 => 348],
            'TRA' => [1 => 334, 2 => 335, 3 => 336],
        ];
    }

    public static function abreviaturaTipo(int $tipotransaccionCajaId): string
    {
        if ($tipotransaccionCajaId <= 0) {
            return '';
        }

        return strtoupper(trim((string) Tipotransaccion_Caja::query()
            ->whereKey($tipotransaccionCajaId)
            ->value('abreviatura')));
    }

    public static function usaNumeracionAnita(int $tipotransaccionCajaId): bool
    {
        if (! self::estaHabilitada()) {
            return false;
        }

        $abrev = self::abreviaturaTipo($tipotransaccionCajaId);

        return $abrev !== '' && isset(self::mapaSemillas()[$abrev]);
    }

    public static function claveNumerador(int $empresaId, int $tipotransaccionCajaId): int
    {
        $abrev = self::abreviaturaTipo($tipotransaccionCajaId);
        $mapa = self::mapaSemillas();
        if ($abrev === '' || ! isset($mapa[$abrev])) {
            throw new \RuntimeException(
                'El tipo de transacción de caja '.$tipotransaccionCajaId
                .' no tiene semilla Anita configurada para numeración IE.'
            );
        }

        if (EntornoEmpresaSupport::esFerli() && in_array($abrev, ['OPP', 'OPA'], true)) {
            $desdeTcomp = self::claveNumeradorOpDesdeTComp($empresaId, $abrev);
            if ($desdeTcomp > 0) {
                return $desdeTcomp;
            }
        }

        if (EntornoEmpresaSupport::esFerli()) {
            $desdeTctes = self::claveNumeradorDesdeTctes($abrev);
            if ($desdeTctes > 0) {
                return $desdeTctes;
            }
            if ($abrev === 'TRA') {
                throw new \RuntimeException(
                    'Ferli no tiene numerador Anita de documento para TRA (tesmov nativo TED/TEH).'
                );
            }
        }

        $empresaAnita = SicoreEmpresaAnitaSupport::codigoEmpresaAnita($empresaId);
        if ($empresaAnita <= 0) {
            throw new \RuntimeException('No se pudo resolver código Anita de empresa '.$empresaId.' para numerar IE.');
        }

        $clave = (int) ($mapa[$abrev][$empresaAnita] ?? 0);
        if ($clave <= 0 && EntornoEmpresaSupport::esFerli()) {
            $clave = (int) ($mapa[$abrev][1] ?? 0);
        }
        if ($clave <= 0) {
            throw new \RuntimeException(
                'Sin semilla Anita para '.$abrev.' / empresa Anita '.$empresaAnita
                .' (empresa ERP '.$empresaId.').'
            );
        }

        return $clave;
    }

    /**
     * Ferli: ING/EGR (y otros) vía tctes_numero → ventas.numerador.
     * No usar ventas.t_comp ING (en Ferli es ingreso de stock, clave 127).
     */
    public static function claveNumeradorDesdeTctes(string $abreviatura): int
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            return 0;
        }

        $abrev = strtoupper(substr(trim($abreviatura), 0, 3));
        if ($abrev === '') {
            return 0;
        }

        try {
            $sistema = (string) config('caja.ingresoegreso_anita_tesmov_sistema', 'che_ban');
            $raw = (new ApiAnita)->apiCallEscritura([
                'acc' => 'list',
                'sistema' => $sistema,
                'tabla' => 'tctes',
                'campos' => 'tctes_clave,tctes_numero,tctes_desc',
                'whereArmado' => ' WHERE tctes_clave = '.self::escSqlLiteral($abrev),
            ], 'caja IE tctes numerador '.$abrev);

            $err = ApiAnita::extraerMensajeError($raw);
            if ($err !== null) {
                Log::warning('caja.ie.numeracion.tctes', [
                    'abrev' => $abrev,
                    'error' => $err,
                ]);

                return 0;
            }

            $fila = ApiAnita::primeraFilaLista((string) $raw);
            $numero = ltrim(trim((string) ($fila->tctes_numero ?? '')), '0');
            if ($numero === '' || $numero === '0') {
                return 0;
            }

            return max(0, (int) $numero);
        } catch (\Throwable $e) {
            Log::warning('caja.ie.numeracion.tctes_exception', [
                'abrev' => $abrev,
                'error' => $e->getMessage(),
            ]);

            return 0;
        }
    }

    /**
     * Ferli: clave de numerador de OP vía t_comp (pago.c: OPP u OPA según in_tcomp).
     * AGG MultiEmpresa no usa este camino: OPA comparte O{nroemp} con OPP.
     */
    public static function claveNumeradorOpDesdeTComp(int $empresaId, string $abreviatura = 'OPP'): int
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            return 0;
        }

        try {
            $claveTcomp = PagoproveedorAnitaNumeracionSupport::claveTCompParaEmpresa($empresaId, $abreviatura);
            $refer = PagoproveedorAnitaNumeracionSupport::resolverClaveNumeradorDesdeTComp($claveTcomp);

            return max(0, (int) $refer);
        } catch (\Throwable $e) {
            Log::warning('caja.ie.numeracion.tcomp_op', [
                'empresa_id' => $empresaId,
                'abrev' => $abreviatura,
                'error' => $e->getMessage(),
            ]);

            return 0;
        }
    }

    public static function leerUltimoNumero(int $claveNumerador): int
    {
        $api = new ApiAnita;
        $raw = $api->apiCallEscritura([
            'acc' => 'list',
            'sistema' => self::sistemaNumerador(),
            'tabla' => 'numerador',
            'campos' => 'num_ult_numero',
            'whereArmado' => ' WHERE num_clave = '.self::escSqlLiteral((string) $claveNumerador),
        ], 'caja IE numerador lectura '.$claveNumerador);

        $err = ApiAnita::extraerMensajeError($raw);
        if ($err !== null) {
            throw new \RuntimeException(
                'No se pudo leer numerador Anita (num_clave='.$claveNumerador.'): '.$err
            );
        }

        $fila = ApiAnita::primeraFilaLista((string) $raw);
        if ($fila === null || ! isset($fila->num_ult_numero)) {
            throw new \RuntimeException(
                'Numerador Anita inexistente (sistema='.self::sistemaNumerador()
                .', num_clave='.$claveNumerador.').'
            );
        }

        return max(0, (int) $fila->num_ult_numero);
    }

    public static function actualizarNumerador(int $claveNumerador, int $numero): void
    {
        if ($numero <= 0) {
            throw new \InvalidArgumentException('Número de transacción IE inválido.');
        }

        $api = new ApiAnita;
        $raw = $api->apiCallEscritura([
            'acc' => 'update',
            'sistema' => self::sistemaNumerador(),
            'tabla' => 'numerador',
            'valores' => 'num_ult_numero = '.(int) $numero,
            'whereArmado' => ' WHERE num_clave = '.self::escSqlLiteral((string) $claveNumerador),
        ], 'caja IE numerador update '.$claveNumerador);

        $err = ApiAnita::extraerMensajeError($raw);
        if ($err !== null) {
            Log::error('caja.ie.numeracion.anita_update_fail', [
                'clave' => $claveNumerador,
                'numero' => $numero,
                'error' => $err,
            ]);
            throw new \RuntimeException(
                'No se pudo actualizar numerador Anita (num_clave='.$claveNumerador.'): '.$err
            );
        }
    }

    /**
     * Reserva el siguiente número vía {@see \App\Support\Configuracion\SistemaNumeradorSupport}
     * (tabla ERP + sync Anita si está habilitado).
     * Debe llamarse dentro del lock de {@see CobranzaNumeracionTransaccion::conExclusividad}.
     */
    public static function reservarSiguienteNumero(int $empresaId, int $tipotransaccionCajaId, int $maxErp): string
    {
        return SistemaNumeradorSupport::reservarSiguienteCaja(
            $empresaId,
            $tipotransaccionCajaId,
            $maxErp
        );
    }

    private static function escSqlLiteral(string $valor): string
    {
        return "'".str_replace("'", "''", $valor)."'";
    }
}
