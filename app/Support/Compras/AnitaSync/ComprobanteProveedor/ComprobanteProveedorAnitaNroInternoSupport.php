<?php

namespace App\Support\Compras\AnitaSync\ComprobanteProveedor;

use App\ApiAnita;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Nro. interno Anita (a-compprov lee_num_comp "INT"): t_comp INT → numerador ventas 208.
 * No usar MAX(compra)+1: Anita nativo sigue consumiendo 208 y choca concmov.
 */
final class ComprobanteProveedorAnitaNroInternoSupport
{
    public function siguiente(): int
    {
        $segundos = max(5, (int) config('comprobante_proveedor.numeracion_lock_segundos', 20));
        $lock = Cache::lock('comprobante_proveedor:numeracion:int', $segundos);

        return $lock->block($segundos, function (): int {
            $clave = $this->resolverClaveNumerador();
            $ultimoNumerador = $this->leerNumerador($clave);
            $maxCompra = $this->maxCampo('compra', 'com_nro_interno');
            $maxPromov = $this->maxCampo('promov', 'prov_nro_interno');

            $siguiente = ComprobanteProveedorConcmovPertenenciaSupport::calcularSiguienteInterno(
                $ultimoNumerador,
                $maxCompra,
                $maxPromov
            );

            while ($this->existeNroInterno($siguiente)) {
                $siguiente++;
            }

            $this->actualizarNumerador($clave, $siguiente);

            Log::info('comprobante_proveedor.anita_nro_interno.reservado', [
                'clave_numerador' => $clave,
                'ultimo_numerador' => $ultimoNumerador,
                'max_compra' => $maxCompra,
                'max_promov' => $maxPromov,
                'asignado' => $siguiente,
            ]);

            return $siguiente;
        });
    }

    public function resolverClaveNumerador(): string
    {
        $claveTcomp = (string) config('comprobante_proveedor.anita_tcomp_clave_interno', 'INT');
        $api = new ApiAnita;
        $raw = $api->apiCallEscritura([
            'acc' => 'list',
            'sistema' => (string) config('comprobante_proveedor.anita_sistema_tcomp', 'compras'),
            'tabla' => 't_comp',
            'campos' => 'tcomp_refer',
            'whereArmado' => ' WHERE tcomp_clave = '.$this->escSqlLiteral($claveTcomp),
        ], 'comprobante proveedor t_comp INT');

        $err = ApiAnita::extraerMensajeError($raw);
        if ($err !== null) {
            throw new RuntimeException('No se pudo leer t_comp '.$claveTcomp.' en Anita: '.$err);
        }

        $fila = ApiAnita::primeraFilaLista((string) $raw);
        $refer = trim((string) ($fila->tcomp_refer ?? ''));
        if ($refer === '' || $refer === '000') {
            throw new RuntimeException('t_comp sin tcomp_refer válido para clave '.$claveTcomp.'.');
        }

        return $refer;
    }

    private function leerNumerador(string $claveNumerador): int
    {
        $api = new ApiAnita;
        $raw = $api->apiCallEscritura([
            'acc' => 'list',
            'sistema' => (string) config('comprobante_proveedor.anita_sistema_numerador', 'ventas'),
            'tabla' => 'numerador',
            'campos' => 'num_ult_numero',
            'whereArmado' => ' WHERE num_clave = '.$this->escSqlLiteral($claveNumerador),
        ], 'comprobante proveedor numerador INT lectura');

        $err = ApiAnita::extraerMensajeError($raw);
        if ($err !== null) {
            throw new RuntimeException('No se pudo leer numerador Anita ('.$claveNumerador.'): '.$err);
        }

        $fila = ApiAnita::primeraFilaLista((string) $raw);
        if ($fila === null || ! isset($fila->num_ult_numero)) {
            throw new RuntimeException('Numerador Anita inexistente (num_clave='.$claveNumerador.').');
        }

        return max(0, (int) $fila->num_ult_numero);
    }

    private function actualizarNumerador(string $claveNumerador, int $numero): void
    {
        if ($numero <= 0) {
            throw new \InvalidArgumentException('Nro. interno Anita inválido.');
        }

        $api = new ApiAnita;
        $raw = $api->apiCallEscritura([
            'acc' => 'update',
            'sistema' => (string) config('comprobante_proveedor.anita_sistema_numerador', 'ventas'),
            'tabla' => 'numerador',
            'valores' => 'num_ult_numero = '.(int) $numero,
            'whereArmado' => ' WHERE num_clave = '.$this->escSqlLiteral($claveNumerador),
        ], 'comprobante proveedor numerador INT update');

        $err = ApiAnita::extraerMensajeError($raw);
        if ($err !== null) {
            Log::error('comprobante_proveedor.anita_nro_interno.update_fail', [
                'clave' => $claveNumerador,
                'numero' => $numero,
                'error' => $err,
            ]);
            throw new RuntimeException('No se pudo actualizar numerador Anita INT: '.$err);
        }
    }

    private function existeNroInterno(int $nroInterno): bool
    {
        if ($nroInterno <= 0) {
            return true;
        }

        $api = new ApiAnita;
        $raw = $api->apiCall([
            'acc' => 'list',
            'sistema' => 'compras',
            'tabla' => 'compra',
            'campos' => 'com_nro_interno',
            'whereArmado' => ' WHERE com_nro_interno = '.(int) $nroInterno,
        ]);
        $filas = ApiAnita::decodificarListaFilas($raw);

        return $filas !== [];
    }

    private function maxCampo(string $tabla, string $campo): int
    {
        $api = new ApiAnita;
        $raw = $api->apiCall([
            'acc' => 'list',
            'sistema' => 'compras',
            'tabla' => $tabla,
            'campos' => 'MAX('.$campo.') AS max_nro',
            'whereArmado' => ' WHERE 1=1 ',
        ]);

        $rows = ApiAnita::decodificarListaFilas($raw);
        if ($rows === []) {
            return 0;
        }

        $row = (array) $rows[0];

        return (int) ($row['max_nro'] ?? $row['MAX'] ?? $row[strtolower('max_nro')] ?? 0);
    }

    private function escSqlLiteral(string $valor): string
    {
        return "'".str_replace("'", "''", $valor)."'";
    }
}
