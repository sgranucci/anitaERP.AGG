<?php

namespace App\Support\Configuracion;

use App\Models\Configuracion\Arbolaprobacion_Movimiento;
use Carbon\Carbon;
use Illuminate\Support\Collection;

final class ArbolAprobacionEnlaceSupport
{
    /** Caracteres conflictivos en rutas URL al persistir el hash (bcrypt incluye `$`). */
    public const CARACTERES_REEMPLAZO = ['/', '%', '$'];

    public static function prepararHashAlmacenado(string $hashGenerado): string
    {
        return str_replace(self::CARACTERES_REEMPLAZO, '+', $hashGenerado);
    }

    public static function normalizarHashRecibido(string $hash): string
    {
        $hash = trim(rawurldecode($hash));
        // Algunos proxies/clientes convierten '+' en espacio dentro de la ruta.
        return str_replace(' ', '+', $hash);
    }

    public static function hashesCoinciden(string $recibido, string $almacenado): bool
    {
        $recibido = self::normalizarHashRecibido($recibido);
        $almacenado = self::normalizarHashRecibido($almacenado);

        if ($recibido === '' || $almacenado === '') {
            return false;
        }

        return hash_equals($almacenado, $recibido);
    }

    public static function enlaceAbsoluto(string $ipBase, string $rutaRelativa, string $hash): string
    {
        $rutaRelativa = trim($rutaRelativa, '/');
        $hash = trim($hash);

        return rtrim($ipBase, '/').'/anitaERP/public/'.$rutaRelativa.'/'.rawurlencode($hash);
    }

    public static function enlaceAprobar(string $ipBase, string $tipoComprobante, int $comprobanteId, string $hash): string
    {
        return self::enlaceAbsoluto(
            $ipBase,
            'arbolaprobacion/aprobar/'.$tipoComprobante.'/'.$comprobanteId,
            $hash
        );
    }

    public static function enlaceRechazo(string $ipBase, string $tipoComprobante, int $comprobanteId, string $hash): string
    {
        return self::enlaceAbsoluto(
            $ipBase,
            'arbolaprobacion/buscarechazo/'.$tipoComprobante.'/'.$comprobanteId,
            $hash
        );
    }

    public static function enlaceVisualizar(string $ipBase, string $rutaVisualizar, int $comprobanteId, string $hash): string
    {
        return self::enlaceAbsoluto($ipBase, $rutaVisualizar.'/'.$comprobanteId, $hash);
    }

    /**
     * @param  iterable<int, Arbolaprobacion_Movimiento>|Collection<int, Arbolaprobacion_Movimiento>  $movimientos
     */
    public static function mensajeEnlaceNoDisponible(iterable $movimientos, string $hashRecibido, string $modo): string
    {
        $hashRecibido = self::normalizarHashRecibido($hashRecibido);
        $campoHash = $modo === 'rechazo' ? 'hashrechazo' : 'hashaprobacion';
        $enum = Arbolaprobacion_Movimiento::$enumEstado;
        $nombreAprobado = $enum[array_search('A', array_column($enum, 'valor'))]['nombre'];
        $nombreRechazado = $enum[array_search('R', array_column($enum, 'valor'))]['nombre'];

        foreach ($movimientos as $movimiento) {
            $hashAlmacenado = (string) ($movimiento->{$campoHash} ?? '');
            if ($hashAlmacenado === '' || ! self::hashesCoinciden($hashRecibido, $hashAlmacenado)) {
                continue;
            }

            if ($movimiento->estado === $nombreAprobado) {
                $fecha = filled($movimiento->fechaproceso)
                    ? Carbon::parse($movimiento->fechaproceso)->format('d/m/Y H:i')
                    : null;

                return $fecha
                    ? 'Este paso ya fue aprobado el '.$fecha.'. Si se gestionó desde el sistema o por otro enlace, el correo ya no puede utilizarse.'
                    : 'Este paso ya fue aprobado. El enlace del correo ya no puede utilizarse.';
            }

            if ($movimiento->estado === $nombreRechazado) {
                $fecha = filled($movimiento->fechaproceso)
                    ? Carbon::parse($movimiento->fechaproceso)->format('d/m/Y H:i')
                    : null;

                return $fecha
                    ? 'Este paso ya fue rechazado el '.$fecha.'. El enlace del correo ya no es válido.'
                    : 'Este paso ya fue rechazado. El enlace del correo ya no es válido.';
            }

            return 'Este paso ya fue gestionado (estado: '.$movimiento->estado.'). El enlace del correo ya no es válido.';
        }

        return 'No tiene aprobación pendiente o el enlace ya no es válido. Verifique que el enlace del correo esté completo.';
    }
}
