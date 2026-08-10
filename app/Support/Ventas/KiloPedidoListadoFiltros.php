<?php

namespace App\Support\Ventas;

use App\Support\Database\SqlDialectSupport;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;

final class KiloPedidoListadoFiltros
{
    public const TIPOS_LISTADO = [
        'ABRE' => 'Abre ítems de pedidos',
        'TOTAL' => 'Totales de pedidos',
    ];

    public const ESTADOS = [
        'PENDIENTE' => 'Pedidos pendientes de facturar',
        'TODO' => 'Todos los pedidos',
    ];

    private const REPARTO_HASTA_ABIERTO = 999999;

    /**
     * @return array<string, mixed>
     */
    public static function resolverDesdeRequest(Request $request): array
    {
        $tipolistado = strtoupper(trim((string) $request->input('tipolistado', 'TOTAL')));
        if (! array_key_exists($tipolistado, self::TIPOS_LISTADO)) {
            $tipolistado = 'TOTAL';
        }

        $estado = strtoupper(trim((string) $request->input('estado', 'PENDIENTE')));
        if (! array_key_exists($estado, self::ESTADOS)) {
            $estado = 'PENDIENTE';
        }

        $repartoDesde = trim((string) $request->input('reparto_desde', $request->input('codigodesdetransporte', '')));
        $repartoHasta = trim((string) $request->input('reparto_hasta', $request->input('codigohastatransporte', '')));

        [$repartoDesde, $repartoHasta] = self::normalizarRangoRepartos($repartoDesde, $repartoHasta);

        $fechaDesde = trim((string) $request->input('fecha_desde', $request->input('desdefecha', date('Y-m-d'))));
        $fechaHasta = trim((string) $request->input('fecha_hasta', $request->input('hastafecha', date('Y-m-d'))));

        return [
            'fecha_desde' => $fechaDesde,
            'fecha_hasta' => $fechaHasta,
            'reparto_desde' => $repartoDesde,
            'reparto_hasta' => $repartoHasta,
            'tipolistado' => $tipolistado,
            'estado' => $estado,
        ];
    }

    /**
     * @return array{0: string, 1: string}
     */
    public static function normalizarRangoRepartos(string $desde, string $hasta): array
    {
        $desde = trim($desde);
        $hasta = trim($hasta);

        if (str_contains($desde, '/')) {
            $partes = array_map('trim', explode('/', $desde, 2));
            $desde = $partes[0] ?? '';
            if (($partes[1] ?? '') !== '') {
                $hasta = $partes[1];
            }
        }

        if ($desde === '' && $hasta !== '' && ! self::esListaRepartos($hasta)) {
            $desde = '1';
        }

        if (! self::esListaRepartos($desde)) {
            $desdeNum = self::codigoRepartoANumero($desde);
            $hastaNum = self::codigoRepartoANumero($hasta);
            if ($desdeNum !== null && $hastaNum !== null
                && $hastaNum !== self::REPARTO_HASTA_ABIERTO
                && $desdeNum > $hastaNum) {
                [$desde, $hasta] = [(string) $hastaNum, (string) $desdeNum];
            }
        }

        return [$desde, $hasta];
    }

    public static function esListaRepartos(string $valor): bool
    {
        return str_contains($valor, ',') || str_contains($valor, ';');
    }

    /**
     * @param  EloquentBuilder|\Illuminate\Database\Query\Builder  $query
     */
    public static function aplicarFiltroRepartoEnQuery($query, string $desde, string $hasta): void
    {
        $desde = trim($desde);
        $hasta = trim($hasta);

        if ($desde === '' && $hasta === '') {
            return;
        }

        $query->whereNotNull('transporte.id');

        if (self::esListaRepartos($desde)) {
            $codigos = self::parseListaRepartos($desde);
            if ($codigos === []) {
                return;
            }

            $query->where(function ($q) use ($codigos) {
                foreach ($codigos as $codigo) {
                    $q->orWhere('transporte.codigo', $codigo);
                }
            });

            return;
        }

        $desdeNum = self::codigoRepartoANumero($desde);
        $hastaNum = self::codigoRepartoANumero($hasta);

        if ($desdeNum === null) {
            return;
        }

        if ($hasta === '' || $hastaNum === null || $hastaNum === self::REPARTO_HASTA_ABIERTO) {
            $query->where('transporte.codigo', $desde);

            return;
        }

        if ($desdeNum > $hastaNum) {
            [$desdeNum, $hastaNum] = [$hastaNum, $desdeNum];
        }

        $query->whereRaw(SqlDialectSupport::castEntero('transporte.codigo').' BETWEEN ? AND ?', [$desdeNum, $hastaNum]);
    }

