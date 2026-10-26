<?php

namespace App\Services\Ventas;

use App\Exports\Ventas\ContaduriaGastronomiaWorkbookExport;
use App\Models\Ventas\Cliente;
use App\Models\Ventas\DescuentoGastronomia;
use App\Support\Ventas\ContaduriaGastronomiaReporteCatalogo;
use App\Support\Ventas\GastronomiaDescuentoReporteFiltros;
use App\Support\Ventas\GastronomiaDescuentoReporteTipoArticuloSupport;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

final class ContaduriaGastronomiaReporteService
{
    public function __construct(
        private readonly GastronomiaDescuentoReporteService $reporteService,
    ) {}

    /**
     * Tres libros. Canje e invitaciones salen como estaban.
     * El consumo personal filtra la factura al descuento 10 y suma la vianda solo en el staff.
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
        $mandos = $this->clientesPorCodigos(ContaduriaGastronomiaReporteCatalogo::MANDOS_MEDIOS_CODIGOS);
        $directorio = $this->clientesPorCodigos(ContaduriaGastronomiaReporteCatalogo::DIRECTORIO_CODIGOS);
        $comedor = $this->clientesPorCodigos([ContaduriaGastronomiaReporteCatalogo::COMEDOR_CODIGO]);
        $invitaciones = $this->descuentosPorCodigos(ContaduriaGastronomiaReporteCatalogo::INVITACION_CODIGOS);

        $resultadoCanje = $this->generarClientes($filtrosBase, array_keys($canje));
        $filtrosConsumo = $filtrosBase;
        $filtrosConsumo['codigos_descuento_cliente_resueltos'] = [
            ContaduriaGastronomiaReporteCatalogo::CONSUMO_DESCUENTO_CODIGO,
        ];
        $resultadoConsumo = $this->generarClientes($filtrosConsumo, array_values(array_unique(array_merge(
            array_keys($staff),
            array_keys($mandos),
            array_keys($directorio),
        ))));
        $resultadoConsumo = $this->sumarViandasAlStaff($resultadoConsumo, $staff, $empresaId, $desde, $hasta);
        $resultadoComedor = $this->generarClientes($filtrosBase, array_keys($comedor));
        $resultadoInvitaciones = $this->generarCodigos(
            $filtrosBase,
            ContaduriaGastronomiaReporteCatalogo::INVITACION_CODIGOS,
        );

        $nombresCanje = $this->nombresPorCodigo($canje);
        $nombresStaff = $this->nombresPorCodigo($staff);
        $nombresMandos = $this->nombresPorCodigo($mandos);
        $nombresDirectorio = $this->nombresPorCodigo($directorio);
        $nombresComedor = $this->nombresPorCodigo($comedor);
        $codigosStaff = array_keys($nombresStaff);
        $codigosMandos = array_values(array_filter(
            ContaduriaGastronomiaReporteCatalogo::MANDOS_MEDIOS_CODIGOS,
            static fn (string $codigo) => isset($nombresMandos[$codigo]),
        ));
        $codigosDirectorio = array_values(array_filter(
            ContaduriaGastronomiaReporteCatalogo::DIRECTORIO_CODIGOS,
            static fn (string $codigo) => isset($nombresDirectorio[$codigo]),
        ));

        $canjeRecortado = $this->subtotalConCodigosEnCero(
            $this->recortar(
                $resultadoCanje,
                ContaduriaGastronomiaReporteCatalogo::CANJE_CODIGOS,
                $nombresCanje,
            ),
            ContaduriaGastronomiaReporteCatalogo::CANJE_CODIGOS,
            $nombresCanje,
        );
        $staffRecortado = $this->recortar($resultadoConsumo, $codigosStaff, $nombresStaff);
        $staffCaratula = $this->subtotalConCodigosEnCero($staffRecortado, $codigosStaff, $nombresStaff);
        $staffDetalle = $this->enColumnas($staffRecortado);
        $mandosRecortado = $this->recortar($resultadoConsumo, $codigosMandos, $nombresMandos);
        $mandosCaratula = $this->subtotalConCodigosEnCero($mandosRecortado, $codigosMandos, $nombresMandos);
        $mandosDetalle = $this->enColumnas($mandosRecortado);
        $directorioRecortado = $this->recortar($resultadoConsumo, $codigosDirectorio, $nombresDirectorio);
        $directorioCaratula = $this->subtotalConCodigosEnCero($directorioRecortado, $codigosDirectorio, $nombresDirectorio);
        $directorioDetalle = $this->enColumnas($directorioRecortado);
        $comedorRecortado = $this->recortar(
            $resultadoComedor,
            [ContaduriaGastronomiaReporteCatalogo::COMEDOR_CODIGO],
            $nombresComedor,
        );
        $invitacionesRecortadas = $this->subtotalConCodigosEnCero(
            $this->recortar(
                $resultadoInvitaciones,
                ContaduriaGastronomiaReporteCatalogo::INVITACION_CODIGOS,
                $invitaciones,
            ),
            ContaduriaGastronomiaReporteCatalogo::INVITACION_CODIGOS,
            $invitaciones,
        );
        $consumo = $this->caratulaConCentroCosto($this->unir([
            $staffCaratula,
            $mandosCaratula,
            $directorioCaratula,
            $comedorRecortado,
        ], $resultadoConsumo), array_merge($codigosMandos, $codigosDirectorio));

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
                            'resultado' => $staffDetalle,
                            'solo_totales' => false,
                            'en_columnas' => true,
                        ],
                        [
                            'titulo' => 'Mandos medios',
                            'resultado' => $mandosDetalle,
                            'solo_totales' => false,
                            'en_columnas' => true,
                        ],
                        [
                            'titulo' => 'Directorio',
                            'resultado' => $directorioDetalle,
                            'solo_totales' => false,
                            'en_columnas' => true,
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
     * Suma la vianda del centro a la factura del cliente STAFF de ese código.
     * No toca mandos, directorio ni comedor: la vianda de esas personas ya entra por el centro.
     *
     * @param  array<string, mixed>  $resultado
     * @param  array<int, array{codigo:string,nombre:string}>  $staff
     * @return array<string, mixed>
     */
    private function sumarViandasAlStaff(array $resultado, array $staff, int $empresaId, string $desde, string $hasta): array
    {
        $porCodigo = [];
        foreach ($staff as $cliente) {
            $codigo = trim((string) ($cliente['codigo'] ?? ''));
            if ($codigo !== '') {
                $porCodigo[$codigo] = $cliente;
            }
        }
        if ($porCodigo === []) {
            return $resultado;
        }

        $lineas = DB::table('vianda_consumo_linea as vcl')
            ->join('vianda_consumo as vc', 'vc.id', '=', 'vcl.vianda_consumo_id')
            ->join('centrocosto as cc', 'cc.id', '=', 'vc.centrocosto_id')
            ->leftJoin('articulo as a', 'a.id', '=', 'vcl.articulo_id')
            ->leftJoin('tipoarticulo as ta', 'ta.id', '=', 'a.tipoarticulo_id')
            ->where('vc.empresa_id', $empresaId)
            ->whereBetween('vc.fecha_jornada', [$desde, $hasta])
            ->whereNull('vc.anulado_at')
            ->whereIn('cc.codigo', array_keys($porCodigo))
            ->groupBy('cc.codigo', 'vcl.articulo_id', 'ta.id')
            ->selectRaw('cc.codigo as cc_codigo')
            ->selectRaw('vcl.articulo_id as articulo_id')
            ->selectRaw('ta.id as tipoarticulo_id')
            ->selectRaw('MAX(vcl.sku) as sku')
            ->selectRaw('MAX(vcl.descripcion) as descripcion')
            ->selectRaw('MAX(COALESCE(ta.nombre, vcl.tipoarticulo_nombre)) as tipoarticulo_nombre')
            ->selectRaw('SUM(vcl.cantidad) as unidades')
            ->selectRaw('SUM(vcl.cantidad * vcl.precio_costo_unitario) as costo_total')
            ->selectRaw('SUM(vcl.cantidad * vcl.precio_venta_unitario) as total_venta')
            ->get();

        if ($lineas->isEmpty()) {
            return $resultado;
        }

        $bloques = $resultado['bloques'] ?? [];
        $indice = [];
        foreach ($bloques as $i => $bloque) {
            $codigo = trim((string) ($bloque['codigo'] ?? ''));
            if (isset($porCodigo[$codigo]) && ! isset($indice[$codigo])) {
                $indice[$codigo] = $i;
            }
        }

        $porCentro = [];
        foreach ($lineas as $linea) {
            $codigo = trim((string) $linea->cc_codigo);
            if (! isset($porCodigo[$codigo])) {
                continue;
            }
            $porCentro[$codigo][] = $linea;
        }

        foreach ($porCentro as $codigo => $filasCentro) {
            if (! isset($indice[$codigo])) {
                $bloques[] = [
                    'clave' => 'c_vianda_'.$codigo,
                    'tipo_agrupacion' => GastronomiaDescuentoReporteFiltros::AGRUPAR_CLIENTE,
                    'codigo' => $codigo,
                    'nombre' => $porCodigo[$codigo]['nombre'] ?? '',
                    'filas' => [],
                    'grupos' => [],
                    'totales' => [
                        'unidades' => 0.0,
                        'costo_total' => 0.0,
                        'total_venta' => 0.0,
                    ],
                ];
                $indice[$codigo] = array_key_last($bloques);
            }

            $bloque = $bloques[$indice[$codigo]];
            $map = [];
            foreach ($bloque['filas'] ?? [] as $fila) {
                $articuloId = (int) ($fila['articulo_id'] ?? 0);
                if ($articuloId > 0) {
                    $map[$articuloId] = $fila;
                }
            }

            foreach ($filasCentro as $linea) {
                $unidades = round((float) $linea->unidades, 4);
                if (abs($unidades) < 0.0001) {
                    continue;
                }
                $articuloId = (int) $linea->articulo_id;
                if ($articuloId <= 0) {
                    continue;
                }
                if (! isset($map[$articuloId])) {
                    $tipoId = (int) ($linea->tipoarticulo_id ?? 0);
                    $map[$articuloId] = [
                        'articulo_id' => $articuloId,
                        'sku' => (string) ($linea->sku ?? ''),
                        'descripcion' => (string) ($linea->descripcion ?? ''),
                        'tipoarticulo_id' => $tipoId > 0 ? $tipoId : null,
                        'tipoarticulo_nombre' => trim((string) ($linea->tipoarticulo_nombre ?? '')),
                        'unidades' => 0.0,
                        'costo_unitario' => 0.0,
                        'costo_total' => 0.0,
                        'precio_venta' => 0.0,
                        'total_venta' => 0.0,
                    ];
                }
                $map[$articuloId]['unidades'] = round((float) $map[$articuloId]['unidades'] + $unidades, 4);
                $map[$articuloId]['costo_total'] = round((float) $map[$articuloId]['costo_total'] + (float) $linea->costo_total, 2);
                $map[$articuloId]['total_venta'] = round((float) $map[$articuloId]['total_venta'] + (float) $linea->total_venta, 2);
                $unidadesFila = (float) $map[$articuloId]['unidades'];
                if ($unidadesFila > 0) {
                    $map[$articuloId]['costo_unitario'] = round((float) $map[$articuloId]['costo_total'] / $unidadesFila, 2);
                    $map[$articuloId]['precio_venta'] = round((float) $map[$articuloId]['total_venta'] / $unidadesFila, 2);
                }
            }

            $bloque['filas'] = array_values($map);
            $bloques[$indice[$codigo]] = $this->recalcularBloque($bloque);
        }

        $resultado['bloques'] = array_values($bloques);

        return $resultado;
    }

