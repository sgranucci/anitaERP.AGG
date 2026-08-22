<?php

namespace App\Support\Seguridad;

use App\Models\Seguridad\IngresoProveedor;

final class IngresoProveedorVisitanteSupport
{
    public const PROVEEDOR = 'PROVEEDOR';

    public const VISITANTE = 'VISITANTE';

    public static function esVisitante(?IngresoProveedor $ticket): bool
    {
        if (! $ticket) {
            return false;
        }

        return strtoupper((string) ($ticket->visitante_tipo ?? self::PROVEEDOR)) === self::VISITANTE
            || (int) ($ticket->proveedor_id ?? 0) <= 0;
    }

    public static function etiquetaOrigen(?IngresoProveedor $ticket): string
    {
        if (! $ticket) {
            return '';
        }
        if (self::esVisitante($ticket)) {
            $nombre = trim((string) ($ticket->visitante_nombre ?? ''));

            return $nombre !== '' ? $nombre : 'Visitante';
        }

        return (string) ($ticket->proveedores->nombre ?? '');
    }

    public static function etiquetaTipo(?IngresoProveedor $ticket): string
    {
        return self::esVisitante($ticket) ? 'Visitante' : 'Proveedor';
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function normalizarAlGuardar(array $data): array
    {
        $esVisitante = ! empty($data['es_visitante'])
            || strtoupper((string) ($data['visitante_tipo'] ?? '')) === self::VISITANTE;

        if ($esVisitante) {
            $data['visitante_tipo'] = self::VISITANTE;
            $data['proveedor_id'] = null;
            $data['ordencompra_id'] = null;
            $data['visitante_nombre'] = trim((string) ($data['visitante_nombre'] ?? ''));
        } else {
            $data['visitante_tipo'] = self::PROVEEDOR;
            $data['visitante_nombre'] = null;
        }

        unset($data['es_visitante']);

        return $data;
    }
}
