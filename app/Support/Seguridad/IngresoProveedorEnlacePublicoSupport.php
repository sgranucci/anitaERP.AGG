<?php

namespace App\Support\Seguridad;

use App\Models\Seguridad\IngresoProveedor;
use App\Support\Configuracion\ArbolAprobacionEnlaceSupport;
use Illuminate\Support\Str;

/**
 * Enlace de mail al ticket: mismo criterio que el árbol (hash, sin login ni can()).
 */
final class IngresoProveedorEnlacePublicoSupport
{
    public static function asegurarHash(IngresoProveedor $ticket): string
    {
        $actual = trim((string) ($ticket->hashvisualizar ?? ''));
        if ($actual !== '') {
            return $actual;
        }
        $hash = Str::lower(Str::random(48));
        $ticket->forceFill(['hashvisualizar' => $hash])->save();

        return $hash;
    }

    public static function hashValido(IngresoProveedor $ticket, string $hash): bool
    {
        $almacenado = trim((string) ($ticket->hashvisualizar ?? ''));
        $recibido = ArbolAprobacionEnlaceSupport::normalizarHashRecibido($hash);

        return $almacenado !== '' && $recibido !== '' && hash_equals($almacenado, $recibido);
    }

    public static function urlVisualizar(int $ticketId): ?string
    {
        if ($ticketId <= 0) {
            return null;
        }
        $ticket = IngresoProveedor::query()->find($ticketId);
        if (! $ticket) {
            return null;
        }
        $hash = self::asegurarHash($ticket);
        $ip = (string) config('arbolaprobacion.ip_link', config('app.url'));

        return ArbolAprobacionEnlaceSupport::enlaceVisualizar(
            $ip,
            'seguridad/ingreso-proveedor/visualizar',
            (int) $ticket->id,
            $hash
        );
    }

    public static function urlArchivo(int $ticketId, int $archivoId, string $hash, bool $inline = false): string
    {
        $url = route('visualizar_archivo_ingreso_proveedor', [
            'id' => $ticketId,
            'hash' => $hash,
            'archivo' => $archivoId,
        ]);
        if ($inline) {
            $url .= (str_contains($url, '?') ? '&' : '?').'inline=1';
        }

        return $url;
    }
}
