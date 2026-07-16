<?php

namespace App\Support\Compras\AnitaSync\Pagoproveedor;

use App\ApiAnita;
use App\Models\Compras\Pagoproveedor_Retencion;
use App\Support\Contable\Sicore\SicoreEmpresaAnitaSupport;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Numeración de certificados de retención (Anita lee_num_tes en retgan/retiva/retsmov/retibr.fc).
 *
 * MultiEmpresa:
 *   Ganancias → G{n} | IVA → V{n} | SUSS → S{n} | IIBB → T{n}
 * Monoempresa:
 *   RGP | RIP | RSP | RTP
 *
 * Resolución de num_clave:
 *   1) t_comp (compras) con esa clave → tcomp_refer
 *   2) fallback config pagoproveedor.retencion_num_clave[prefijo][empresaAnita]
 *   3) la clave misma como num_clave en ventas.numerador
 *
 * Semántica Anita al grabar:
 *   - Ganancias: un número por cada régimen con importe > 0
 *   - IVA / SUSS: un número por pago (compartido entre líneas del mismo tipo)
 *   - IIBB: un número por provincia (se reinicia al cambiar provincia)
 */
final class PagoproveedorAnitaRetencionNumeracionSupport
{
    public const PREFIJO_GANANCIAS = 'G';

    public const PREFIJO_IVA = 'V';

    public const PREFIJO_SUSS = 'S';

    public const PREFIJO_IIBB = 'T';

    public const MONO_GANANCIAS = 'RGP';

    public const MONO_IVA = 'RIP';

    public const MONO_SUSS = 'RSP';

    public const MONO_IIBB = 'RTP';

    public static function estaHabilitada(): bool
    {
        return (bool) config('pagoproveedor.anita_escritura_habilitada', true);
    }

    public static function esMultiempresa(): bool
    {
        return (bool) config('pagoproveedor.anita_multiempresa', true);
    }

    public static function prefijoPorTiporetencion(string $tiporetencion): string
    {
        return match ($tiporetencion) {
            Pagoproveedor_Retencion::TIPO_GANANCIAS => self::PREFIJO_GANANCIAS,
            Pagoproveedor_Retencion::TIPO_IVA => self::PREFIJO_IVA,
            Pagoproveedor_Retencion::TIPO_SUSS => self::PREFIJO_SUSS,
            Pagoproveedor_Retencion::TIPO_IIBB => self::PREFIJO_IIBB,
            default => throw new \InvalidArgumentException('Tipo de retención desconocido: '.$tiporetencion),
        };
    }

    public static function claveTesParaEmpresa(string $prefijo, int $empresaId): string
    {
        if (! self::esMultiempresa()) {
            return match ($prefijo) {
                self::PREFIJO_GANANCIAS => self::MONO_GANANCIAS,
                self::PREFIJO_IVA => self::MONO_IVA,
                self::PREFIJO_SUSS => self::MONO_SUSS,
                self::PREFIJO_IIBB => self::MONO_IIBB,
                default => throw new \InvalidArgumentException('Prefijo retención inválido: '.$prefijo),
            };
        }

        $nro = SicoreEmpresaAnitaSupport::codigoEmpresaAnita($empresaId);
        if ($nro <= 0) {
            throw new \RuntimeException('No se pudo resolver código Anita de empresa para numerar retención.');
        }

        return $prefijo.$nro;
    }

    public static function siguienteNumeroConLock(string $tiporetencion, int $empresaId): int
    {
        if (! self::estaHabilitada()) {
            throw new \RuntimeException('Numeración Anita de retenciones deshabilitada.');
        }

        $prefijo = self::prefijoPorTiporetencion($tiporetencion);
        $claveTes = self::claveTesParaEmpresa($prefijo, $empresaId);
        $segundos = max(5, (int) config('pagoproveedor.numeracion_lock_segundos', 15));
        $lock = Cache::lock('pagoproveedor:numeracion:ret:'.$claveTes, $segundos);

        return $lock->block($segundos, function () use ($claveTes, $prefijo, $empresaId) {
            $claveNum = self::resolverClaveNumerador($claveTes, $prefijo, $empresaId);
            $ultimo = self::leerUltimoNumero($claveNum);
            $siguiente = $ultimo + 1;
            self::actualizarNumerador($claveNum, $siguiente);

            return $siguiente;
        });
    }