    /**
     * @param  array<string, mixed>  $bloque
     * @return array<string, mixed>
     */
    private function recalcularBloque(array $bloque): array
    {
        $agrupado = GastronomiaDescuentoReporteTipoArticuloSupport::agruparFilas($bloque['filas'] ?? []);
        $unidades = 0.0;
        $costo = 0.0;
        $venta = 0.0;
        foreach ($agrupado['filas'] as $fila) {
            $unidades += (float) ($fila['unidades'] ?? 0);
            $costo += (float) ($fila['costo_total'] ?? 0);
            $venta += (float) ($fila['total_venta'] ?? 0);
        }
        $bloque['filas'] = $agrupado['filas'];
        $bloque['grupos'] = $agrupado['grupos'];
        $bloque['totales'] = [
            'unidades' => round($unidades, 4),
            'costo_total' => round($costo, 2),
            'total_venta' => round($venta, 2),
        ];

        return $bloque;
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
                continue;
            }

            foreach ($items as $bloque) {
                $bloques[] = $bloque;
                $costo = round((float) ($bloque['totales']['costo_total'] ?? 0), 2);
                $venta = round((float) ($bloque['totales']['total_venta'] ?? 0), 2);
                $unidades = round((float) ($bloque['totales']['unidades'] ?? 0), 4);
                $granCosto += $costo;
                $granVenta += $venta;
                $granUnidades += $unidades;
                if (abs($costo) < 0.005) {
                    continue;
                }
                $totales[] = [
                    'clave' => $bloque['clave'] ?? '',
                    'tipo_agrupacion' => $bloque['tipo_agrupacion'] ?? '',
                    'codigo' => $bloque['codigo'] ?? $codigo,
                    'sector' => $bloque['nombre'] ?? ($nombres[$codigo] ?? ''),
                    'costo_total' => $costo,
                ];
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
     * El subtotal lista todos los códigos del catálogo.
     * Sin consumo el costo queda en blanco. Las solapas salen solo de los bloques con líneas.
     *
     * @param  array<string, mixed>  $resultado
     * @param  list<string>  $codigos
     * @param  array<string, string>  $nombres
     * @return array<string, mixed>
     */
    private function subtotalConCodigosEnCero(array $resultado, array $codigos, array $nombres): array
    {
        $porCodigo = [];
        foreach ($resultado['totales'] ?? [] as $fila) {
            $codigo = trim((string) ($fila['codigo'] ?? ''));
            if ($codigo !== '') {
                $porCodigo[$codigo] = $fila;
            }
        }

        $totales = [];
        foreach ($codigos as $codigo) {
            if (isset($porCodigo[$codigo])) {
                $totales[] = $porCodigo[$codigo];

                continue;
            }

            $totales[] = [
                'clave' => '',
                'tipo_agrupacion' => '',
                'codigo' => $codigo,
                'sector' => $nombres[$codigo] ?? '',
                'costo_total' => 0,
            ];
        }

        $resultado['totales'] = $totales;

        return $resultado;
    }

    /**
     * Carátula de consumo personal: código, centro de costo y costo.
     * El staff muestra su propio código como centro de costo.
     * Mandos y directorio conservan su código y el centro de costo del legajo, en rojo.
     * La vianda de esas personas no se suma de nuevo: ya está en el centro.
     *
     * @param  array<string, mixed>  $resultado
     * @param  list<string>  $codigosPersona
     * @return array<string, mixed>
     */
    private function caratulaConCentroCosto(array $resultado, array $codigosPersona): array
    {
        $personasCodigo = array_fill_keys($codigosPersona, true);
        $nombres = [];
        foreach ($resultado['bloques'] ?? [] as $bloque) {
            $codigo = trim((string) ($bloque['codigo'] ?? ''));
            if ($codigo !== '') {
                $nombres[$codigo] = trim((string) ($bloque['nombre'] ?? ''));
            }
        }

        $personas = $this->personasConCentroCosto();
        $totales = [];
        foreach ($resultado['totales'] ?? [] as $fila) {
            $codigo = trim((string) ($fila['codigo'] ?? ''));
            $nombre = trim((string) ($nombres[$codigo] ?? ($fila['sector'] ?? '')));
            $esStaff = ctype_digit($codigo)
                && (int) $codigo >= ContaduriaGastronomiaReporteCatalogo::STAFF_CODIGO_DESDE
                && (int) $codigo <= ContaduriaGastronomiaReporteCatalogo::STAFF_CODIGO_HASTA;

            if ($esStaff) {
                $fila['codigo_visible'] = '';
                $fila['centro_costo'] = $codigo;
                $fila['centro_costo_rojo'] = false;
            } elseif (isset($personasCodigo[$codigo])) {
                $centro = $this->resolverCentroCosto($nombre, $personas['empleados'], $personas['usuarios']);
                $fila['codigo_visible'] = $codigo;
                $fila['centro_costo'] = $centro;
                $fila['centro_costo_rojo'] = $centro !== '';
            } else {
                $fila['codigo_visible'] = $codigo;
                $fila['centro_costo'] = '';
                $fila['centro_costo_rojo'] = false;
            }

            $totales[] = $fila;
        }

        $resultado['totales'] = $totales;
        $resultado['caratula_cc'] = true;

        return $resultado;
    }

    /**
     * @return array{empleados:list<array{nombre:string,tokens:list<string>,estado:string,cc:string}>,usuarios:list<array{tokens:list<string>,cc:string}>}
     */
    private function personasConCentroCosto(): array
    {
        $empleados = [];
        $rows = DB::table('empleado_sueldos as e')
            ->leftJoin('centrocosto as cc', 'cc.id', '=', 'e.centrocosto_id')
            ->whereNotNull('e.centrocosto_id')
            ->get(['e.nombre', 'e.estado', 'cc.codigo']);
        foreach ($rows as $row) {
            $nombre = $this->normalizarNombre((string) ($row->nombre ?? ''));
            $cc = trim((string) ($row->codigo ?? ''));
            if ($nombre === '' || $cc === '') {
                continue;
            }
            $empleados[] = [
                'nombre' => $nombre,
                'tokens' => $this->tokensNombre($nombre),
                'estado' => strtoupper(trim((string) ($row->estado ?? ''))),
                'cc' => $cc,
            ];
        }

        $usuarios = [];
        $rows = DB::table('usuario as u')
            ->join('centrocosto as cc', 'cc.id', '=', 'u.centrocosto_id')
            ->whereNotNull('u.centrocosto_id')
            ->get(['u.nombre', 'cc.codigo']);
        foreach ($rows as $row) {
            $nombre = $this->normalizarNombre((string) ($row->nombre ?? ''));
            $cc = trim((string) ($row->codigo ?? ''));
            $tokens = $this->tokensNombre($nombre);
            if ($tokens === [] || $cc === '') {
                continue;
            }
            $usuarios[] = [
                'tokens' => $tokens,
                'cc' => $cc,
            ];
        }

        return ['empleados' => $empleados, 'usuarios' => $usuarios];
    }

    /**
     * @param  list<array{nombre:string,tokens:list<string>,estado:string,cc:string}>  $empleados
     * @param  list<array{tokens:list<string>,cc:string}>  $usuarios
     */
    private function resolverCentroCosto(string $nombre, array $empleados, array $usuarios): string
    {
        $normalizado = $this->normalizarNombre($nombre);
        $tokens = $this->tokensNombre($normalizado);
        if ($tokens === []) {
            return '';
        }

        $exactos = array_values(array_filter(
            $empleados,
            static fn (array $empleado) => $empleado['nombre'] === $normalizado,
        ));
        $candidatos = $exactos !== []
            ? $exactos
            : array_values(array_filter($empleados, function (array $empleado) use ($tokens) {
                if (($empleado['tokens'][0] ?? '') !== ($tokens[0] ?? '')) {
                    return false;
                }
                $comunes = array_intersect($tokens, $empleado['tokens']);
                $empleadoEnPersona = array_diff($empleado['tokens'], $tokens) === [];
                $personaEnEmpleado = array_diff($tokens, $empleado['tokens']) === [];

                return count($comunes) >= 2 && ($empleadoEnPersona || $personaEnEmpleado);
            }));
        if ($candidatos === []) {
            return $this->centroCostoDesdeUsuarios($tokens, $usuarios, []);
        }

        $activos = array_values(array_filter(
            $candidatos,
            static fn (array $empleado) => $empleado['estado'] === 'A',
        ));
        $pool = $activos !== [] ? $activos : $candidatos;
        $centros = array_values(array_unique(array_filter(array_map(
            static fn (array $empleado) => $empleado['cc'],
            $pool,
        ))));
        if (count($centros) === 1) {
            return $centros[0];
        }
        if ($centros === []) {
            return '';
        }

        return $this->centroCostoDesdeUsuarios($tokens, $usuarios, $centros);
    }

    /**
     * @param  list<string>  $tokens
     * @param  list<array{tokens:list<string>,cc:string}>  $usuarios
     * @param  list<string>  $centrosPermitidos
     */
    private function centroCostoDesdeUsuarios(array $tokens, array $usuarios, array $centrosPermitidos): string
    {
        $mejor = '';
        $mejorPuntaje = 1;
        $empate = false;
        foreach ($usuarios as $usuario) {
            if ($centrosPermitidos !== [] && ! in_array($usuario['cc'], $centrosPermitidos, true)) {
                continue;
            }
            if (array_diff($usuario['tokens'], $tokens) !== []) {
                continue;
            }
            $puntaje = count(array_intersect($tokens, $usuario['tokens']));
            if ($puntaje < 2) {
                continue;
            }
            if ($puntaje > $mejorPuntaje) {
                $mejorPuntaje = $puntaje;
                $mejor = $usuario['cc'];
                $empate = false;
            } elseif ($puntaje === $mejorPuntaje && $usuario['cc'] !== $mejor) {
                $empate = true;
            }
        }

        return $empate ? '' : $mejor;
    }

    private function normalizarNombre(string $nombre): string
    {
        $nombre = mb_strtoupper(trim($nombre));
        $nombre = str_replace(['*', '.'], ' ', $nombre);
        $nombre = preg_replace('/\s+/u', ' ', $nombre) ?? $nombre;

        return trim($nombre);
    }

    /**
     * @return list<string>
     */
    private function tokensNombre(string $normalizado): array
    {
        if ($normalizado === '') {
            return [];
        }

        return array_values(array_filter(
            explode(' ', $normalizado),
            static fn (string $token) => $token !== '',
        ));
    }

    /**
     * Arma la grilla horizontal: una terna de columnas (unidades, costo, venta) por cliente con consumo.
     *
     * @param  array<string, mixed>  $resultado
     * @return array<string, mixed>
     */
    private function enColumnas(array $resultado): array
    {
        return $this->reporteService->enriquecerVistaColumnas(
            ['presentacion_columnas' => true],
            $resultado,
        );
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
