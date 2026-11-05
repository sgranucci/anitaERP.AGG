<?php

declare(strict_types=1);

namespace App\Http\Controllers\Compras;

use App\Exports\Compras\ProyeccionPagosProgramacionExport;
use App\Http\Controllers\Controller;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Support\Compras\ProyeccionPagosProgramacionSupport;
use App\Support\Configuracion\EmpresaFiltroExternoSupport;
use App\Support\Reportes\DompdfListadoSupport;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Excel;

class ProyeccionPagosProgramacionController extends Controller
{
    public function __construct(
        private readonly ProyeccionPagosProgramacionSupport $support,
        private readonly EmpresaRepositoryInterface $empresaRepository,
    ) {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        can('programar-pagos-proveedor');

        $filtros = $this->filtros($request);
        $listado = $this->support->listar($filtros, $this->empresaIds());
        $total = 0.0;
        foreach ($listado['filas'] as $fila) {
            $total += (float) $fila['total_adeudado'];
        }

        return view('compras.proyeccion_pagos_programacion.index', [
            'filas' => $listado['filas'],
            'truncado' => $listado['truncado'],
            'total' => $total,
            'filtros' => $filtros,
            'filtrosQuery' => $this->paraQuery($filtros),
            'empresa_query' => $this->empresaRepository->allFiltrado(),
            'schema_ok' => ProyeccionPagosProgramacionSupport::columnasDisponibles(),
            'hoy' => now()->toDateString(),
        ]);
    }

    public function grabar(Request $request)
    {
        can('programar-pagos-proveedor');

        $fechas = $request->input('fechas', []);
        $urgentes = $request->input('urgentes', []);
        if (! is_array($fechas)) {
            $fechas = [];
        }
        if (! is_array($urgentes)) {
            $urgentes = [];
        }

        $resultado = $this->support->grabar($fechas, $urgentes, $this->empresaIds());
        $partes = [];
        if ($resultado['urgentes'] > 0) {
            $partes[] = $resultado['urgentes'].' urgente(s)';
        }
        if ($resultado['asignadas'] > 0) {
            $partes[] = $resultado['asignadas'].' con fecha de pago';
        }
        if ($resultado['limpiadas'] > 0) {
            $partes[] = $resultado['limpiadas'].' sin programación';
        }
        $mensaje = $partes === []
            ? 'No hubo cambios para grabar.'
            : 'Se grabó: '.implode(', ', $partes).'.';

        $redirect = redirect()
            ->route('programar_pagos_proveedor', $this->paraQuery($this->filtros($request)))
            ->with('mensaje', $mensaje);
        if ($resultado['omitidas'] > 0) {
            $redirect->with(
                'mensaje_aviso',
                $resultado['omitidas'].' fila(s) no se grabaron: están en una propuesta abierta, la fecha no es válida o el medio no admite urgente.'
            );
        }

        return $redirect;
    }

    public function exportar(Request $request, ?string $formato = null)
    {
        can('programar-pagos-proveedor');
        ini_set('memory_limit', '512M');
        ini_set('max_execution_time', '180');

        $filtros = $this->filtros($request);
        $modo = (string) $request->query('informe', ProyeccionPagosProgramacionSupport::INFORME_PROGRAMADOS);
        if (! in_array($modo, [
            ProyeccionPagosProgramacionSupport::INFORME_URGENTES,
            ProyeccionPagosProgramacionSupport::INFORME_PROGRAMADOS,
        ], true)) {
            $modo = ProyeccionPagosProgramacionSupport::INFORME_PROGRAMADOS;
        }

        $formato = strtoupper((string) $formato);
        if (! in_array($formato, ['PDF', 'EXCEL', 'CSV'], true)) {
            return redirect()->route('programar_pagos_proveedor', $this->paraQuery($filtros));
        }

        $informe = $this->support->informe($filtros, $this->empresaIds(), $modo);
        $titulo = $modo === ProyeccionPagosProgramacionSupport::INFORME_URGENTES
            ? 'Urgentes — transferencias'
            : 'Programados por fecha de pago';
        $subtitulo = $titulo.' · '.count($informe['filas']).' registros';
        if ($informe['truncado']) {
            $subtitulo .= ' (recorte)';
        }

        if ($formato === 'PDF') {
            $html = view('compras.proyeccion_pagos_programacion.listado', [
                'filas' => $informe['filas'],
                'titulo' => $titulo,
                'subtitulo' => $subtitulo,
            ])->render();
            $ruta = storage_path('pdf/listados/programar_pagos_'.date('Ymd_His').'.pdf');
            if (! is_dir(dirname($ruta))) {
                @mkdir(dirname($ruta), 0775, true);
            }
            DompdfListadoSupport::guardarLegalLandscape($html, $ruta, [
                'titulo_corto' => $titulo,
            ]);

            return response()->download($ruta)->deleteFileAfterSend(true);
        }

        $export = new ProyeccionPagosProgramacionExport($informe['filas'], $titulo, $subtitulo);
        $nombre = 'programar_pagos_'.date('Ymd_His');

        return $formato === 'CSV'
            ? $export->download($nombre.'.csv', Excel::CSV)
            : $export->download($nombre.'.xlsx');
    }

