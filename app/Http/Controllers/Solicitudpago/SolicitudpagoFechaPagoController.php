<?php

declare(strict_types=1);

namespace App\Http\Controllers\Solicitudpago;

use App\Exports\Solicitudpago\SolicitudpagoFechaPagoImpagasExport;
use App\Http\Controllers\Controller;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Support\Configuracion\EmpresaFiltroExternoSupport;
use App\Support\Solicitudpago\SolicitudpagoFechaPagoSupport;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Excel;

/**
 * Marca solicitudes autorizadas, agrupadas por vencimiento, con la fecha en que se van a pagar.
 */
class SolicitudpagoFechaPagoController extends Controller
{
    public function __construct(
        private readonly SolicitudpagoFechaPagoSupport $support,
        private readonly EmpresaRepositoryInterface $empresaRepository,
    ) {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        can('programar-fecha-pago-solicitud');

        $filtros = $this->filtros($request);
        $empresaIds = $this->empresaIds();
        $listado = $this->support->listar(
            $filtros['vencimiento_desde'],
            $filtros['vencimiento_hasta'],
            $empresaIds,
            $filtros['empresa_id'] > 0 ? $filtros['empresa_id'] : null,
            $filtros['estado'],
            $filtros['asignacion'],
            $filtros['incluir_vencidas'],
        );

        $totales = [];
        foreach ($listado['filas'] as $solicitud) {
            $moneda = trim((string) (optional($solicitud->monedas)->abreviatura ?: 'ARS'));
            if ($moneda === '') {
                $moneda = 'ARS';
            }
            $totales[$moneda] = ($totales[$moneda] ?? 0) + (float) $solicitud->monto;
        }

        return view('solicitudpago.fecha_pago.index', [
            'filas' => $listado['filas'],
            'truncado' => $listado['truncado'],
            'filtros' => $filtros,
            'totales' => $totales,
            'empresa_query' => $this->empresaRepository->allFiltrado(),
            'hoy' => now()->toDateString(),
        ]);
    }

    public function grabar(Request $request)
    {
        can('programar-fecha-pago-solicitud');

        $fechas = $request->input('fechas', []);
        if (! is_array($fechas)) {
            $fechas = [];
        }

        $resultado = $this->support->grabar($fechas, $this->empresaIds());
        $partes = [];
        if ($resultado['asignadas'] > 0) {
            $partes[] = $resultado['asignadas'].($resultado['asignadas'] === 1 ? ' solicitud con fecha de pago' : ' solicitudes con fecha de pago');
        }
        if ($resultado['limpiadas'] > 0) {
            $partes[] = $resultado['limpiadas'].($resultado['limpiadas'] === 1 ? ' solicitud sin fecha' : ' solicitudes sin fecha');
        }
        $mensaje = $partes === []
            ? 'No hubo cambios para grabar.'
            : 'Se grabó: '.implode(' y ', $partes).'.';

        $redirect = redirect()
            ->route('programar_fecha_pago_solicitud', $this->filtros($request))
            ->with('mensaje', $mensaje);
        if ($resultado['omitidas'] > 0) {
            $redirect->with('mensaje_aviso', $resultado['omitidas'].' fila(s) no se grabaron: no están autorizadas o pagadas, o la fecha no es válida.');
        }

        return $redirect;
    }

