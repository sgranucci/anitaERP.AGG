<?php

namespace App\Support\Ventas;

use Carbon\Carbon;
use Illuminate\Http\Request;

class GastronomiaDescuentoReporteFiltros
{
    public const AGRUPAR_CODIGO = 'codigo_descuento';

    public const AGRUPAR_CLIENTE = 'cliente_descuento';

    /**
     * @return array<string, mixed>
     */
    public static function resolverDesdeRequest(Request $request): array
    {
        $empresaId = (int) $request->input('empresa_id', 0);

        [$desde, $hasta] = self::normalizarRangoFechas(
            trim((string) $request->input('fecha_desde', '')),
            trim((string) $request->input('fecha_hasta', '')),
        );

        $codigosRaw = trim((string) $request->input('codigos_descuento', ''));
        $codigos = GastronomiaDescuentoReporteCodigoSupport::expandir($codigosRaw);
        $codigosClienteRaw = trim((string) $request->input('codigos_descuento_cliente', ''));
        $codigosCliente = GastronomiaDescuentoReporteCodigoSupport::expandir($codigosClienteRaw);
        $clientesRaw = trim((string) $request->input('clientes_descuento_ids', ''));
        $clienteIds = self::parsearIdsCsv($clientesRaw);

        $agruparPor = trim((string) $request->input('agrupar_por', self::AGRUPAR_CODIGO));
        if (! in_array($agruparPor, [self::AGRUPAR_CODIGO, self::AGRUPAR_CLIENTE], true)) {
            $agruparPor = self::AGRUPAR_CODIGO;
        }

        $listarTodos = $request->boolean('listar_todos');
        $presentacionColumnas = $request->boolean('presentacion_columnas');

        return [
            'empresa_id' => $empresaId,
            'fecha_desde' => $desde,
            'fecha_hasta' => $hasta,
            'codigos_descuento' => $codigosRaw,
            'codigos_descuento_resueltos' => $codigos,
            'codigos_descuento_cliente' => $codigosClienteRaw,
            'codigos_descuento_cliente_resueltos' => $codigosCliente,
            'clientes_descuento_ids' => $clienteIds,
            'clientes_descuento_ids_raw' => $clientesRaw,
            'listar_todos' => $listarTodos,
            'agrupar_por' => $agruparPor,
            'presentacion_columnas' => $presentacionColumnas,
            'excel_solapas' => ! $listarTodos && ! $presentacionColumnas && $request->boolean('excel_solapas'),
        ];
    }

