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

    private static function escSqlLiteral(string $value): string
    {
        return str_replace("'", "''", trim($value));
    }
}
