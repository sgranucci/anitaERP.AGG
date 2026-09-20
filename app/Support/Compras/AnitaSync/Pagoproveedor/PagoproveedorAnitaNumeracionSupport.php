<?php

namespace App\Support\Compras\AnitaSync\Pagoproveedor;

use App\ApiAnita;
use App\Support\Contable\Sicore\SicoreEmpresaAnitaSupport;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Numeración OP alineada a Anita pago.c nro_op():
 *   - MultiEmpresa (AGG): t_comp O{nroemp} (O1/O2/O3) tanto para OPP como OPA.
 *     pag_tipo queda OPP u OPA; el correlativo es el mismo.
 *   - Mono (Ferli): t_comp = in_tcomp (OPP o OPA). ADELANTO fija OPA y numera
 *     con ese comprobante (no con OPP).
 *
 * En MultiEmpresa la sucursal de la OP es el código de empresa Anita (pag_sucursal = nroemp).
 */
final class PagoproveedorAnitaNumeracionSupport
{
    public static function estaHabilitada(): bool
    {
        return (bool) config('pagoproveedor.anita_escritura_habilitada', true);
    }

    public static function esMultiempresa(): bool
    {
        return (bool) config('pagoproveedor.anita_multiempresa', true);
    }

    /**
     * Código de empresa Anita (nroemp) usado en clave O{n} y en sucursal de la OP.
     */
    public static function codigoEmpresaAnita(int $empresaId): int
    {
        return SicoreEmpresaAnitaSupport::codigoEmpresaAnita($empresaId);
    }

    /**
     * Sucursal a grabar en la OP: empresa Anita si MultiEmpresa; si no, config default.
     */
    public static function sucursalParaOp(int $empresaId): int
    {
        if (self::esMultiempresa()) {
            return max(1, self::codigoEmpresaAnita($empresaId));
        }

        return max(1, (int) config('pagoproveedor.sucursal_default', 1));
    }

    /**
     * pago.c: ADELANTO → in_tcomp=OPA. Cualquier otro → OPP (salvo que ya venga OPA).
     */
    public static function normalizarTipoComprobante(?string $tipoComprobante, bool $esAnticipoSinAplicaciones = false): string
    {
        $tipo = strtoupper(substr(trim((string) $tipoComprobante), 0, 3));
        if ($tipo === 'OPA' || $esAnticipoSinAplicaciones) {
            return 'OPA';
        }

        return $tipo === '' ? 'OPP' : $tipo;
    }

    public static function esTipoOpa(?string $tipoComprobante): bool
    {
        return self::normalizarTipoComprobante($tipoComprobante) === 'OPA';
    }

    /**
     * Clave t_comp de pago.c nro_op (sin consultar Anita).
     * MultiEmpresa: siempre O{nroemp}. Mono: OPA o OPP según el comprobante.
     */
    public static function claveTCompParaTipo(string $tipoComprobante, int $codigoEmpresaAnita): string
    {
        if (self::esMultiempresa()) {
            if ($codigoEmpresaAnita <= 0) {
                throw new \RuntimeException('No se pudo resolver código Anita de empresa para numerar OP.');
            }

            return 'O'.$codigoEmpresaAnita;
        }

        if (self::esTipoOpa($tipoComprobante)) {
            $opa = strtoupper(substr(trim((string) config('pagoproveedor.anita_tcomp_clave_opa', 'OPA')), 0, 3));

            return $opa !== '' ? $opa : 'OPA';
        }

        $opp = strtoupper(substr(trim((string) config('pagoproveedor.anita_tcomp_clave', 'OPP')), 0, 3));

        return $opp !== '' ? $opp : 'OPP';
    }

    /**
     * Clave t_comp: MultiEmpresa O{n}; Ferli OPP o OPA según el comprobante.
     */
    public static function claveTCompParaEmpresa(int $empresaId, ?string $tipoComprobante = null): string
    {
        return self::claveTCompParaTipo(
            self::normalizarTipoComprobante($tipoComprobante),
            self::codigoEmpresaAnita($empresaId)
        );
    }

    public static function siguienteNumeroConLock(int $empresaId, ?string $tipoComprobante = null): int
    {
        $tipo = self::normalizarTipoComprobante($tipoComprobante);

        // Smoke / labs: sin Anita no quemamos correlativo real; numeramos por max ERP.
        if (! self::estaHabilitada()) {
            if (app()->environment(['testing', 'local'])) {
                return self::siguienteNumeroLocalErp($empresaId, $tipo);
            }

            throw new \RuntimeException('Numeración Anita de OP deshabilitada (PAGOPROVEEDOR_ANITA_ESCRITURA_HABILITADA).');
        }

        self::assertNumeradorDisponible($empresaId, $tipo);

        $segundos = max(5, (int) config('pagoproveedor.numeracion_lock_segundos', 15));
        $claveTcomp = self::claveTCompParaEmpresa($empresaId, $tipo);
        $lock = Cache::lock('pagoproveedor:numeracion:opp:'.$claveTcomp, $segundos);

        return $lock->block($segundos, function () use ($claveTcomp) {
            $clave = self::resolverClaveNumeradorDesdeTComp($claveTcomp);
            $ultimo = self::leerUltimoNumero($clave);
            $siguiente = $ultimo + 1;
            self::actualizarNumerador($clave, $siguiente);

            return $siguiente;
        });
    }