    /**
     * @return list<int>
     */
    public static function parsearIdsCsv(string $raw): array
    {
        if (trim($raw) === '') {
            return [];
        }

        $ids = [];
        foreach (preg_split('/[\s,;]+/', $raw) ?: [] as $part) {
            $id = (int) trim((string) $part);
            if ($id > 0 && ! in_array($id, $ids, true)) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    public static function tieneCriteriosAplicados(array $filtros): bool
    {
        if ((int) ($filtros['empresa_id'] ?? 0) <= 0) {
            return false;
        }

        if (trim((string) ($filtros['fecha_desde'] ?? '')) === ''
            || trim((string) ($filtros['fecha_hasta'] ?? '')) === '') {
            return false;
        }

        return self::tieneSeleccionAlcance($filtros);
    }

    public static function tieneSeleccionAlcance(array $filtros): bool
    {
        if (! empty($filtros['listar_todos'])) {
            return true;
        }

        $agruparPor = (string) ($filtros['agrupar_por'] ?? self::AGRUPAR_CODIGO);

        if ($agruparPor === self::AGRUPAR_CODIGO) {
            $codigos = $filtros['codigos_descuento_resueltos'] ?? [];

            return is_array($codigos) && $codigos !== [];
        }

        $clienteIds = $filtros['clientes_descuento_ids'] ?? [];

        return is_array($clienteIds) && $clienteIds !== [];
    }

    /**
     * @return array{0: string, 1: string}
     */
    public static function normalizarRangoFechas(string $fechaDesde, string $fechaHasta): array
    {
        $desde = trim($fechaDesde);
        $hasta = trim($fechaHasta);

        if ($desde !== '' && $hasta === '') {
            $hasta = $desde;
        } elseif ($hasta !== '' && $desde === '') {
            $desde = $hasta;
        }

        if ($desde !== '' && $hasta !== '' && $desde > $hasta) {
            [$desde, $hasta] = [$hasta, $desde];
        }

        return [$desde, $hasta];
    }

    /**
     * @return array<string, string|int>
     */
    public static function paraQueryString(array $filtros): array
    {
        $params = [
            'consultar' => 1,
            'fecha_desde' => (string) ($filtros['fecha_desde'] ?? ''),
            'fecha_hasta' => (string) ($filtros['fecha_hasta'] ?? ''),
            'codigos_descuento' => (string) ($filtros['codigos_descuento'] ?? ''),
            'clientes_descuento_ids' => (string) ($filtros['clientes_descuento_ids_raw'] ?? ''),
            'agrupar_por' => (string) ($filtros['agrupar_por'] ?? self::AGRUPAR_CODIGO),
        ];

        if ((int) ($filtros['empresa_id'] ?? 0) > 0) {
            $params['empresa_id'] = (int) $filtros['empresa_id'];
        }

        if (! empty($filtros['listar_todos'])) {
            $params['listar_todos'] = 1;
        }

        if (! empty($filtros['presentacion_columnas'])) {
            $params['presentacion_columnas'] = 1;
        }

        if (! empty($filtros['excel_solapas']) && empty($filtros['listar_todos'])) {
            $params['excel_solapas'] = 1;
        }

        if (trim((string) ($filtros['codigos_descuento_cliente'] ?? '')) !== '') {
            $params['codigos_descuento_cliente'] = (string) $filtros['codigos_descuento_cliente'];
        }

        return $params;
    }

    public static function formatearPeriodoTexto(array $filtros): string
    {
        $desde = trim((string) ($filtros['fecha_desde'] ?? ''));
        $hasta = trim((string) ($filtros['fecha_hasta'] ?? ''));

        if ($desde === '' || $hasta === '') {
            return '';
        }

        $fmtDesde = Carbon::parse($desde)->format('d/m/y');
        $fmtHasta = Carbon::parse($hasta)->format('d/m/y');

        return $desde === $hasta ? $fmtDesde : 'Desde '.$fmtDesde.' hasta '.$fmtHasta;
    }

    public static function formatearPeriodoTextoLargo(array $filtros): string
    {
        $desde = trim((string) ($filtros['fecha_desde'] ?? ''));
        $hasta = trim((string) ($filtros['fecha_hasta'] ?? ''));

        if ($desde === '' || $hasta === '') {
            return '';
        }

        $fmtDesde = Carbon::parse($desde)->format('d/m/Y');
        $fmtHasta = Carbon::parse($hasta)->format('d/m/Y');

        return $desde === $hasta ? $fmtDesde : $fmtDesde.' — '.$fmtHasta;
    }

    public static function etiquetaMes(array $filtros): string
    {
        $hasta = trim((string) ($filtros['fecha_hasta'] ?? ''));
        if ($hasta === '') {
            return '';
        }

        $meses = [
            1 => 'ENERO', 2 => 'FEBRERO', 3 => 'MARZO', 4 => 'ABRIL',
            5 => 'MAYO', 6 => 'JUNIO', 7 => 'JULIO', 8 => 'AGOSTO',
            9 => 'SEPTIEMBRE', 10 => 'OCTUBRE', 11 => 'NOVIEMBRE', 12 => 'DICIEMBRE',
        ];

        $fecha = Carbon::parse($hasta);

        return strtoupper(($meses[(int) $fecha->format('n')] ?? $fecha->format('F')).' '.$fecha->format('Y'));
    }

    public static function etiquetaAgrupacion(array $filtros): string
    {
        return ($filtros['agrupar_por'] ?? self::AGRUPAR_CODIGO) === self::AGRUPAR_CLIENTE
            ? 'Cliente interno descuento'
            : 'Código descuento';
    }

    /**
     * @param  array<string, mixed>|null  $resultado
     */
    public static function debeUsarVistaColumnas(array $filtros, ?array $resultado): bool
    {
        if (empty($filtros['presentacion_columnas']) || $resultado === null) {
            return false;
        }

        $vista = $resultado['vista_columnas'] ?? null;
        if (! is_array($vista)) {
            return false;
        }

        $columnas = $vista['columnas'] ?? [];

        return is_array($columnas) && $columnas !== [];
    }
}