    /**
     * Restringe la consulta al vendedor del usuario y sus asociados (El Bierzo).
     * Sin efecto si $vendedorIds está vacío (admin / sin permiso listar-clientes-vendedor).
     *
     * @param  EloquentBuilder|\Illuminate\Database\Query\Builder  $query
     * @param  list<int>  $vendedorIds
     */
    public static function aplicarFiltroVendedorUsuarioEnQuery($query, array $vendedorIds, string $columna = 'cliente.vendedor_id'): void
    {
        if ($vendedorIds === []) {
            return;
        }

        $query->whereIn($columna, $vendedorIds);
    }

    /**
     * @return list<string>
     */
    public static function parseListaRepartos(string $valor): array
    {
        $partes = preg_split('/[,;]+/', $valor) ?: [];

        return array_values(array_unique(array_filter(array_map(
            static fn ($c) => trim((string) $c),
            $partes,
        ), static fn ($c) => $c !== '')));
    }

    public static function codigoRepartoANumero(?string $codigo): ?int
    {
        $codigo = trim((string) $codigo);
        if ($codigo === '' || ! ctype_digit($codigo)) {
            return null;
        }

        return (int) $codigo;
    }

    public static function esRepartoHastaAbierto(string $hasta): bool
    {
        return self::codigoRepartoANumero($hasta) === self::REPARTO_HASTA_ABIERTO;
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function tieneCriteriosAplicados(array $filtros): bool
    {
        return trim((string) ($filtros['fecha_desde'] ?? '')) !== ''
            && trim((string) ($filtros['fecha_hasta'] ?? '')) !== '';
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    public static function paraQueryString(array $filtros): array
    {
        $hasta = (string) ($filtros['reparto_hasta'] ?? '');

        return array_filter([
            'fecha_desde' => (string) ($filtros['fecha_desde'] ?? ''),
            'fecha_hasta' => (string) ($filtros['fecha_hasta'] ?? ''),
            'reparto_desde' => (string) ($filtros['reparto_desde'] ?? ''),
            'reparto_hasta' => self::esRepartoHastaAbierto($hasta) ? '' : $hasta,
            'tipolistado' => (string) ($filtros['tipolistado'] ?? 'TOTAL'),
            'estado' => (string) ($filtros['estado'] ?? 'PENDIENTE'),
            'consultar' => 1,
        ], fn ($v) => $v !== null && $v !== '');
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function formatearRepartoTexto(array $filtros): string
    {
        $desde = trim((string) ($filtros['reparto_desde'] ?? ''));
        $hasta = trim((string) ($filtros['reparto_hasta'] ?? ''));

        if ($desde === '' && $hasta === '') {
            return 'Todos';
        }

        if (self::esListaRepartos($desde)) {
            return 'Repartos '.implode(', ', self::parseListaRepartos($desde));
        }

        if ($hasta !== '' && ! self::esRepartoHastaAbierto($hasta)) {
            return $desde.' al '.$hasta;
        }

        return 'Reparto '.$desde;
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function formatearPeriodoTexto(array $filtros): string
    {
        $desde = trim((string) ($filtros['fecha_desde'] ?? ''));
        $hasta = trim((string) ($filtros['fecha_hasta'] ?? ''));

        if ($desde === '' || $hasta === '') {
            return '';
        }

        return date('d/m/Y', strtotime($desde)).' – '.date('d/m/Y', strtotime($hasta));
    }
}