    /**
     * Resuelve num_clave de ventas.numerador a partir de la clave lee_num_tes.
     */
    public static function resolverClaveNumerador(string $claveTes, string $prefijo, int $empresaId): string
    {
        $desdeTcomp = self::referDesdeTComp($claveTes);
        if ($desdeTcomp !== null) {
            return $desdeTcomp;
        }

        $nroEmp = SicoreEmpresaAnitaSupport::codigoEmpresaAnita($empresaId);
        $mapa = config('pagoproveedor.retencion_num_clave', []);
        $fallback = $mapa[$prefijo][$nroEmp] ?? $mapa[$prefijo][(string) $nroEmp] ?? null;
        if (is_string($fallback) && $fallback !== '') {
            return $fallback;
        }

        return $claveTes;
    }

    private static function referDesdeTComp(string $claveTes): ?string
    {
        $api = new ApiAnita;
        $raw = $api->apiCallEscritura([
            'acc' => 'list',
            'sistema' => (string) config('pagoproveedor.anita_sistema_tcomp', 'compras'),
            'tabla' => 't_comp',
            'campos' => 'tcomp_refer',
            'whereArmado' => ' WHERE tcomp_clave = '.self::escSqlLiteral($claveTes),
        ], 'pagoproveedor t_comp ret '.$claveTes);

        $err = ApiAnita::extraerMensajeError($raw);
        if ($err !== null) {
            Log::warning('pagoproveedor.retencion.t_comp', ['clave' => $claveTes, 'error' => $err]);

            return null;
        }

        $fila = ApiAnita::primeraFilaLista((string) $raw);
        $refer = trim((string) ($fila->tcomp_refer ?? ''));
        if ($refer === '' || $refer === '000') {
            return null;
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
        ], 'pagoproveedor numerador ret lectura');

        $err = ApiAnita::extraerMensajeError($raw);
        if ($err !== null) {
            throw new \RuntimeException('No se pudo leer numerador retención Anita ('.$claveNumerador.'): '.$err);
        }

        $fila = ApiAnita::primeraFilaLista((string) $raw);
        if ($fila === null || ! isset($fila->num_ult_numero)) {
            throw new \RuntimeException(
                'Numerador Anita inexistente para retención (num_clave='.$claveNumerador
                .'). Crear la clave o cargar pagoproveedor.retencion_num_clave.'
            );
        }

        return max(0, (int) $fila->num_ult_numero);
    }

    public static function actualizarNumerador(string $claveNumerador, int $numero): void
    {
        if ($numero <= 0) {
            throw new \InvalidArgumentException('Número de certificado de retención inválido.');
        }

        $api = new ApiAnita;
        $raw = $api->apiCallEscritura([
            'acc' => 'update',
            'sistema' => (string) config('pagoproveedor.anita_sistema_numerador', 'ventas'),
            'tabla' => 'numerador',
            'valores' => 'num_ult_numero = '.(int) $numero,
            'whereArmado' => ' WHERE num_clave = '.self::escSqlLiteral($claveNumerador),
        ], 'pagoproveedor numerador ret update');

        $err = ApiAnita::extraerMensajeError($raw);
        if ($err !== null) {
            Log::error('pagoproveedor.retencion.numeracion.update_fail', [
                'clave' => $claveNumerador,
                'numero' => $numero,
                'error' => $err,
            ]);
            throw new \RuntimeException('No se pudo actualizar numerador Anita de retención: '.$err);
        }
    }

    private static function escSqlLiteral(string $valor): string
    {
        return "'".str_replace("'", "''", $valor)."'";
    }
}