    /**
     * @return array<string, mixed>
     */
    private function filtros(Request $request): array
    {
        $desde = ProyeccionPagosProgramacionSupport::normalizarFecha((string) $request->input('vencimiento_desde', ''))
            ?? now()->toDateString();
        $hasta = ProyeccionPagosProgramacionSupport::normalizarFecha((string) $request->input('vencimiento_hasta', ''))
            ?? now()->addDays(30)->toDateString();
        if ($desde > $hasta) {
            [$desde, $hasta] = [$hasta, $desde];
        }

        $asignacion = (string) $request->input('asignacion', ProyeccionPagosProgramacionSupport::ASIGNACION_TODAS);
        if (! in_array($asignacion, [
            ProyeccionPagosProgramacionSupport::ASIGNACION_TODAS,
            ProyeccionPagosProgramacionSupport::ASIGNACION_SIN,
            ProyeccionPagosProgramacionSupport::ASIGNACION_CON,
            ProyeccionPagosProgramacionSupport::ASIGNACION_URGENTES,
            ProyeccionPagosProgramacionSupport::ASIGNACION_VENCIDOS,
        ], true)) {
            $asignacion = ProyeccionPagosProgramacionSupport::ASIGNACION_TODAS;
        }

        $medio = (string) $request->input('medio', ProyeccionPagosProgramacionSupport::MEDIO_TODOS);
        if (! in_array($medio, [
            ProyeccionPagosProgramacionSupport::MEDIO_TODOS,
            ProyeccionPagosProgramacionSupport::MEDIO_TRF,
            ProyeccionPagosProgramacionSupport::MEDIO_CHEQUE,
        ], true)) {
            $medio = ProyeccionPagosProgramacionSupport::MEDIO_TODOS;
        }

        [$empresaId, $empresaScope] = $this->resolverEmpresa($request);
        $incluirVencidas = ! $request->boolean('consultar') || $request->boolean('incluir_vencidas');

        return [
            'vencimiento_desde' => $desde,
            'vencimiento_hasta' => $hasta,
            'empresa_id' => $empresaId,
            'empresa_scope' => $empresaScope,
            'asignacion' => $asignacion,
            'medio' => $medio,
            'texto' => trim((string) $request->input('texto', '')),
            'incluir_vencidas' => $incluirVencidas,
            'consultar' => 1,
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    private function paraQuery(array $filtros): array
    {
        $q = [
            'consultar' => 1,
            'vencimiento_desde' => $filtros['vencimiento_desde'],
            'vencimiento_hasta' => $filtros['vencimiento_hasta'],
            'asignacion' => $filtros['asignacion'],
            'medio' => $filtros['medio'],
            'texto' => $filtros['texto'],
        ];
        if (! empty($filtros['incluir_vencidas'])) {
            $q['incluir_vencidas'] = 1;
        }
        if (($filtros['empresa_scope'] ?? '') === 'todas') {
            $q['empresa_todas'] = 1;
        } elseif ((int) ($filtros['empresa_id'] ?? 0) > 0) {
            $q['empresa_id'] = (int) $filtros['empresa_id'];
        }

        return $q;
    }

    /** @return array{0: int, 1: string} */
    private function resolverEmpresa(Request $request): array
    {
        $permitidas = $this->empresaIds();
        $valida = function (int $empresaId) use ($permitidas): int {
            if ($empresaId <= 0) {
                return 0;
            }
            if ($permitidas !== [] && ! in_array($empresaId, $permitidas, true)) {
                return 0;
            }

            return $empresaId;
        };

        if ($request->boolean('empresa_todas') || $request->input('empresa_scope') === 'todas') {
            return [0, 'todas'];
        }

        if ($request->filled('empresa_id') && (int) $request->input('empresa_id') > 0) {
            $empresaId = $valida((int) $request->input('empresa_id'));

            return $empresaId > 0 ? [$empresaId, 'una'] : [0, 'todas'];
        }

        $default = $valida((int) (EmpresaFiltroExternoSupport::empresaDefaultDesdeSesion() ?? 0));
        if ($default > 0) {
            return [$default, 'una'];
        }

        return [0, 'todas'];
    }

    /** @return list<int> */
    private function empresaIds(): array
    {
        return $this->empresaRepository->allFiltrado()
            ->pluck('id')
            ->map(static fn ($id) => (int) $id)
            ->filter(static fn (int $id) => $id > 0)
            ->values()
            ->all();
    }
}