    /**
     * Correlativo local (testing/local con Anita off): max(numerotransaccion)+1 por empresa/tipo/sucursal.
     */
    private static function siguienteNumeroLocalErp(int $empresaId, string $tipoComprobante): int
    {
        $sucursal = self::sucursalParaOp($empresaId);
        $segundos = max(5, (int) config('pagoproveedor.numeracion_lock_segundos', 15));
        $lockKey = 'pagoproveedor:numeracion:local:'.$empresaId.':'.$tipoComprobante.':'.$sucursal;
        $lock = Cache::lock($lockKey, $segundos);

        return $lock->block($segundos, function () use ($empresaId, $tipoComprobante, $sucursal) {
            $max = (int) DB::table('pagoproveedor')
                ->where('empresa_id', $empresaId)
                ->where('tipocomprobante', $tipoComprobante)
                ->where('sucursal', $sucursal)
                ->whereNull('deleted_at')
                ->selectRaw('MAX(CAST(numerotransaccion AS UNSIGNED)) as m')
                ->value('m');

            return max(1, $max + 1);
        });
    }

    /**
     * Solo lectura: verifica que exista t_comp + numerador antes de consumir un número.
     * Evita quemar el correlativo de OP si luego falla otra validación/Anita.
     */
    public static function assertNumeradorDisponible(int $empresaId, ?string $tipoComprobante = null): void
    {
        if (! self::estaHabilitada()) {
            return;
        }

        $claveTcomp = self::claveTCompParaEmpresa($empresaId, $tipoComprobante);
        $clave = self::resolverClaveNumeradorDesdeTComp($claveTcomp);
        self::leerUltimoNumero($clave);
    }

    public static function resolverClaveNumeradorDesdeTComp(?string $claveTcomp = null): string
    {
        $claveTcomp = $claveTcomp ?? (string) config('pagoproveedor.anita_tcomp_clave', 'OPP');
        $api = new ApiAnita;
        $raw = $api->apiCallEscritura([
            'acc' => 'list',
            'sistema' => (string) config('pagoproveedor.anita_sistema_tcomp', 'compras'),
            'tabla' => 't_comp',
            'campos' => 'tcomp_refer',
            'whereArmado' => ' WHERE tcomp_clave = '.self::escSqlLiteral($claveTcomp),
        ], 'pagoproveedor t_comp OP '.$claveTcomp);

        $err = ApiAnita::extraerMensajeError($raw);
        if ($err !== null) {
            throw new \RuntimeException('No se pudo leer t_comp '.$claveTcomp.' en Anita: '.$err);
        }

        $fila = ApiAnita::primeraFilaLista((string) $raw);
        $refer = trim((string) ($fila->tcomp_refer ?? ''));
        if ($refer === '' || $refer === '000') {
            $hint = self::esMultiempresa()
                ? ' En instalaciones monoempresa (p.ej. Ferli) use PAGOPROVEEDOR_ANITA_MULTIEMPRESA=false para numerar con OPP.'
                : '';
            throw new \RuntimeException(
                't_comp sin tcomp_refer válido para clave '.$claveTcomp.'.'.$hint
            );
        }

        return $refer;
    }

    public static function leerUltimoNumero(string $claveNumerador): int
    {
        $api = new ApiAnita;
        $raw = $api->apiCallEscritura([
            'acc' => 'list',
            'sistema' => (string) config('pagoproveedor.anita_sistema_numerador', 'ventas'),
            'tabla' => 'numerador',
            'campos' => 'num_ult_numero',
            'whereArmado' => ' WHERE num_clave = '.self::escSqlLiteral($claveNumerador),
        ], 'pagoproveedor numerador OP lectura');

        $err = ApiAnita::extraerMensajeError($raw);
        if ($err !== null) {
            throw new \RuntimeException('No se pudo leer numerador Anita ('.$claveNumerador.'): '.$err);
        }

        $fila = ApiAnita::primeraFilaLista((string) $raw);
        if ($fila === null || ! isset($fila->num_ult_numero)) {
            throw new \RuntimeException('Numerador Anita inexistente (num_clave='.$claveNumerador.').');
        }

        return max(0, (int) $fila->num_ult_numero);
    }

    public static function actualizarNumerador(string $claveNumerador, int $numero): void
    {
        if ($numero <= 0) {
            throw new \InvalidArgumentException('Número OP inválido.');
        }

        $api = new ApiAnita;
        $raw = $api->apiCallEscritura([
            'acc' => 'update',
            'sistema' => (string) config('pagoproveedor.anita_sistema_numerador', 'ventas'),
            'tabla' => 'numerador',
            'valores' => 'num_ult_numero = '.(int) $numero,
            'whereArmado' => ' WHERE num_clave = '.self::escSqlLiteral($claveNumerador),
        ], 'pagoproveedor numerador OP update');

        $err = ApiAnita::extraerMensajeError($raw);
        if ($err !== null) {
            Log::error('pagoproveedor.numeracion.update_fail', [
                'clave' => $claveNumerador,
                'numero' => $numero,
                'error' => $err,
            ]);
            throw new \RuntimeException('No se pudo actualizar numerador Anita OP: '.$err);
        }
    }

    private static function escSqlLiteral(string $valor): string
    {
        return "'".str_replace("'", "''", $valor)."'";
    }
}
