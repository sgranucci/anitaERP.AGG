<?php

namespace App\Http\Controllers\Caja;

use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionRendicionMaquinavendingCaja;
use App\Models\Caja\Caja;
use App\Queries\Caja\Caja_AsignacionQueryInterface;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Services\Caja\RendicionMaquinavendingCajaService;
use App\Support\Caja\RendicionMaquinavendingCajaListadoFiltros;
use App\Support\Caja\RendicionMaquinavendingCajaPermiso;
use App\Support\Listado\FiltrosListadoRequest;
use Auth;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class RendicionMaquinavendingController extends Controller
{
    public function __construct(
        private RendicionMaquinavendingCajaService $service,
        private Caja_AsignacionQueryInterface $cajaAsignacionQuery,
        private EmpresaRepositoryInterface $empresaRepository,
    ) {
    }

    public function index(Request $request)
    {
        can('listar-rendicion-maquinavending-caja');

        $filtros = $this->resolverFiltrosListado($request);
        $rendiciones = $this->service->listar($filtros, true);

        return view('caja.rendicionmaquinavending.index', [
            'rendiciones' => $rendiciones,
            'filtros' => $filtros,
            'filtrosQuery' => RendicionMaquinavendingCajaListadoFiltros::paraQueryString($filtros),
            'camposFiltro' => RendicionMaquinavendingCajaListadoFiltros::CAMPOS,
            'empresa_query' => $this->empresaRepository->allFiltrado(),
        ]);
    }

    public function listar(Request $request, ?string $formato = null, ?string $busqueda = null)
    {
        can('listar-rendicion-maquinavending-caja');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = $this->resolverFiltrosListado($request, $busqueda);
        $rendiciones = $this->service->listar($filtros, false);

        if ($formato === 'PDF') {
            $view = \View::make('caja.rendicionmaquinavending.listado', compact('rendiciones'))->render();
            $path = storage_path('pdf/listados');
            $nombrePdf = 'listado_rendicion_maquinavending_caja';
            $pdf = \App::make('dompdf.wrapper');
            $pdf->setPaper('legal', 'landscape');
            $pdf->loadHTML($view)->save($path.'/'.$nombrePdf.'.pdf');

            return response()->download($path.'/'.$nombrePdf.'.pdf');
        }

        return redirect()->route('rendicionmaquinavending', RendicionMaquinavendingCajaListadoFiltros::paraQueryString($filtros));
    }

    public function crear(?int $caja = null)
    {
        can('crear-rendicion-maquinavending-caja');

        [$cajaId, $nombreCaja] = $this->resolverCaja($caja);
        if ($cajaId <= 0) {
            return redirect()
                ->route('rendicionmaquinavending')
                ->with('errores', ['No tiene caja asignada para hoy. Debe ingresar desde Movimientos de caja o solicitar asignación de cajero.']);
        }

        $empresaQuery = $this->empresaRepository->allFiltrado();

        return view('caja.rendicionmaquinavending.crear', [
            'caja_id' => $cajaId,
            'nombreCaja' => $nombreCaja,
            'empresa_query' => $empresaQuery,
            'data' => null,
        ]);
    }

    public function guardar(ValidacionRendicionMaquinavendingCaja $request)
    {
        can('crear-rendicion-maquinavending-caja');

        try {
            $cabecera = $this->service->cabeceraDesdeRequest($request->validated());
            $movimientos = $this->service->normalizarMovimientosRequest($request->input('movimientos', []));
            $resultado = $this->service->guardar($cabecera, $movimientos);
            $presentacion = $resultado['presentacion'];
        } catch (InvalidArgumentException|\RuntimeException $e) {
            return redirect()->back()->withInput()->with('errores', [$e->getMessage()]);
        }

        $redirect = redirect('caja/rendicionmaquinavending')
            ->with('mensaje', 'Rendición vending presentada en caja con éxito')
            ->with('url_comprobante_pdf', route('imprimir_rendicion_maquinavending', [
                'id' => $presentacion->id,
                'inline' => 1,
            ]));

        if (($resultado['advertencias_anita'] ?? []) !== []) {
            $redirect->with('advertencias', $resultado['advertencias_anita']);
        }

        return $redirect;
    }

    public function editar(int $id)
    {
        can('editar-rendicion-maquinavending-caja');

        $data = $this->service->findConDetalle($id);
        try {
            RendicionMaquinavendingCajaPermiso::assertModificacionPermitida($data);
        } catch (InvalidArgumentException $e) {
            return redirect('caja/rendicionmaquinavending')
                ->with('errores', [$e->getMessage()]);
        }

        return view('caja.rendicionmaquinavending.editar', [
            'data' => $data,
            'empresa_query' => $this->empresaRepository->allFiltrado(),
            'nombreCaja' => (string) ($data->caja?->nombre ?? ''),
        ]);
    }

    public function actualizar(ValidacionRendicionMaquinavendingCaja $request, int $id)
    {
        can('actualizar-rendicion-maquinavending-caja');

        $rendicion = $this->service->findConDetalle($id);
        try {
            RendicionMaquinavendingCajaPermiso::assertModificacionPermitida($rendicion);
        } catch (InvalidArgumentException $e) {
            return redirect('caja/rendicionmaquinavending')
                ->with('errores', [$e->getMessage()]);
        }

        try {
            $cabecera = $this->service->cabeceraDesdeRequest($request->validated(), $id);
            $movimientos = $this->service->normalizarMovimientosRequest($request->input('movimientos', []));
            $resultado = $this->service->actualizar($id, $cabecera, $movimientos);
        } catch (InvalidArgumentException|\RuntimeException $e) {
            return redirect()->back()->withInput()->with('errores', [$e->getMessage()]);
        }

        $redirect = redirect('caja/rendicionmaquinavending')
            ->with('mensaje', 'Rendición vending en caja actualizada con éxito');

        if (($resultado['advertencias_anita'] ?? []) !== []) {
            $redirect->with('advertencias', $resultado['advertencias_anita']);
        }

        return $redirect;
    }

    public function eliminar(Request $request, int $id)
    {
        can('borrar-rendicion-maquinavending-caja');

        try {
            $advertenciasAnita = $this->service->eliminar($id);

            if ($request->ajax()) {
                return response()->json(['mensaje' => 'ok']);
            }

            $redirect = redirect('caja/rendicionmaquinavending')
                ->with('mensaje', 'Rendición vending eliminada con éxito');

            if ($advertenciasAnita !== []) {
                $redirect->with('advertencias', $advertenciasAnita);
            }

            return $redirect;
        } catch (\Throwable $e) {
            if ($request->ajax()) {
                return response()->json(['mensaje' => $e->getMessage() ?: 'ng']);
            }

            return redirect('caja/rendicionmaquinavending')->with('errores', [$e->getMessage() ?: 'No se pudo eliminar']);
        }
    }

    public function imprimir(Request $request, int $id)
    {
        can('listar-rendicion-maquinavending-caja');

        $rendicion = $this->service->findConDetalle($id);
        $datos = $this->service->datosComprobante($rendicion);
        $nombre = 'rendicion_vending_caja_'.$id.'.pdf';
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('caja.rendicionmaquinavending.comprobante', compact('datos'))
            ->setPaper('a4', 'portrait');

        return $request->boolean('inline', true)
            ? $pdf->stream($nombre)
            : $pdf->download($nombre);
    }

    public function apiConsultaRendicionVentas(Request $request): JsonResponse
    {
        if (! can('crear-rendicion-maquinavending-caja', false) && ! can('editar-rendicion-maquinavending-caja', false)) {
            abort(403);
        }

        $empresaId = (int) $request->input('empresa_id', 0);
        $excepto = (int) $request->input('excepto_rendicion_caja_id', 0);
        $consulta = trim((string) $request->input('consulta', ''));

        $pendientes = $this->service->rendicionesVentasPendientes(
            $excepto > 0 ? $excepto : null,
            $empresaId > 0 ? $empresaId : null,
        );

        if ($consulta !== '') {
            if (ctype_digit($consulta)) {
                $pendientes = $pendientes->filter(fn ($r) => (int) $r->id === (int) $consulta
                    || (int) $r->numero_cierre === (int) $consulta);
            } else {
                $needle = mb_strtoupper($consulta);
                $pendientes = $pendientes->filter(function ($r) use ($needle) {
                    $hay = mb_strtoupper(implode(' ', [
                        (string) $r->id,
                        (string) $r->numero_cierre,
                        (string) ($r->maquinavending->nombre ?? ''),
                        (string) ($r->maquinavending->puntoventa->codigo ?? ''),
                    ]));

                    return str_contains($hay, $needle);
                });
            }
        }

        $html = '';
        foreach ($pendientes->take(200) as $r) {
            $pv = trim(($r->maquinavending->puntoventa->codigo ?? '').' — '.($r->maquinavending->nombre ?? ''), ' —');
            $html .= '<tr>';
            $html .= '<td class="id">'.e((string) $r->id).'</td>';
            $html .= '<td class="numero_cierre">#'.e((string) $r->numero_cierre).'</td>';
            $html .= '<td class="maquina">'.e($pv).'</td>';
            $html .= '<td class="fecha">'.e((string) ($r->fecha_rendicion?->format('d/m/Y H:i') ?? '')).'</td>';
            $html .= '<td class="jornada">'.e((string) ($r->fecha_jornada?->format('d/m/Y') ?? '')).'</td>';
            $html .= '<td class="total">$'.e(number_format((float) $r->total_cobrado, 2, ',', '.')).'</td>';
            $html .= '<td><a href="#" class="btn btn-warning btn-sm elegir-rendicion-ventas" data-id="'.e((string) $r->id).'">Elegir</a></td>';
            $html .= '</tr>';
        }

        if ($html === '') {
            $html = '<tr><td colspan="7">Sin rendiciones vending pendientes de presentar en caja.</td></tr>';
        }

        return response()->json(['data' => $html]);
    }

    public function apiDatosRendicionVentas(Request $request): JsonResponse
    {
        if (! can('crear-rendicion-maquinavending-caja', false) && ! can('editar-rendicion-maquinavending-caja', false)) {
            abort(403);
        }

        $rendicionId = (int) $request->input('maquinavending_rendicion_id', 0);
        $excepto = (int) $request->input('excepto_rendicion_caja_id', 0);

        if ($rendicionId <= 0) {
            return response()->json(['ok' => false, 'mensaje' => 'Rendición inválida.'], 422);
        }

        try {
            $datos = $this->service->datosDesdeRendicionVentas(
                $rendicionId,
                $excepto > 0 ? $excepto : null,
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'mensaje' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true, 'datos' => $datos]);
    }

    /**
     * @return array<string, mixed>
     */
    private function resolverFiltrosListado(Request $request, ?string $busquedaRuta = null): array
    {
        $filtros = RendicionMaquinavendingCajaListadoFiltros::resolverDesdeRequest($request, $busquedaRuta);
        $filtros['empresas_asignadas'] = $this->empresaRepository->traeEmpresasAsignadas();

        if (FiltrosListadoRequest::solicitudLimpiaFiltros($request)) {
            return $filtros;
        }

        return $filtros;
    }

    /**
     * @return array{0: int, 1: string}
     */
    private function resolverCaja(?int $cajaParam): array
    {
        if ($cajaParam !== null && $cajaParam > 0) {
            $caja = Caja::query()->find($cajaParam);

            return [(int) $cajaParam, (string) ($caja->nombre ?? 'Caja #'.$cajaParam)];
        }

        $asignacion = $this->cajaAsignacionQuery->leeAsignacionPorUsuario((int) Auth::id(), Carbon::now());
        if ($asignacion && (int) $asignacion->caja_id > 0) {
            $cajaId = (int) $asignacion->caja_id;
            $nombre = (string) ($asignacion->cajas->nombre ?? Caja::query()->whereKey($cajaId)->value('nombre') ?? '');

            return [$cajaId, $nombre];
        }

        return [0, ''];
    }
}
