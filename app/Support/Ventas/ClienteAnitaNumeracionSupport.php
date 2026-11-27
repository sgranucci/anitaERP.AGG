<?php

namespace App\Support\Ventas;

use App\ApiAnita;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Database\SqlDialectSupport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Numeración automática de cliente: t_comp (CLI) → numerador Anita (num_clave = tcomp_refer).
 * El siguiente código sale de num_ult_numero + 1; si está ocupado en ERP/climae se incrementa.
 * Activo en EL BIERZO e INTERFORMING, siempre que
 * CLIENTE_ANITA_NUMERACION_BIERZO=true en .env.
 * Crown numera en el ERP (ClienteNumeradorErpSupport), no en este numerador.
 */
final class ClienteAnitaNumeracionSupport
{
    /**
     * Tope de la serie operativa al sembrar un numerador CLI en 0 (Interforming).
     * Evita que outliers (87654, 101885, 999999) arrastren el contador.
     */
    private const PISO_SERIE_OPERATIVA_HASTA = 19999;

    public static function estaHabilitada(): bool
    {
        if (! filter_var(config('cliente_anita.numeracion.habilitada', true), FILTER_VALIDATE_BOOLEAN)) {
            return false;
        }

        return EntornoEmpresaSupport::esElBierzo()
            || EntornoEmpresaSupport::esInterforming();
    }

    /**
     * El alta no permite cargar el código a mano: lo asigna el numerador CLI.
     */
    public static function codigoAutomatico(): bool
    {
        return self::estaHabilitada();
    }

    /**
     * El operador tipea el código a mano (solo Interforming sin numerador CLI).
     * En el resto de los entornos (Crown, AGG, Ferli…) el repositorio asigna el código al grabar
     * y el campo del formulario es de solo lectura: no se puede exigir en la validación.
     */
    public static function codigoManual(): bool
    {
        return EntornoEmpresaSupport::esInterforming() && ! self::codigoAutomatico();
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
        $ultimoNumerador = self::asegurarPisoNumeradorSiVacio(self::leerUltimoNumeroNumerador());
        $desde = $ultimoNumerador + 1;
        $numero = ClienteAnitaColisionSupport::primerCodigoDisponible($desde);
        self::assertFueraDeSerieEmpleado($numero);

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
     * Código que vería el alta, sin mover el numerador. Es indicativo: al guardar se reserva de nuevo.
     */
    public static function proximoCodigoVisible(): string
    {
        if (! self::estaHabilitada()) {
            return '';
        }

        try {
            $desde = self::asegurarPisoNumeradorSiVacio(self::leerUltimoNumeroNumerador()) + 1;
            $techo = self::techoSerieCliente();
            if ($desde > $techo) {
                return '';
            }

            $numero = self::primerLibreEnErp($desde, $techo);
            $intentos = 0;
            while ($numero <= $techo && $intentos < 40 && ClienteAnitaColisionSupport::existeCodigoEnClimae($numero)) {
                $numero = self::primerLibreEnErp($numero + 1, $techo);
                $intentos++;
            }
            if ($numero > $techo || $numero <= 0) {
                return '';
            }

            return self::formatearCodigoErp($numero);
        } catch (\Throwable $e) {
            Log::warning('ClienteAnitaNumeracion: no se pudo anticipar el código de cliente', [
                'error' => $e->getMessage(),
            ]);

            return '';
        }
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

    private static function techoSerieCliente(): int
    {
        if (ClienteCodigoEmpleadoBierzoSupport::habilitada()) {
            return ClienteCodigoEmpleadoBierzoSupport::desde() - 1;
        }

        return 999999;
    }

    /**
     * Interforming: t_comp CLI → numerador 014 quedó en 0 (no se usaba).
     * Si arrancáramos en 1 asignaríamos huecos viejos. Usamos como piso el max de la
     * serie operativa (hasta 19999); el alta graba el numerador con el código asignado.
     */
    private static function asegurarPisoNumeradorSiVacio(int $ultimoNumerador): int
    {
        if ($ultimoNumerador > 0 || ! EntornoEmpresaSupport::esInterforming()) {
            return $ultimoNumerador;
        }

        $piso = self::maxCodigoSerieOperativaErp();
        if ($piso <= 0) {
            return 0;
        }

        Log::info('ClienteAnitaNumeracion: numerador CLI en 0; piso efectivo desde serie operativa', [
            'piso' => $piso,
            'hasta' => self::PISO_SERIE_OPERATIVA_HASTA,
        ]);

        return $piso;
    }

    private static function maxCodigoSerieOperativaErp(): int
    {
        $cast = SqlDialectSupport::castEntero('codigo');
        $max = DB::table('cliente')
            ->whereNotNull('codigo')
            ->where('codigo', '!=', '')
            ->whereRaw(SqlDialectSupport::coincideRegex('codigo'), ['^[0-9]+$'])
            ->whereRaw($cast.' BETWEEN 1 AND ?', [self::PISO_SERIE_OPERATIVA_HASTA])
            ->max(DB::raw($cast));

        return max(0, (int) $max);
    }

    /**
     * Primer código numérico libre en el ERP entre $desde y $techo. $techo + 1 si no hay.
     */
    private static function primerLibreEnErp(int $desde, int $techo): int
    {
        if ($desde > $techo) {
            return $techo + 1;
        }

        $cast = SqlDialectSupport::castEntero('codigo');
        $ocupados = DB::table('cliente')
            ->whereRaw(SqlDialectSupport::coincideRegex('codigo'), ['^[0-9]+$'])
            ->whereRaw($cast.' BETWEEN ? AND ?', [$desde, $techo])
            ->pluck('codigo');

        $tomados = [];
        foreach ($ocupados as $codigo) {
            $tomados[(int) $codigo] = true;
        }

        for ($numero = $desde; $numero <= $techo; $numero++) {
            if (! isset($tomados[$numero])) {
                return $numero;
            }
        }

        return $techo + 1;
    }

    /**
     * La serie 14000–14500 es de empleados. Un cliente estándar no puede consumirla.
     */
    private static function assertFueraDeSerieEmpleado(int $numero): void
    {
        if (! ClienteCodigoEmpleadoBierzoSupport::numeroEnRango($numero)) {
            return;
        }

        $desde = ClienteCodigoEmpleadoBierzoSupport::desde();
        $hasta = ClienteCodigoEmpleadoBierzoSupport::hasta();
        throw new \RuntimeException(
            'No hay código de cliente libre antes de la serie de empleados ('.$desde.'–'.$hasta.').'
        );
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