    public function reporte(Request $request, string $formato)
    {
        can('programar-fecha-pago-solicitud');
        ini_set('memory_limit', '512M');
        ini_set('max_execution_time', '120');

        $filtros = $this->filtros($request);
        $informe = $this->support->informeImpagas(
            $this->empresaIds(),
            $filtros['empresa_id'] > 0 ? $filtros['empresa_id'] : null,
        );
        $empresa = '';
        if ($filtros['empresa_id'] > 0) {
            $empresa = (string) (optional($this->empresaRepository->allFiltrado()->firstWhere('id', $filtros['empresa_id']))->nombre ?? '');
        }
        $subtitulo = 'Impagas con fecha de pago, por empresa y ordenadas por fecha de pago';
        if ($empresa !== '') {
            $subtitulo .= ' · '.$empresa;
        } elseif (($filtros['empresa_scope'] ?? '') === 'todas') {
            $subtitulo .= ' · Todas mis empresas';
        }

        $formato = strtoupper($formato);
        if ($formato === 'PDF') {
            $html = view('solicitudpago.fecha_pago.reporte_pdf', [
                'filas' => $informe['filas'],
                'totales' => $informe['totales'],
                'subtitulo' => $subtitulo,
            ])->render();
            $path = storage_path('pdf/listados');
            if (! is_dir($path)) {
                @mkdir($path, 0775, true);
            }
            $nombre = 'impagas_fecha_pago_'.date('Ymd_His').'.pdf';
            $pdf = \App::make('dompdf.wrapper');
            $pdf->setPaper('legal', 'landscape');
            $pdf->loadHTML($html)->save($path.'/'.$nombre);

            return response()->download($path.'/'.$nombre)->deleteFileAfterSend(true);
        }

        if (in_array($formato, ['EXCEL', 'CSV'], true)) {
            $export = new SolicitudpagoFechaPagoImpagasExport($informe['filas'], $informe['totales'], $subtitulo);
            $nombre = 'impagas_fecha_pago_'.date('Ymd_His');

            return $formato === 'CSV'
                ? $export->download($nombre.'.csv', Excel::CSV)
                : $export->download($nombre.'.xlsx');
        }

        return redirect()->route('programar_fecha_pago_solicitud', $filtros);
    }

    /** @return array{vencimiento_desde: string, vencimiento_hasta: string, empresa_id: int, empresa_scope: string, estado: string, asignacion: string, incluir_vencidas: bool, consultar: int} */
    private function filtros(Request $request): array
    {
        $desde = SolicitudpagoFechaPagoSupport::normalizarFecha((string) $request->input('vencimiento_desde', ''))
            ?? now()->toDateString();
        $hasta = SolicitudpagoFechaPagoSupport::normalizarFecha((string) $request->input('vencimiento_hasta', ''))
            ?? now()->addDays(30)->toDateString();
        if ($desde > $hasta) {
            [$desde, $hasta] = [$hasta, $desde];
        }

        $estado = (string) $request->input('estado', SolicitudpagoFechaPagoSupport::ESTADO_AUTORIZADAS);
        if (! in_array($estado, [
            SolicitudpagoFechaPagoSupport::ESTADO_AUTORIZADAS,
            SolicitudpagoFechaPagoSupport::ESTADO_PAGADAS,
            SolicitudpagoFechaPagoSupport::ESTADO_AMBAS,
        ], true)) {
            $estado = SolicitudpagoFechaPagoSupport::ESTADO_AUTORIZADAS;
        }

        $asignacion = (string) $request->input('asignacion', SolicitudpagoFechaPagoSupport::ASIGNACION_SIN);
        if (! in_array($asignacion, [
            SolicitudpagoFechaPagoSupport::ASIGNACION_TODAS,
            SolicitudpagoFechaPagoSupport::ASIGNACION_SIN,
            SolicitudpagoFechaPagoSupport::ASIGNACION_CON,
        ], true)) {
            $asignacion = SolicitudpagoFechaPagoSupport::ASIGNACION_SIN;
        }

        [$empresaId, $empresaScope] = $this->resolverEmpresa($request);

        $incluirVencidas = ! $request->boolean('consultar') || $request->boolean('incluir_vencidas');

        return [
            'vencimiento_desde' => $desde,
            'vencimiento_hasta' => $hasta,
            'empresa_id' => $empresaId,
            'empresa_scope' => $empresaScope,
            'estado' => $estado,
            'asignacion' => $asignacion,
            'incluir_vencidas' => $incluirVencidas,
            'consultar' => 1,
        ];
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
};
