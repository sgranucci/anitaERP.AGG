<?php

namespace App\Support\Compras\AnitaSync\Ordencompra;

use App\ApiAnita;
use App\Models\Compras\Ordencompra_Articulo;
use App\Support\Stock\SurmarSupport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Numeración OC (PEP): t_comp en compras → numerador en ventas (num_clave = tcomp_refer).
 * Con empresa Surmar (El Bierzo) usa path /usr2/surmar.
 */
final class OrdencompraAnitaNumeracionSupport
{
    public static function estaHabilitada(): bool
    {
        return (bool) config('ordencompra_anita.escritura_habilitada', true);
    }

    public static function sistemaTComp(): string
    {
        return (string) config('ordencompra_anita.escritura.sistema_compras', 'compras');
    }

    public static function sistemaNumerador(): string
    {
        return (string) config('ordencompra_anita.escritura.sistema_numerador', 'ventas');
    }

    public static function claveTComp(): string
    {
        return (string) config('ordencompra_anita.escritura.t_comp_clave', 'PEP');
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private static function payloadConPath(array $payload, ?int $empresaId): array
    {
        return SurmarSupport::mergePathSistema($payload, $empresaId);
    }

    public static function resolverClaveNumeradorDesdeTComp(?int $empresaId = null): string
    {
        $clave = self::escSqlLiteral(self::claveTComp());
        $api = new ApiAnita;
        $raw = $api->apiCallEscritura(self::payloadConPath([
            'acc' => 'list',
            'sistema' => self::sistemaTComp(),
            'tabla' => 't_comp',
            'campos' => 'tcomp_refer',
            'whereArmado' => " WHERE tcomp_clave = '".$clave."'",
        ], $empresaId), 'ordencompra t_comp numerador PEP');

        $err = ApiAnita::extraerMensajeError($raw);
        if ($err !== null) {
            throw new \RuntimeException('No se pudo leer t_comp (PEP) en Anita compras: '.$err);
        }

        $fila = ApiAnita::primeraFilaLista((string) $raw);
        $refer = trim((string) ($fila->tcomp_refer ?? ''));
        if ($refer === '') {
            throw new \RuntimeException('t_comp sin tcomp_refer para clave '.self::claveTComp().' en Anita (compras).');
        }

        return $refer;
    }

    public static function leerUltimoNumero(string $claveNumerador, ?int $empresaId = null): int
    {
        $clave = self::escSqlLiteral($claveNumerador);
        $api = new ApiAnita;
        $raw = $api->apiCallEscritura(self::payloadConPath([
            'acc' => 'list',
            'sistema' => self::sistemaNumerador(),
            'tabla' => 'numerador',
            'campos' => 'num_ult_numero',
            'whereArmado' => ' WHERE num_clave = '.$clave,
        ], $empresaId), 'ordencompra numerador PEP lectura');

        $err = ApiAnita::extraerMensajeError($raw);
        if ($err !== null) {
            throw new \RuntimeException('No se pudo leer numerador Anita (num_clave='.$claveNumerador.'): '.$err);
        }

        $fila = ApiAnita::primeraFilaLista((string) $raw);
        if ($fila === null || ! isset($fila->num_ult_numero)) {
            throw new \RuntimeException('Numerador Anita inexistente o sin num_ult_numero (num_clave='.$claveNumerador.').');
        }

        return max(0, (int) $fila->num_ult_numero);
    }

    public static function actualizarNumerador(string $claveNumerador, int $numero, ?int $empresaId = null): void
    {
        if ($numero <= 0) {
            throw new \InvalidArgumentException('Número de OC Anita inválido.');
        }

        $clave = self::escSqlLiteral($claveNumerador);
        $api = new ApiAnita;
        $raw = $api->apiCallEscritura(self::payloadConPath([
            'acc' => 'update',
            'sistema' => self::sistemaNumerador(),
            'tabla' => 'numerador',
            'valores' => 'num_ult_numero = '.(int) $numero,
            'whereArmado' => ' WHERE num_clave = '.$clave,
        ], $empresaId), 'ordencompra numerador PEP update');

        $err = ApiAnita::extraerMensajeError($raw);
        if ($err !== null) {
            throw new \RuntimeException('No se pudo actualizar numerador Anita (num_clave='.$claveNumerador.'): '.$err);
        }
    }

    public static function ultimoNumeroOcGlobal(?int $empresaId = null): int
    {
        $maxErp = (int) DB::table('ordencompra')->max('numeroordencompra');
        $maxNumerador = 0;
        try {
            $maxNumerador = self::leerUltimoNumero(self::resolverClaveNumeradorDesdeTComp($empresaId), $empresaId);
        } catch (\Throwable $e) {
            Log::warning('OrdencompraAnitaNumeracion: no se pudo leer numerador PEP', [
                'error' => $e->getMessage(),
                'empresa_id' => $empresaId,
            ]);
        }

        return max($maxErp, $maxNumerador);
    }

    public static function existeNumeroEnErp(int $numero): bool
    {
        return DB::table('ordencompra')->where('numeroordencompra', $numero)->exists();
    }

    public static function existeNumeroEnPendmaep(int $numero, ?int $empresaId = null): bool
    {
        if ($numero <= 0) {
            return false;
        }

        try {
            $api = new ApiAnita;
            $raw = $api->apiCallEscritura(self::payloadConPath([
                'acc' => 'list',
                'sistema' => self::sistemaTComp(),
                'tabla' => config('ordencompra_anita.tablas.cabecera'),
                'campos' => 'penmp_nro',
                'whereArmado' => OrdencompraAnitaWhereSupport::pendmaepPorNumero($numero),
                'limit' => 'FIRST 1',
            ], $empresaId), 'ordencompra pendmaep existe nro');

            return ApiAnita::primeraFilaLista((string) $raw) !== null;
        } catch (\Throwable $e) {
            Log::warning('OrdencompraAnitaNumeracion: no se pudo verificar pendmaep', [
                'numero' => $numero,
                'empresa_id' => $empresaId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public static function primerNumeroDisponible(int $desde, ?int $empresaId = null): int
    {
        $numero = max(1, $desde);
        for ($i = 0; $i < 500; $i++) {
            if (! self::existeNumeroEnErp($numero) && ! self::existeNumeroEnPendmaep($numero, $empresaId)) {
                return $numero;
            }
            $numero++;
        }

        throw new \RuntimeException('No se encontró número de OC libre a partir de '.$desde.'.');
    }

    public static function asignarNumeroOcLibre(?int $empresaId = null): int
    {
        $base = self::ultimoNumeroOcGlobal($empresaId);
        $numero = self::primerNumeroDisponible($base + 1, $empresaId);

        Log::info('OrdencompraAnitaNumeracion: número OC reservado en ERP', [
            'base' => $base,
            'asignado' => $numero,
            'empresa_id' => $empresaId,
        ]);

        return $numero;
    }

    public static function registrarNumeroAsignadoEnNumerador(int $numero, ?int $empresaId = null): void
    {
        if ($numero <= 0 || ! self::estaHabilitada()) {
            return;
        }

        try {
            $claveNumerador = self::resolverClaveNumeradorDesdeTComp($empresaId);
            $ultimoNumerador = self::leerUltimoNumero($claveNumerador, $empresaId);
            $maxErp = (int) DB::table('ordencompra')->max('numeroordencompra');
            $objetivo = max($numero, $ultimoNumerador, $maxErp);
            if ($objetivo > $ultimoNumerador) {
                self::actualizarNumerador($claveNumerador, $objetivo, $empresaId);
            }
        } catch (\Throwable $e) {
            Log::warning('OrdencompraAnitaNumeracion: no se pudo actualizar numerador tras asignar OC', [
                'numero' => $numero,
                'empresa_id' => $empresaId,
                'error' => $e->getMessage(),
            ]);
            throw new \RuntimeException('OC grabada en ERP pero falló la actualización del numerador PEP Anita: '.$e->getMessage(), 0, $e);
        }
    }

    public static function maxPenvpNroInternoErp(): int
    {
        return max(0, (int) Ordencompra_Articulo::query()->max('penvp_nro_interno'));
    }

    public static function leerMaxPenvpNroInternoAnita(?int $empresaId = null): int
    {
        $piso = (int) config('ordencompra_anita.escritura.piso_nro_interno', 500000);
        try {
            $api = new ApiAnita;
            $raw = $api->apiCallEscritura(self::payloadConPath([
                'acc' => 'list',
                'sistema' => self::sistemaTComp(),
                'tabla' => config('ordencompra_anita.tablas.linea'),
                'campos' => 'penvp_nro_interno',
                'whereArmado' => ' WHERE penvp_nro_interno >= '.$piso.' ORDER BY penvp_nro_interno DESC',
                'limit' => 'FIRST 1',
            ], $empresaId), 'ordencompra max penvp_nro_interno');

            $fila = ApiAnita::primeraFilaLista((string) $raw);

            return max(0, (int) ($fila->penvp_nro_interno ?? 0));
        } catch (\Throwable $e) {
            Log::warning('OrdencompraAnitaNumeracion: no se pudo leer max penvp_nro_interno Anita', [
                'empresa_id' => $empresaId,
                'error' => $e->getMessage(),
            ]);

            return 0;
        }
    }

    public static function reservarSiguienteNroInterno(?int $empresaId = null): int
    {
        $base = max(
            self::maxPenvpNroInternoErp(),
            self::leerMaxPenvpNroInternoAnita($empresaId),
            (int) config('ordencompra_anita.escritura.piso_nro_interno', 500000)
        );

        return $base + 1;
    }

    private static function escSqlLiteral(string $value): string
    {
        return str_replace("'", "''", trim($value));
    }
}
