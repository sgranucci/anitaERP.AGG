<?php

namespace App\Support\Stock;

use App\ApiAnita;
use Illuminate\Support\Facades\Log;

/**
 * Numeración provisoria de recepciones COM alineada a Anita (ventas):
 * t_comp (tcomp_clave=COM) → tcomp_refer → numerador (num_clave) → num_ult_numero + 1.
 */
final class RecepcionProveedorAnitaNumeracionSupport
{
    public static function sistemaVentas(): string
    {
        return (string) config('recepcion_proveedor.anita.sistema_ventas', 'ventas');
    }

    public static function claveTipoCom(): string
    {
        return (string) config('recepcion_proveedor.anita.t_comp_clave_numerador', 'COM');
    }

    /**
     * Reserva el siguiente número COM en Anita y devuelve el valor asignado.
     *
     * @param  int|null  $ultimoErpEmpresa  Máximo numerorecepcion ya usado en ERP para la empresa (si aplica).
     */
    public static function reservarSiguienteNumero(?int $ultimoErpEmpresa = null): int
    {
        $claveNumerador = self::resolverClaveNumeradorDesdeTComp();
        $ultimoAnita = self::leerUltimoNumero($claveNumerador);
        $base = $ultimoAnita;
        if ($ultimoErpEmpresa !== null && $ultimoErpEmpresa > $base) {
            $base = $ultimoErpEmpresa;
        }

        $siguiente = $base + 1;
        self::actualizarNumerador($claveNumerador, $siguiente);

        Log::info('RecepcionProveedorAnitaNumeracion: número COM reservado', [
            'num_clave' => $claveNumerador,
            'ultimo_anita' => $ultimoAnita,
            'ultimo_erp_empresa' => $ultimoErpEmpresa,
            'asignado' => $siguiente,
        ]);

        return $siguiente;
    }

    /** Lee tcomp_refer de t_comp para la clave COM (ventas). */
    public static function resolverClaveNumeradorDesdeTComp(): string
    {
        $claveCom = self::escSqlLiteral(self::claveTipoCom());
        $api = new ApiAnita;
        $raw = $api->apiCallEscritura([
            'acc' => 'list',
            'sistema' => self::sistemaVentas(),
            'tabla' => 't_comp',
            'campos' => 'tcomp_refer',
            'whereArmado' => " WHERE tcomp_clave = '".$claveCom."'",
        ], 'recepcion t_comp numerador COM');

        $err = ApiAnita::extraerMensajeError($raw);
        if ($err !== null) {
            throw new \RuntimeException('No se pudo leer t_comp (COM) en Anita ventas: '.$err);
        }

        $fila = ApiAnita::primeraFilaLista((string) $raw);
        $refer = trim((string) ($fila->tcomp_refer ?? ''));
        if ($refer === '') {
            throw new \RuntimeException(
                't_comp sin tcomp_refer para clave '.self::claveTipoCom().' en Anita (ventas).'
            );
        }

        return $refer;
    }

    public static function leerUltimoNumero(string $claveNumerador): int
    {
        $clave = self::escSqlLiteral($claveNumerador);
        $api = new ApiAnita;
        $raw = $api->apiCallEscritura([
            'acc' => 'list',
            'sistema' => self::sistemaVentas(),
            'tabla' => 'numerador',
            'campos' => 'num_ult_numero',
            'whereArmado' => " WHERE num_clave = '".$clave."'",
        ], 'recepcion numerador COM lectura');

        $err = ApiAnita::extraerMensajeError($raw);
        if ($err !== null) {
            throw new \RuntimeException('No se pudo leer numerador Anita (num_clave='.$claveNumerador.'): '.$err);
        }

        $fila = ApiAnita::primeraFilaLista((string) $raw);
        if ($fila === null || ! isset($fila->num_ult_numero)) {
            throw new \RuntimeException(
                'Numerador Anita inexistente o sin num_ult_numero (num_clave='.$claveNumerador.').'
            );
        }

        return max(0, (int) $fila->num_ult_numero);
    }

    public static function actualizarNumerador(string $claveNumerador, int $numero): void
    {
        if ($numero <= 0) {
            throw new \InvalidArgumentException('Número de recepción Anita inválido.');
        }

        $clave = self::escSqlLiteral($claveNumerador);
        $api = new ApiAnita;
        $raw = $api->apiCallEscritura([
            'acc' => 'update',
            'sistema' => self::sistemaVentas(),
            'tabla' => 'numerador',
            'valores' => 'num_ult_numero = '.(int) $numero,
            'whereArmado' => " WHERE num_clave = '".$clave."'",
        ], 'recepcion numerador COM update');

        $err = ApiAnita::extraerMensajeError($raw);
        if ($err !== null) {
            throw new \RuntimeException('No se pudo actualizar numerador Anita (num_clave='.$claveNumerador.'): '.$err);
        }
    }

