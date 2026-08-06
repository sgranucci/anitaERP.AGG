<?php

namespace App\Support\Compras;

use Illuminate\Http\Request;

/**
 * Filtros del listado de órdenes de compra del portal de proveedores.
 */
final class PortalProveedorOrdencompraListadoFiltros
{
    public const GRUPO_ACTIVAS = 'activas';

    public const GRUPO_CERRADAS = 'cerradas';

    public const GRUPO_TODAS = 'todas';

    /** @var list<array{valor: string, etiqueta: string}> */
    public const OPCIONES_GRUPO = [
        ['valor' => self::GRUPO_ACTIVAS, 'etiqueta' => 'Activas / pendientes'],
        ['valor' => self::GRUPO_CERRADAS, 'etiqueta' => 'Cerradas'],
        ['valor' => self::GRUPO_TODAS, 'etiqueta' => 'Todas'],
    ];

    /**
     * Estados de cabecera visibles como "activas / pendientes" para el proveedor.
     *
     * @return list<string>
     */
    public static function estadosActivas(): array
    {
        return [
            OrdencompraEstados::PENDIENTE,
            OrdencompraEstados::APROBADA,
            OrdencompraEstados::CUMPLIDA,
        ];
    }

    /**
     * Estados de factura visibles en el portal (sin borradores internos ni anuladas).
     *
     * @return list<string>
     */
    public static function estadosFacturaVisibles(): array
    {
        return [
            ComprobanteProveedorEstados::PRECARGA,
            ComprobanteProveedorEstados::PENDIENTE_REVISION,
            ComprobanteProveedorEstados::PENDIENTE_APROBACION,
            ComprobanteProveedorEstados::PENDIENTE_DIFERENCIA,
            ComprobanteProveedorEstados::APROBADO,
            ComprobanteProveedorEstados::CONTABILIZADO,
            ComprobanteProveedorEstados::ERROR_SYNC,
        ];
    }

    /**
     * @return array{
     *   proveedor_id: int,
     *   empresa_id: int|null,
     *   grupo_estado: string,
     *   estadoordencompra: string,
     *   numero: string,
     *   fecha_desde: string,
     *   fecha_hasta: string,
     *   consultar: bool
     * }
     */
    public static function resolverDesdeRequest(Request $request): array
    {
        $grupo = trim((string) $request->input('grupo_estado', self::GRUPO_ACTIVAS));
        $gruposOk = array_column(self::OPCIONES_GRUPO, 'valor');
        if (! in_array($grupo, $gruposOk, true)) {
            $grupo = self::GRUPO_ACTIVAS;
        }

        $estado = strtoupper(trim((string) $request->input('estadoordencompra', '')));
        if ($estado !== '' && ! OrdencompraEstados::esNombreValido($estado)) {
            $estado = '';
        }

        $empresaId = (int) $request->input('empresa_id', 0);

        return [
            'proveedor_id' => (int) $request->input('proveedor_id', 0),
            'empresa_id' => $empresaId > 0 ? $empresaId : null,
            'grupo_estado' => $grupo,
            'estadoordencompra' => $estado,
            'numero' => trim((string) $request->input('numero', '')),
            'fecha_desde' => trim((string) $request->input('fecha_desde', '')),
            'fecha_hasta' => trim((string) $request->input('fecha_hasta', '')),
            'consultar' => $request->boolean('consultar') || $request->has('proveedor_id'),
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    public static function paraQueryString(array $filtros): array
    {
        $out = [];
        foreach (['proveedor_id', 'empresa_id', 'grupo_estado', 'estadoordencompra', 'numero', 'fecha_desde', 'fecha_hasta'] as $k) {
            $v = $filtros[$k] ?? null;
            if ($v !== null && $v !== '' && $v !== 0) {
                $out[$k] = $v;
            }
        }
        if (! empty($filtros['consultar'])) {
            $out['consultar'] = 1;
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function tieneCriteriosAplicados(array $filtros): bool
    {
        if (($filtros['grupo_estado'] ?? self::GRUPO_ACTIVAS) !== self::GRUPO_ACTIVAS) {
            return true;
        }
        foreach (['empresa_id', 'estadoordencompra', 'numero', 'fecha_desde', 'fecha_hasta'] as $k) {
            $v = $filtros[$k] ?? null;
            if ($v !== null && $v !== '' && $v !== 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function subtituloFiltros(array $filtros): string
    {
        $partes = [];
        $grupo = $filtros['grupo_estado'] ?? self::GRUPO_ACTIVAS;
        foreach (self::OPCIONES_GRUPO as $op) {
            if ($op['valor'] === $grupo) {
                $partes[] = $op['etiqueta'];
                break;
            }
        }
        if (! empty($filtros['estadoordencompra'])) {
            $partes[] = 'Estado '.$filtros['estadoordencompra'];
        }
        if (! empty($filtros['numero'])) {
            $partes[] = 'Nº OC '.$filtros['numero'];
        }
        if (! empty($filtros['fecha_desde'])) {
            $partes[] = 'Desde '.$filtros['fecha_desde'];
        }
        if (! empty($filtros['fecha_hasta'])) {
            $partes[] = 'Hasta '.$filtros['fecha_hasta'];
        }

        return $partes === [] ? 'Sin filtros adicionales' : implode(' · ', $partes);
    }

    /**
     * Etiqueta legible de factura para el portal.
     */
    public static function etiquetaFactura(object $comp): string
    {
        $abrev = optional($comp->tipotransaccion_compras)->abreviatura ?: 'FC';

        return sprintf(
            '%s %s %04d-%s',
            $abrev,
            $comp->letra ?? '',
            (int) ($comp->sucursal ?? 0),
            (string) ($comp->numerocomprobante ?? '')
        );
    }
}
