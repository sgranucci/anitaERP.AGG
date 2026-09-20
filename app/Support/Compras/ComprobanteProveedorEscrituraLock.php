<?php

namespace App\Support\Compras;

use Illuminate\Support\Facades\Cache;
use RuntimeException;

/**
 * Exclusión mutua por comprobante de proveedor mientras se contabiliza, descontabiliza,
 * edita o elimina.
 *
 * El asiento y la CC viven en MySQL, pero Anita es otra base y queda fuera del
 * DB::transaction. Sin este candado, dos clics en «Contabilizar» leen los dos BORRADOR,
 * los dos pasan el guard y el segundo genera un asiento huérfano (la CC se salva por el
 * unique de cuota; el asiento no).
 *
 * El lockForUpdate dentro del TX MySQL sigue haciendo falta: este candado cubre la
 * ventana completa, incluido el round-trip a Anita.
 */
final class ComprobanteProveedorEscrituraLock
{
    /** @var array<int, true> */
    private static array $retenidosEnEsteRequest = [];

    public static function clave(int $comprobanteId): string
    {
        return 'compras:comprobante-proveedor:escritura:'.max(0, $comprobanteId);
    }

    public static function segundosBloqueo(): int
    {
        return max(30, (int) config('compras.comprobante_escritura_lock_segundos', 120));
    }

    public static function segundosEspera(): int
    {
        return max(5, (int) config('compras.comprobante_escritura_lock_espera_segundos', 30));
    }

    /**
     * @template T
     * @param  callable(): T  $accion
     * @return T
     */
    public static function ejecutar(int $comprobanteId, callable $accion): mixed
    {
        if ($comprobanteId <= 0) {
            return $accion();
        }

        // Reentrante en el mismo request: editar un contabilizado toma el candado y después
        // llama a descontabilizar → contabilizar; sin esto el segundo block() espera al primero
        // hasta timeout.
        if (isset(self::$retenidosEnEsteRequest[$comprobanteId])) {
            return $accion();
        }

        $lock = Cache::lock(self::clave($comprobanteId), self::segundosBloqueo());
        if (! $lock->block(self::segundosEspera())) {
            throw new RuntimeException(
                'Otro operador está modificando este comprobante. Espere un momento y reintente.'
            );
        }

        self::$retenidosEnEsteRequest[$comprobanteId] = true;
        try {
            return $accion();
        } finally {
            unset(self::$retenidosEnEsteRequest[$comprobanteId]);
            optional($lock)->release();
        }
    }

    /**
     * Candado sobre la precarga al generar el borrador: evita dos comprobantes desde el
     * mismo doble clic en «Generar desde precarga».
     *
     * @template T
     * @param  callable(): T  $accion
     * @return T
     */
    public static function ejecutarSobrePrecarga(int $precargaId, callable $accion): mixed
    {
        if ($precargaId <= 0) {
            return $accion();
        }

        $lock = Cache::lock(
            'compras:comprobante-proveedor:desde-precarga:'.$precargaId,
            self::segundosBloqueo()
        );
        if (! $lock->block(self::segundosEspera())) {
            throw new RuntimeException(
                'Otro operador está generando el comprobante desde esta precarga. Reintente.'
            );
        }

        try {
            return $accion();
        } finally {
            optional($lock)->release();
        }
    }
}