    /**
     * Máximo COM global: ERP + recepmae + numerador ventas (num_clave 120 vía t_comp COM).
     */
    public static function ultimoNumeroComGlobal(): int
    {
        $maxErp = (int) \Illuminate\Support\Facades\DB::table('recepcion_proveedor')->max('numerorecepcion');

        $maxRecepmae = 0;
        try {
            $maxRecepmae = RecepcionProveedorAnitaColisionSupport::maxNumeroRecepmaeGlobal();
        } catch (\Throwable $e) {
            Log::warning('RecepcionProveedorAnitaNumeracion: no se pudo leer max recepmae', [
                'error' => $e->getMessage(),
            ]);
        }

        $maxNumerador = 0;
        try {
            $maxNumerador = self::leerUltimoNumero(self::resolverClaveNumeradorDesdeTComp());
        } catch (\Throwable $e) {
            Log::warning('RecepcionProveedorAnitaNumeracion: no se pudo leer numerador COM', [
                'error' => $e->getMessage(),
            ]);
        }

        return max($maxErp, $maxRecepmae, $maxNumerador);
    }

    /**
     * Alinea numerador 120 (último COM Anita desktop) con max(ERP, recepmae, numerador actual).
     *
     * @return array{antes: int, despues: int, num_clave: string, max_erp: int, max_recepmae: int}
     */
    public static function sincronizarNumeradorComGlobal(): array
    {
        $claveNumerador = self::resolverClaveNumeradorDesdeTComp();
        $antes = self::leerUltimoNumero($claveNumerador);
        $maxErp = (int) \Illuminate\Support\Facades\DB::table('recepcion_proveedor')->max('numerorecepcion');
        $maxRecepmae = RecepcionProveedorAnitaColisionSupport::maxNumeroRecepmaeGlobal();
        $despues = max($maxErp, $maxRecepmae, $antes);

        if ($despues > $antes) {
            self::actualizarNumerador($claveNumerador, $despues);
        }

        Log::info('RecepcionProveedorAnitaNumeracion: numerador COM sincronizado', [
            'num_clave' => $claveNumerador,
            'antes' => $antes,
            'despues' => $despues,
            'max_erp' => $maxErp,
            'max_recepmae' => $maxRecepmae,
        ]);

        return [
            'antes' => $antes,
            'despues' => $despues,
            'num_clave' => $claveNumerador,
            'max_erp' => $maxErp,
            'max_recepmae' => $maxRecepmae,
        ];
    }

    /** Tras asignar numerorecepcion en ERP, deja numerador ≥ max(ERP, recepmae, número asignado). */
    public static function registrarNumeroAsignadoEnNumerador(int $numero): void
    {
        if ($numero <= 0) {
            return;
        }

        try {
            $claveNumerador = self::resolverClaveNumeradorDesdeTComp();
            $ultimoNumerador = self::leerUltimoNumero($claveNumerador);
            $maxErp = (int) \Illuminate\Support\Facades\DB::table('recepcion_proveedor')->max('numerorecepcion');
            $maxRecepmae = 0;
            try {
                $maxRecepmae = RecepcionProveedorAnitaColisionSupport::maxNumeroRecepmaeGlobal();
            } catch (\Throwable $e) {
                Log::warning('RecepcionProveedorAnitaNumeracion: no se pudo leer max recepmae al registrar COM', [
                    'numero' => $numero,
                    'error' => $e->getMessage(),
                ]);
            }

            $objetivo = max($numero, $ultimoNumerador, $maxErp, $maxRecepmae);
            if ($objetivo > $ultimoNumerador) {
                self::actualizarNumerador($claveNumerador, $objetivo);
            }
        } catch (\Throwable $e) {
            Log::warning('RecepcionProveedorAnitaNumeracion: no se pudo actualizar numerador tras asignar COM', [
                'numero' => $numero,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Primer COM libre (ERP + recepmae) y avance del numerador Anita (num_clave COM).
     *
     * @param  int|null  $excluirRecepcionId  Al renumerar borrador, excluir la fila actual en ERP.
     * @param  int|null  $pisoMinimo  Mínimo para max(base global, piso) antes de buscar hueco.
     */
    public static function asignarNumeroComGlobalLibre(?int $excluirRecepcionId = null, ?int $pisoMinimo = null): int
    {
        $base = self::ultimoNumeroComGlobal();
        if ($pisoMinimo !== null && $pisoMinimo > $base) {
            $base = $pisoMinimo;
        }

        $numero = RecepcionProveedorAnitaColisionSupport::primerNumeroComDisponible($base + 1, $excluirRecepcionId);

        if (config('recepcion_proveedor.anita.reservar_numerador_anita', false)) {
            try {
                $numeroReservado = self::reservarSiguienteNumero($base > 0 ? $base : null);
                if ($numeroReservado > $numero) {
                    $numero = RecepcionProveedorAnitaColisionSupport::primerNumeroComDisponible(
                        $numeroReservado,
                        $excluirRecepcionId
                    );
                }
            } catch (\Throwable $e) {
                Log::warning('RecepcionProveedorAnitaNumeracion: reserva COM Anita no disponible', [
                    'base' => $base,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        self::registrarNumeroAsignadoEnNumerador($numero);

        return $numero;
    }

    private static function escSqlLiteral(string $value): string
    {
        return str_replace("'", "''", trim($value));
    }
}
