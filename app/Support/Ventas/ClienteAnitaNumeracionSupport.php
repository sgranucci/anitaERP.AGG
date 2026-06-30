<?php

namespace App\Support\Ventas;

use App\ApiAnita;
use Illuminate\Support\Facades\Log;

/**
 * Numeración cliente EL BIERZO: t_comp (CLI) → numerador Anita (num_clave = tcomp_refer).
 */
final class ClienteAnitaNumeracionSupport
{
    public static function estaHabilitada(): bool
    {
        return config('app.empresa') === 'EL BIERZO'
            && filter_var(config('cliente_anita.numeracion.habilitada', true), FILTER_VALIDATE_BOOLEAN);
    }

    public static function sistemaTComp(): string
    {
        return (string) config('cliente_anita.numeracion.sistema_t_comp', 'ventas');
    }

    public static function sistemaNumerador(): string
    {
        return (string) config('cliente_anita.numeracion.sistema_numerador', 'ventas');
    }

    public static function claveTComp(): string
    {
        return (string) config('cliente_anita.numeracion.t_comp_clave', 'CLI');
    }

    public static function resolverClaveNumeradorDesdeTComp(): string
    {
        $clave = self::escSqlLiteral(self::claveTComp());
        $api = new ApiAnita;
        $raw = $api->apiCallEscritura([
            'acc' => 'list',
            'sistema' => self::sistemaTComp(),
            'tabla' => 't_comp',
            'campos' => 'tcomp_refer',
            'whereArmado' => " WHERE tcomp_clave = '".$clave."'",
        ], 'cliente t_comp numerador CLI');

        $err = ApiAnita::extraerMensajeError($raw);
        if ($err !== null) {
            throw new \RuntimeException('No se pudo leer t_comp (CLI) en Anita: '.$err);
        }

        $fila = ApiAnita::primeraFilaLista((string) $raw);
        $refer = trim((string) ($fila->tcomp_refer ?? ''));
        if ($refer === '') {
            throw new \RuntimeException('t_comp sin tcomp_refer para clave '.self::claveTComp().' en Anita.');
        }

        return $refer;
    }

    public static function leerUltimoNumeroNumerador(): int
    {
        $claveNumerador = self::resolverClaveNumeradorDesdeTComp();
        $clave = self::escSqlLiteral($claveNumerador);
        $api = new ApiAnita;
        $raw = $api->apiCallEscritura([
            'acc' => 'list',
            'sistema' => self::sistemaNumerador(),
            'tabla' => 'numerador',
            'campos' => 'num_ult_numero',
            'whereArmado' => ' WHERE num_clave = '.$clave,
        ], 'cliente numerador CLI lectura');

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

    public static function actualizarNumerador(int $numero): void
    {
        if ($numero <= 0) {
            throw new \InvalidArgumentException('Número de cliente Anita inválido.');
        }

        $claveNumerador = self::resolverClaveNumeradorDesdeTComp();
        $clave = self::escSqlLiteral($claveNumerador);
        $api = new ApiAnita;
        $raw = $api->apiCallEscritura([
            'acc' => 'update',
            'sistema' => self::sistemaNumerador(),
            'tabla' => 'numerador',
            'valores' => 'num_ult_numero = '.(int) $numero,
            'whereArmado' => ' WHERE num_clave = '.$clave,
        ], 'cliente numerador CLI update');

        $err = ApiAnita::extraerMensajeError($raw);
        if ($err !== null) {
            throw new \RuntimeException('No se pudo actualizar numerador Anita (num_clave='.$claveNumerador.'): '.$err);
        }
    }

    public static function ultimoNumeroClienteGlobal(): int
    {
        $maxNumerador = 0;
        try {
            $maxNumerador = self::leerUltimoNumeroNumerador();
        } catch (\Throwable $e) {
            Log::warning('ClienteAnitaNumeracion: no se pudo leer numerador CLI', [
                'error' => $e->getMessage(),
            ]);
        }

        return max(
            $maxNumerador,
            ClienteAnitaColisionSupport::maxCodigoClienteErp(),
            ClienteAnitaColisionSupport::maxCodigoClimae()
        );
    }

    public static function asignarCodigoClienteLibre(): int
    {
        $base = self::ultimoNumeroClienteGlobal();
        $numero = ClienteAnitaColisionSupport::primerCodigoDisponible($base + 1);

        Log::info('ClienteAnitaNumeracion: código cliente reservado', [
            'base' => $base,
            'asignado' => $numero,
        ]);

        return $numero;
    }

    public static function formatearCodigoErp(int $numero): string
    {
        return str_pad((string) $numero, 6, '0', STR_PAD_LEFT);
    }

    public static function registrarCodigoAsignadoEnNumerador(int $numero): void
    {
        if ($numero <= 0 || ! self::estaHabilitada()) {
            return;
        }

        try {
            $claveNumerador = self::resolverClaveNumeradorDesdeTComp();
            $ultimoNumerador = self::leerUltimoNumeroNumerador();
            $maxErp = ClienteAnitaColisionSupport::maxCodigoClienteErp();
            $objetivo = max($numero, $ultimoNumerador, $maxErp);
            if ($objetivo > $ultimoNumerador) {
                self::actualizarNumerador($objetivo);
            }
        } catch (\Throwable $e) {
            Log::warning('ClienteAnitaNumeracion: no se pudo actualizar numerador tras alta', [
                'numero' => $numero,
                'error' => $e->getMessage(),
            ]);
            throw new \RuntimeException(
                'Cliente grabado en ERP pero falló la actualización del numerador CLI Anita: '.$e->getMessage(),
                0,
                $e
            );
        }
    }

    private static function escSqlLiteral(string $value): string
    {
        return str_replace("'", "''", trim($value));
    }
}
