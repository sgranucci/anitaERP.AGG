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

        return strtoupper((string) ($ticket->visitante_tipo ?? self::PROVEEDOR)) === self::VISITANTE;
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
        $flagVisitante = $data['es_visitante'] ?? null;
        $esVisitante = filter_var($flagVisitante, FILTER_VALIDATE_BOOLEAN)
            || strtoupper((string) ($data['visitante_tipo'] ?? '')) === self::VISITANTE;

        if ((int) ($data['proveedor_id'] ?? 0) > 0) {
            $esVisitante = false;
        }

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
