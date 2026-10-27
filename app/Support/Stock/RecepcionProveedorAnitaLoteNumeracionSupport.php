<?php

namespace App\Support\Stock;

use App\ApiAnita;
use Illuminate\Support\Facades\Log;

/**
 * recv_nro_interno de recepmov. Anita desktop lo toma de
 * shared.numabm (compras / a-stock.c / referencia 2).
 */
final class RecepcionProveedorAnitaLoteNumeracionSupport
{
    public static function sistemaShared(): string
    {
        return 'shared';
    }

    public static function whereNumabm(): string
    {
        return " WHERE numa_sistema = 'compras'"
            ." AND numa_programa = 'a-stock.c'"
            ." AND numa_referencia = '2'";
    }

    public static function leerUltimoNumabm(): int
    {
        $api = new ApiAnita;
        $raw = $api->apiCallEscritura([
            'acc' => 'list',
            'sistema' => self::sistemaShared(),
            'tabla' => 'numabm',
            'campos' => 'numa_ult_numero',
            'whereArmado' => self::whereNumabm(),
        ], 'recepcion lote numabm lectura');

        $err = ApiAnita::extraerMensajeError($raw);
        if ($err !== null) {
            throw new \RuntimeException('No se pudo leer numabm de lote (a-stock.c ref 2): '.$err);
        }

        $fila = ApiAnita::primeraFilaLista((string) $raw);
        if ($fila === null || ! isset($fila->numa_ult_numero)) {
            throw new \RuntimeException('numabm de lote inexistente (a-stock.c referencia 2).');
        }

        return max(0, (int) $fila->numa_ult_numero);
    }

    public static function leerMaxRecepmov(): int
    {
        $api = new ApiAnita;
        $raw = $api->apiCall([
            'acc' => 'list',
            'sistema' => (string) config('recepcion_proveedor.anita.sistema_compras', 'compras'),
            'tabla' => 'recepmov',
            'campos' => 'MAX(recv_nro_interno) AS m',
            'curl_timeout' => 90,
        ]);

        $err = ApiAnita::extraerMensajeError($raw);
        if ($err !== null) {
            throw new \RuntimeException('No se pudo leer el máximo recv_nro_interno: '.$err);
        }

        $fila = ApiAnita::primeraFilaLista((string) $raw);

        return max(0, (int) ($fila->m ?? 0));
    }

    public static function actualizarNumabm(int $numero): void
    {
        if ($numero <= 0) {
            throw new \InvalidArgumentException('Número interno de lote inválido.');
        }

        $api = new ApiAnita;
        $raw = $api->apiCallEscritura([
            'acc' => 'update',
            'sistema' => self::sistemaShared(),
            'tabla' => 'numabm',
            'valores' => 'numa_ult_numero = '.(int) $numero,
            'whereArmado' => self::whereNumabm(),
        ], 'recepcion lote numabm update');

        $err = ApiAnita::extraerMensajeError($raw);
        if ($err !== null) {
            throw new \RuntimeException('No se pudo actualizar numabm de lote: '.$err);
        }
    }

    public static function baseActual(): int
    {
        return max(self::leerUltimoNumabm(), self::leerMaxRecepmov());
    }

    /**
     * Reserva el siguiente número y deja numabm en ese valor.
     */
    public static function reservarSiguiente(): int
    {
        $siguiente = self::baseActual() + 1;
        self::actualizarNumabm($siguiente);

        Log::info('RecepcionProveedorAnitaLoteNumeracion: número de lote reservado', [
            'asignado' => $siguiente,
        ]);

        return $siguiente;
    }

    /**
     * Reserva un bloque contiguo. Devuelve el primero.
     */
    public static function reservarBloque(int $cantidad): int
    {
        if ($cantidad <= 0) {
            throw new \InvalidArgumentException('El bloque de lotes debe ser mayor a 0.');
        }

        $primero = self::baseActual() + 1;
        $ultimo = $primero + $cantidad - 1;
        self::actualizarNumabm($ultimo);

        Log::info('RecepcionProveedorAnitaLoteNumeracion: bloque de lotes reservado', [
            'desde' => $primero,
            'hasta' => $ultimo,
            'cantidad' => $cantidad,
        ]);

        return $primero;
    }
}
