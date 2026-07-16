<?php

declare(strict_types=1);

namespace App\Support\Contable;

use App\Support\Listado\FiltrosListadoRequest;
use App\Support\Ventas\GastronomiaCierresTurnoListadoFiltros;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Filtros del reporte Contable de cierres de turno gastronomía (solo lectura / conciliación).
 * Contaduría siempre ve todas las terminales.
 */
final class CierreTurnoGastronomiaContableListadoFiltros
{
    public const MODO_TODOS = GastronomiaCierresTurnoListadoFiltros::MODO_TODOS;

    public const MODO_CAMPO = GastronomiaCierresTurnoListadoFiltros::MODO_CAMPO;

    /** @var array<string, array{prop: string, type: string, label: string}> */
    public const CAMPOS = GastronomiaCierresTurnoListadoFiltros::CAMPOS;

    public static function resolverDesdeRequest(Request $request, ?string $busquedaRuta = null): array
    {
        if (FiltrosListadoRequest::solicitudLimpiaFiltros($request)) {
            return self::filtrosVacios();
        }

        $filtros = GastronomiaCierresTurnoListadoFiltros::resolverDesdeRequest($request, $busquedaRuta);
        $filtros['todas_terminales'] = true;
        $filtros['identificador_pc'] = '';

        if (trim((string) ($filtros['fecha_desde'] ?? '')) === ''
            && trim((string) ($filtros['fecha_hasta'] ?? '')) === '') {
            $filtros['fecha_desde'] = Carbon::today()->subDays(7)->toDateString();
            $filtros['fecha_hasta'] = Carbon::today()->toDateString();
        }

        return $filtros;
    }

    /**
     * @return array<string, mixed>
     */
    public static function filtrosVacios(): array
    {
        $filtros = GastronomiaCierresTurnoListadoFiltros::filtrosVacios();
        $filtros['todas_terminales'] = true;
        $filtros['identificador_pc'] = '';
        $filtros['fecha_desde'] = Carbon::today()->subDays(7)->toDateString();
        $filtros['fecha_hasta'] = Carbon::today()->toDateString();

        return $filtros;
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    public static function paraQueryString(array $filtros): array
    {
        $qs = GastronomiaCierresTurnoListadoFiltros::paraQueryString($filtros);
        unset($qs['identificador_pc'], $qs['todas_terminales']);

        return $qs;
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function tieneCriteriosAplicados(array $filtros): bool
    {
        return GastronomiaCierresTurnoListadoFiltros::tieneCriteriosAplicados($filtros);
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function tieneCriteriosUsuario(array $filtros): bool
    {
        return self::tieneCriteriosAplicados($filtros);
    }

    public static function operadoresParaCampo(string $campo): array
    {
        return GastronomiaCierresTurnoListadoFiltros::operadoresParaCampo($campo);
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function textoCabeceraExport(array $filtros): string
    {
        $partes = [];
        if ((int) ($filtros['empresa_id'] ?? 0) > 0) {
            $partes[] = 'Empresa ID '.$filtros['empresa_id'];
        }
        $desde = trim((string) ($filtros['fecha_desde'] ?? ''));
        $hasta = trim((string) ($filtros['fecha_hasta'] ?? ''));
        if ($desde !== '' || $hasta !== '') {
            $partes[] = 'Fechas: '
                .($desde !== '' ? Carbon::parse($desde)->format('d/m/Y') : '…')
                .' — '
                .($hasta !== '' ? Carbon::parse($hasta)->format('d/m/Y') : '…');
        }
        $tipo = trim((string) ($filtros['tipo'] ?? ''));
        if ($tipo !== '') {
            $partes[] = 'Tipo: '.$tipo;
        }
        $valor = trim((string) ($filtros['valor'] ?? ''));
        if ($valor !== '') {
            $partes[] = 'Texto: '.$valor;
        }

        return $partes === [] ? 'Sin filtros adicionales' : implode(' · ', $partes);
    }
}
