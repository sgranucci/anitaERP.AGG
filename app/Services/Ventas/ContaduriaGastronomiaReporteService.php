<?php

namespace App\Services\Ventas;

use App\Exports\Ventas\ContaduriaGastronomiaWorkbookExport;
use App\Models\Ventas\Cliente;
use App\Models\Ventas\DescuentoGastronomia;
use App\Support\Ventas\ContaduriaGastronomiaReporteCatalogo;
use App\Support\Ventas\GastronomiaDescuentoReporteFiltros;
use Carbon\Carbon;

final class ContaduriaGastronomiaReporteService
{
    public function __construct(
        private readonly GastronomiaDescuentoReporteService $reporteService,
    ) {}

    /**
     * Tres libros con las solapas pedidas. Una sola lectura de clientes y otra de invitaciones.
     *
     * @return list<array{nombre:string,export:ContaduriaGastronomiaWorkbookExport}>
     */
    public function archivos(int $empresaId, string $mes, string $empresaNombre): array
    {
        [$desde, $hasta] = $this->rangoMes($mes);
        $periodo = Carbon::parse($desde)->translatedFormat('F Y');
        $filtrosBase = [
            'empresa_id' => $empresaId,
            'fecha_desde' => $desde,
            'fecha_hasta' => $hasta,
            'listar_todos' => false,
            'presentacion_columnas' => false,
            'codigos_descuento_resueltos' => [],
            'codigos_descuento_cliente_resueltos' => [],
            'mozos_descuento_ids' => [],
            'vips_descuento_ids' => [],
        ];

        $canje = $this->clientesPorCodigos(ContaduriaGastronomiaReporteCatalogo::CANJE_CODIGOS);
        $staff = $this->staffPorCentroCosto();
        $managers = $this->clientesPorCodigos(ContaduriaGastronomiaReporteCatalogo::MANAGER_CODIGOS);
        $comedor = $this->clientesPorCodigos([ContaduriaGastronomiaReporteCatalogo::COMEDOR_CODIGO]);
        $invitaciones = $this->descuentosPorCodigos(ContaduriaGastronomiaReporteCatalogo::INVITACION_CODIGOS);

        $idsClientes = array_values(array_unique(array_merge(
            array_keys($canje),
            array_keys($staff),
            array_keys($managers),
            array_keys($comedor),
        )));

        $resultadoClientes = $this->generarClientes($filtrosBase, $idsClientes);
        $resultadoInvitaciones = $this->generarCodigos(
            $filtrosBase,
            ContaduriaGastronomiaReporteCatalogo::INVITACION_CODIGOS,
        );

        $nombresCanje = $this->nombresPorCodigo($canje);
        $nombresStaff = $this->nombresPorCodigo($staff);
        $nombresManagers = $this->nombresPorCodigo($managers);
        $nombresComedor = $this->nombresPorCodigo($comedor);
        $codigosStaff = array_keys($nombresStaff);
        $codigosManagers = array_values(array_filter(
            ContaduriaGastronomiaReporteCatalogo::MANAGER_CODIGOS,
            static fn (string $codigo) => isset($nombresManagers[$codigo]),
        ));

        $canjeRecortado = $this->recortar(
            $resultadoClientes,
            ContaduriaGastronomiaReporteCatalogo::CANJE_CODIGOS,
            $nombresCanje,
        );
        $staffRecortado = $this->recortar($resultadoClientes, $codigosStaff, $nombresStaff);
        $managersRecortado = $this->recortar($resultadoClientes, $codigosManagers, $nombresManagers);
        $comedorRecortado = $this->recortar(
            $resultadoClientes,
            [ContaduriaGastronomiaReporteCatalogo::COMEDOR_CODIGO],
            $nombresComedor,
        );
        $invitacionesRecortadas = $this->recortar(
            $resultadoInvitaciones,
            ContaduriaGastronomiaReporteCatalogo::INVITACION_CODIGOS,
            $invitaciones,
        );
        $consumo = $this->unir([
            $staffRecortado,
            $managersRecortado,
            $comedorRecortado,
        ], $resultadoClientes);

        $sufijo = Carbon::parse($desde)->format('Y-m');
        $subtitulo = $periodo;

        return [
            [
                'nombre' => 'canje_gastronomico_'.$sufijo.'.xlsx',
                'export' => new ContaduriaGastronomiaWorkbookExport(
                    $this->reporteService,
                    $filtrosBase,
                    'Canje gastronómico',
                    $subtitulo,
                    $empresaNombre,
                    [
                        [
                            'titulo' => 'Subtotal',
                            'resultado' => $canjeRecortado,
                            'solo_totales' => true,
                        ],
                    ],
                    $canjeRecortado,
                ),
            ],
            [
                'nombre' => 'invitaciones_no_pf_'.$sufijo.'.xlsx',
                'export' => new ContaduriaGastronomiaWorkbookExport(
                    $this->reporteService,
                    $filtrosBase,
                    'Invitaciones no PF',
                    $subtitulo,
                    $empresaNombre,
                    [
                        [
                            'titulo' => 'Subtotal',
                            'resultado' => $invitacionesRecortadas,
                            'solo_totales' => true,
                        ],
                    ],
                    $invitacionesRecortadas,
                ),
            ],
            [
                'nombre' => 'consumo_personal_'.$sufijo.'.xlsx',
                'export' => new ContaduriaGastronomiaWorkbookExport(
                    $this->reporteService,
                    $filtrosBase,
                    'Consumo personal',
                    $subtitulo,
                    $empresaNombre,
                    [
                        [
                            'titulo' => 'Total por codigo',
                            'resultado' => $consumo,
                            'solo_totales' => true,
                        ],
                        [
                            'titulo' => 'Staff 80-105',
                            'resultado' => $staffRecortado,
                            'solo_totales' => false,
                        ],
                        [
                            'titulo' => 'Managers',
                            'resultado' => $managersRecortado,
                            'solo_totales' => false,
                        ],
                        [
                            'titulo' => 'Comedor personal',
                            'resultado' => $comedorRecortado,
                            'solo_totales' => false,
                        ],
                    ],
                    null,
                ),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $filtrosBase
     * @param  list<int>  $clienteIds
     * @return array<string, mixed>
     */
    private function generarClientes(array $filtrosBase, array $clienteIds): array
    {
        $filtros = $filtrosBase;
        $filtros['agrupar_por'] = GastronomiaDescuentoReporteFiltros::AGRUPAR_CLIENTE;
        $filtros['clientes_descuento_ids'] = $clienteIds;

        return $this->reporteService->generar($filtros);
    }

    /**
     * @param  array<string, mixed>  $filtrosBase
     * @param  list<string>  $codigos
     * @return array<string, mixed>
     */
    private function generarCodigos(array $filtrosBase, array $codigos): array
    {
        $filtros = $filtrosBase;
        $filtros['agrupar_por'] = GastronomiaDescuentoReporteFiltros::AGRUPAR_CODIGO;
        $filtros['codigos_descuento_resueltos'] = $codigos;
        $filtros['clientes_descuento_ids'] = [];

        return $this->reporteService->generar($filtros);
    }

    /**
     * @param  array<string, mixed>  $origen
     * @param  list<string>  $codigos
     * @param  array<string, string>  $nombres
     * @return array<string, mixed>
     */
    private function recortar(array $origen, array $codigos, array $nombres): array
    {
        $porCodigo = [];
        foreach ($origen['bloques'] ?? [] as $bloque) {
            $codigo = trim((string) ($bloque['codigo'] ?? ''));
            $porCodigo[$codigo][] = $bloque;
        }

        $bloques = [];
        $totales = [];
        $granCosto = 0.0;
        $granVenta = 0.0;
        $granUnidades = 0.0;

        foreach ($codigos as $codigo) {
            $items = $porCodigo[$codigo] ?? [];
            if ($items === []) {
                if (! isset($nombres[$codigo])) {
                    continue;
                }
                $totales[] = [
                    'codigo' => $codigo,
                    'sector' => $nombres[$codigo],
                    'costo_total' => 0.0,
                ];

                continue;
            }

            foreach ($items as $bloque) {
                $bloques[] = $bloque;
                $costo = round((float) ($bloque['totales']['costo_total'] ?? 0), 2);
                $venta = round((float) ($bloque['totales']['total_venta'] ?? 0), 2);
                $unidades = round((float) ($bloque['totales']['unidades'] ?? 0), 4);
                $totales[] = [
                    'clave' => $bloque['clave'] ?? '',
                    'tipo_agrupacion' => $bloque['tipo_agrupacion'] ?? '',
                    'codigo' => $bloque['codigo'] ?? $codigo,
                    'sector' => $bloque['nombre'] ?? ($nombres[$codigo] ?? ''),
                    'costo_total' => $costo,
                ];
                $granCosto += $costo;
                $granVenta += $venta;
                $granUnidades += $unidades;
            }
        }

        $resultado = $origen;
        $resultado['bloques'] = $bloques;
        $resultado['totales'] = $totales;
        $resultado['gran_total_costo'] = round($granCosto, 2);
        $resultado['gran_total_venta'] = round($granVenta, 2);
        $resultado['gran_total_unidades'] = round($granUnidades, 4);
        $resultado['vista_columnas'] = null;

        return $resultado;
    }

    /**
     * @param  list<array<string, mixed>>  $partes
     * @param  array<string, mixed>  $plantilla
     * @return array<string, mixed>
     */
    private function unir(array $partes, array $plantilla): array
    {
        $bloques = [];
        $totales = [];
        $granCosto = 0.0;
        $granVenta = 0.0;
        $granUnidades = 0.0;

        foreach ($partes as $parte) {
            foreach ($parte['bloques'] ?? [] as $bloque) {
                $bloques[] = $bloque;
            }
            foreach ($parte['totales'] ?? [] as $total) {
                $totales[] = $total;
                $granCosto += (float) ($total['costo_total'] ?? 0);
            }
            $granVenta += (float) ($parte['gran_total_venta'] ?? 0);
            $granUnidades += (float) ($parte['gran_total_unidades'] ?? 0);
        }

        $plantilla['bloques'] = $bloques;
        $plantilla['totales'] = $totales;
        $plantilla['gran_total_costo'] = round($granCosto, 2);
        $plantilla['gran_total_venta'] = round($granVenta, 2);
        $plantilla['gran_total_unidades'] = round($granUnidades, 4);
        $plantilla['vista_columnas'] = null;

        return $plantilla;
    }

    /**
     * @return array{0:string,1:string}
     */
    private function rangoMes(string $mes): array
    {
        $inicio = Carbon::createFromFormat('Y-m-d', $mes.'-01')->startOfMonth();

        return [$inicio->toDateString(), $inicio->copy()->endOfMonth()->toDateString()];
    }

    /**
     * @param  list<string>  $codigos
     * @return array<int, array{codigo:string,nombre:string}>
     */
    private function clientesPorCodigos(array $codigos): array
    {
        $codigos = ContaduriaGastronomiaReporteCatalogo::normalizarCodigos($codigos);
        if ($codigos === []) {
            return [];
        }

        $out = [];
        $rows = Cliente::query()
            ->whereIn('codigo', $codigos)
            ->get(['id', 'codigo', 'nombre']);

        foreach ($rows as $row) {
            $out[(int) $row->id] = [
                'codigo' => trim((string) $row->codigo),
                'nombre' => trim((string) $row->nombre),
            ];
        }

        return $out;
    }

    /**
     * Staff por centro de costo: clientes STAFF con código 80 a 105.
     *
     * @return array<int, array{codigo:string,nombre:string}>
     */
    private function staffPorCentroCosto(): array
    {
        $out = [];
        $rows = Cliente::query()
            ->where('nombre', 'like', 'STAFF%')
            ->get(['id', 'codigo', 'nombre']);

        foreach ($rows as $row) {
            $codigo = trim((string) $row->codigo);
            if ($codigo === '' || ! ctype_digit($codigo)) {
                continue;
            }
            $numero = (int) $codigo;
            if ($numero < ContaduriaGastronomiaReporteCatalogo::STAFF_CODIGO_DESDE
                || $numero > ContaduriaGastronomiaReporteCatalogo::STAFF_CODIGO_HASTA) {
                continue;
            }
            $out[(int) $row->id] = [
                'codigo' => $codigo,
                'nombre' => trim((string) $row->nombre),
            ];
        }

        uasort($out, static fn (array $a, array $b) => ((int) $a['codigo']) <=> ((int) $b['codigo']));

        return $out;
    }

    /**
     * @param  list<string>  $codigos
     * @return array<string, string>
     */
    private function descuentosPorCodigos(array $codigos): array
    {
        $out = [];
        $rows = DescuentoGastronomia::query()
            ->whereIn('codigo', $codigos)
            ->get(['codigo', 'nombre']);

        foreach ($rows as $row) {
            $out[trim((string) $row->codigo)] = trim((string) $row->nombre);
        }

        return $out;
    }

    /**
     * @param  array<int, array{codigo:string,nombre:string}>  $clientes
     * @return array<string, string>
     */
    private function nombresPorCodigo(array $clientes): array
    {
        $out = [];
        foreach ($clientes as $cliente) {
            $out[$cliente['codigo']] = $cliente['nombre'];
        }

        return $out;
    }
}
