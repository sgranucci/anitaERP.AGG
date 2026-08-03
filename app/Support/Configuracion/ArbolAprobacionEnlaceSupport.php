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
        $ipBase = trim($ipBase);
        // Clientes de mail no tratan "10.20.x.x/ruta" como URL; hace falta el esquema.
        if ($ipBase !== '' && ! preg_match('#^https?://#i', $ipBase)) {
            $ipBase = 'http://'.$ipBase;
        }

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

    /** Descarga pública (mail árbol SP): comprobante PDF + adjuntos, autorizada por hashvisualizar. */
    public static function enlaceDescargaPaqueteSolicitudpago(string $ipBase, int $solicitudpagoId, string $hash): string
    {
        return self::enlaceAbsoluto(
            $ipBase,
            'solicitudpago/solicitudpago/'.$solicitudpagoId.'/descargar-paquete',
            $hash
        );
    }

    /**
     * Alta de Ingresos/Egresos precargada con la SP (requiere sesión; mismo destino que irAPago).
     *
     * @param  array{empresa_id?: int|null, proveedor_id?: int|null, detalle?: string|null}  $params
     */
    public static function enlaceCrearIngresoEgresoDesdeSp(string $ipBase, int $solicitudpagoId, array $params = []): string
    {
        $ipBase = trim($ipBase);
        if ($ipBase !== '' && ! preg_match('#^https?://#i', $ipBase)) {
            $ipBase = 'http://'.$ipBase;
        }

        $query = array_filter([
            'solicitudpago_id' => $solicitudpagoId > 0 ? $solicitudpagoId : null,
            'empresa_id' => ! empty($params['empresa_id']) ? (int) $params['empresa_id'] : null,
            'proveedor_id' => ! empty($params['proveedor_id']) ? (int) $params['proveedor_id'] : null,
            'detalle' => trim((string) ($params['detalle'] ?? '')) !== ''
                ? (string) $params['detalle']
                : null,
        ], static fn ($v) => $v !== null && $v !== '');

        $base = rtrim($ipBase, '/').'/anitaERP/public/caja/ingresoegreso/crear';

        return $query === [] ? $base : $base.'?'.http_build_query($query);
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
