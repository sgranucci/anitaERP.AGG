<?php

namespace App\Support\Ventas;

use App\ApiAnita;
use Illuminate\Support\Facades\Log;

/**
 * Numeración cliente EL BIERZO: t_comp (CLI) → numerador Anita (num_clave = tcomp_refer).
 * El siguiente código sale de num_ult_numero + 1; si está ocupado en ERP/climae se incrementa.
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
            throw new \RuntimeException('No se pudo leer t_comp ('.self::claveTComp().') en Anita: '.$err);
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

    public static function actualizarNumeradorConReintento(int $numero): void
    {
        $maxIntentos = max(1, (int) config('cliente_anita.numeracion.reintentos_bloqueo', 6));
        $esperaMs = max(100, (int) config('cliente_anita.numeracion.espera_reintento_ms', 400));
        $ultimoError = null;

        for ($intento = 1; $intento <= $maxIntentos; $intento++) {
            try {
                self::actualizarNumerador($numero);

                return;
            } catch (\Throwable $e) {
                $ultimoError = $e;
                if (! self::esErrorBloqueoNumerador($e->getMessage()) || $intento >= $maxIntentos) {
                    break;
                }

                usleep($esperaMs * 1000);
            }
        }

        $detalle = $ultimoError?->getMessage() ?? 'error desconocido';
        throw new \RuntimeException(
            'Numerador CLI bloqueado en Anita. Cierre la ficha de numeradores o el módulo de clientes en Anita desktop y vuelva a intentar. Detalle: '.$detalle,
            0,
            $ultimoError
        );
    }

    public static function asignarCodigoClienteLibre(): int
    {
        $claveNumerador = self::resolverClaveNumeradorDesdeTComp();
        $ultimoNumerador = self::leerUltimoNumeroNumerador();
        $desde = $ultimoNumerador + 1;
        $numero = ClienteAnitaColisionSupport::primerCodigoDisponible($desde);

        self::actualizarNumeradorConReintento($numero);

        Log::info('ClienteAnitaNumeracion: código cliente reservado desde numerador CLI', [
            't_comp_clave' => self::claveTComp(),
            'num_clave' => $claveNumerador,
            'numerador_ultimo' => $ultimoNumerador,
            'desde' => $desde,
            'asignado' => $numero,
        ]);

        return $numero;
    }

    /**
     * Código en ERP: sin ceros a la izquierda (Anita sí usa 6 dígitos en clim_cliente).
     */
    public static function formatearCodigoErp(int $numero): string
    {
        if ($numero <= 0) {
            throw new \InvalidArgumentException('Número de cliente ERP inválido.');
        }

        return (string) $numero;
    }

    /**
     * Informe del numerador CLI. No alinea contra max(climae): códigos legacy (ej. 014146)
     * no deben mover el numerador de la serie CLI.
     *
     * @return array{antes: int, despues: int, num_clave: string, max_erp: int, max_climae: int}
     */
    public static function sincronizarNumeradorCliGlobal(bool $forzarAlinearMaxGlobal = false): array
    {
        $claveNumerador = self::resolverClaveNumeradorDesdeTComp();
        $antes = self::leerUltimoNumeroNumerador();
        $maxErp = ClienteAnitaColisionSupport::maxCodigoClienteErp();
        $maxClimae = ClienteAnitaColisionSupport::maxCodigoClimae();
        $despues = $antes;

        if ($forzarAlinearMaxGlobal) {
            $objetivo = max($antes, $maxErp, $maxClimae);
            if ($objetivo > $antes) {
                self::actualizarNumeradorConReintento($objetivo);
                $despues = self::leerUltimoNumeroNumerador();
            }
        }

        return [
            'antes' => $antes,
            'despues' => $despues,
            'num_clave' => $claveNumerador,
            'max_erp' => $maxErp,
            'max_climae' => $maxClimae,
        ];
    }

    public static function registrarCodigoAsignadoEnNumerador(int $numero): void
    {
        if ($numero <= 0 || ! self::estaHabilitada()) {
            return;
        }

        try {
            $ultimoNumerador = self::leerUltimoNumeroNumerador();
            if ($numero > $ultimoNumerador) {
                self::actualizarNumeradorConReintento($numero);
            }
        } catch (\Throwable $e) {
            Log::warning('ClienteAnitaNumeracion: no se pudo reforzar numerador tras alta', [
                'numero' => $numero,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private static function esErrorBloqueoNumerador(string $mensaje): bool
    {
        $mensaje = strtolower($mensaje);

        return str_contains($mensaje, 'could not lock')
            || str_contains($mensaje, 'record is locked')
            || str_contains($mensaje, '263:')
            || str_contains($mensaje, 'bloqueado');
    }

    private static function escSqlLiteral(string $value): string
    {
        return str_replace("'", "''", trim($value));
    }
}
