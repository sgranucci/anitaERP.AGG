<?php

namespace App\Support\Compras\AnitaSync\Pagoproveedor;

use App\ApiAnita;
use App\Support\Contable\Sicore\SicoreEmpresaAnitaSupport;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Numeración OP MultiEmpresa (Anita pago.c nro_op):
 *   t_comp clave O{empresa} → tcomp_refer → numerador ventas.
 * Monoempresa: t_comp OPP (default) → refer (205).
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
     * Clave t_comp: O{n} multiempresa o OPP/OPA según config.
     */
    public static function claveTCompParaEmpresa(int $empresaId): string
    {
        if (self::esMultiempresa()) {
            $nro = self::codigoEmpresaAnita($empresaId);
            if ($nro <= 0) {
                throw new \RuntimeException('No se pudo resolver código Anita de empresa para numerar OP.');
            }

            return 'O'.$nro;
        }

        return (string) config('pagoproveedor.anita_tcomp_clave', 'OPP');
    }

    public static function siguienteNumeroConLock(int $empresaId): int
    {
        if (! self::estaHabilitada()) {
            throw new \RuntimeException('Numeración Anita de OP deshabilitada (PAGOPROVEEDOR_ANITA_ESCRITURA_HABILITADA).');
        }

        $segundos = max(5, (int) config('pagoproveedor.numeracion_lock_segundos', 15));
        $claveTcomp = self::claveTCompParaEmpresa($empresaId);
        $lock = Cache::lock('pagoproveedor:numeracion:opp:'.$claveTcomp, $segundos);

        return $lock->block($segundos, function () use ($claveTcomp) {
            $clave = self::resolverClaveNumeradorDesdeTComp($claveTcomp);
            $ultimo = self::leerUltimoNumero($clave);
            $siguiente = $ultimo + 1;
            self::actualizarNumerador($clave, $siguiente);

            return $siguiente;
        });
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
            throw new \RuntimeException('t_comp sin tcomp_refer válido para clave '.$claveTcomp.'.');
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
