<?php

namespace App\Http\Controllers\Compras;

use App\Exports\Compras\OrdencompraLegajoBandejaExport;
use App\Http\Controllers\Controller;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Services\Compras\OrdencompraGestionService;
use App\Services\Compras\OrdencompraLegajoBandejaPaqueteService;
use App\Services\Compras\OrdencompraLegajoBandejaService;
use App\Services\Stock\RecepcionProveedorPdfService;
use App\Support\Compras\OrdencompraEnvioCuentasAPagarGateSupport;
use App\Support\Compras\OrdencompraLegajoAnitaScanFacturaSupport;
use App\Support\Compras\OrdencompraLegajoBandejaFiltros;
use App\Support\Compras\OrdencompraListadoFiltros;
use App\Support\Compras\OrdencompraSectorVisibilidadSupport;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Excel;

class OrdencompraLegajoBandejaController extends Controller
{
    public function __construct(
        private OrdencompraLegajoBandejaService $service,
        private OrdencompraLegajoBandejaPaqueteService $paqueteService,
        private OrdencompraGestionService $gestionService,
        private RecepcionProveedorPdfService $comPdfService,
        private EmpresaRepositoryInterface $empresaRepository,
    ) {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        $this->autorizar();

        $filtros = $this->filtrosDesdeRequest($request);
        $filas = $this->service->paginar($filtros);
        $filtrosQuery = OrdencompraLegajoBandejaFiltros::paraQueryString($filtros);

        return view('compras.legajo_bandeja.index', [
            'filas' => $filas,
            'filtros' => $filtros,
            'filtrosQuery' => $filtrosQuery,
            'camposFiltro' => OrdencompraListadoFiltros::CAMPOS,
            'empresa_query' => $this->empresaRepository->allFiltrado(),
            'puede_actualizar' => can('actualizar-ordencompra', false),
            'puede_asignar_com' => $this->puedeAsignarCom(),
            'puede_cargar_cxp' => can('crear-comprobante-proveedor', false),
            'puede_enviar_pagos' => can('crear-comprobante-proveedor', false)
                || can('actualizar-ordencompra', false)
                || can('listar-legajo-compra', false),
            'puede_devolver_cxp' => can('editar-pagoproveedor', false)
                || can('crear-pagoproveedor', false)
                || can('actualizar-ordencompra', false)
                || can('listar-legajo-compra', false),
            'puede_devolver_compras' => can('crear-comprobante-proveedor', false)
                || can('actualizar-ordencompra', false)
                || can('listar-legajo-compra', false),
            'puede_ver_comprobante' => can('editar-comprobante-proveedor', false) || can('listar-comprobante-proveedor', false),
            'puede_ver_pago' => can('editar-pagoproveedor', false) || can('listar-pagoproveedor', false),
            'puede_archivar' => can('actualizar-ordencompra', false)
                || can('editar-pagoproveedor', false)
                || can('crear-pagoproveedor', false)
                || can('listar-legajo-compra', false),
            'dias_recordatorio' => (int) config('compras.legajo.recordatorio_dias', 3),
            'alcanceSector' => OrdencompraSectorVisibilidadSupport::etiquetaAlcance(),
            'sinSectorAsignado' => OrdencompraSectorVisibilidadSupport::sinSectorAsignado(),
            'vistasPermitidas' => OrdencompraSectorVisibilidadSupport::vistasBandejaPermitidas(),
        ]);
    }

    public function exportar(Request $request, ?string $formato = null)
    {
        $this->autorizar();

        ini_set('memory_limit', '512M');

        $filtros = $this->filtrosDesdeRequest($request);
        $coleccion = $this->service->listar($filtros);
        $filas = $coleccion->all();
        $titulo = $this->tituloVista((string) ($filtros['vista'] ?? ''));
        $subtitulo = $this->subtitulo($filtros);

        switch (strtoupper((string) $formato)) {
            case 'PDF':
                $view = \View::make('compras.legajo_bandeja.listado', compact(
                    'filas',
                    'titulo',
                    'subtitulo',
                ))->render();
                $path = storage_path('pdf/listados');
                if (! is_dir($path)) {
                    mkdir($path, 0775, true);
                }
                $nombrePdf = 'legajos_compras_'.date('Ymd_His');
                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal', 'landscape');
                $pdf->loadHTML($view)->save($path.'/'.$nombrePdf.'.pdf');

                return response()->download($path.'/'.$nombrePdf.'.pdf');

            case 'EXCEL':
                return (new OrdencompraLegajoBandejaExport($filas, $titulo, $subtitulo))
                    ->download('legajos_compras.xlsx');

            case 'CSV':
                return (new OrdencompraLegajoBandejaExport($filas, $titulo, $subtitulo))
                    ->download('legajos_compras.csv', Excel::CSV);
        }

        return redirect()->route('consultar_legajo_compra', OrdencompraLegajoBandejaFiltros::paraQueryString($filtros));
    }

    public function historia(int $id)
    {
        $this->autorizar();
        $oc = $this->ocParaLectura($id);

        return response()->json($this->gestionService->leerHistoriaLegajo((int) $oc->id));
    }

    public function paquete(int $id)
    {
        $this->autorizar();
        $oc = $this->ocParaLectura($id);

        return response()->json($this->paqueteService->paquete($oc));
    }

    public function asignarCom(Request $request, int $id)
    {
        $this->autorizar();
        if (! $this->puedeAsignarCom()) {
            can('actualizar-ordencompra');
        }

        $oc = $this->paqueteService->encontrarOcVisible($id);
        $asignaciones = $request->input('asignaciones');
        if (is_array($asignaciones) && $asignaciones !== []) {
            $this->paqueteService->asignarMultiples($oc, $asignaciones);
            $mensaje = 'Asignaciones de COM guardadas para los comprobantes del legajo.';
        } else {
            $facturaRef = (string) $request->input('precarga_id', '');
            $recepcionIds = (array) $request->input('recepcion_ids', []);
            $this->paqueteService->asignar($oc, $facturaRef, $recepcionIds);
            $mensaje = $recepcionIds === [] || $recepcionIds === ['']
                ? 'Se quitó la asignación de COM de la factura.'
                : 'COM asignada a la factura. Cuentas a pagar puede cargar el comprobante.';
        }

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'ok' => true,
                'mensaje' => $mensaje,
                'paquete' => $this->paqueteService->paquete($oc),
            ]);
        }

        return redirect()
            ->route('consultar_legajo_compra', OrdencompraLegajoBandejaFiltros::paraQueryString($this->filtrosDesdeRequest($request)))
            ->with('mensaje', $mensaje);
    }

    public function corregirTipoDocumento(Request $request, int $id)
    {
        $this->autorizar();
        if (! $this->puedeAsignarCom()) {
            can('actualizar-ordencompra');
        }

        $validated = $request->validate([
            'precarga_id' => 'required|integer|min:1',
            'tipo' => ['required', 'string', 'max:8', 'regex:/^[A-Za-z]{2,6}$/'],
        ]);

        $oc = $this->paqueteService->encontrarOcVisible($id);
        $this->paqueteService->corregirTipoDocumento(
            $oc,
            (int) $validated['precarga_id'],
            (string) $validated['tipo']
        );

        return response()->json([
            'ok' => true,
            'mensaje' => 'Tipo de comprobante actualizado.',
            'paquete' => $this->paqueteService->paquete($oc),
        ]);
    }

    public function verFacturaPdf(Request $request, int $id, int $precarga)
    {
        $this->autorizar();
        $oc = $this->ocParaLectura($id);
        $precargaModel = $this->paqueteService->assertPrecargaDelLegajo($oc, $precarga);
        $path = $this->paqueteService->rutaFacturaPdf($precargaModel);
        if ($path === null) {
            abort(404, 'No se encontró el PDF de la factura.');
        }
        $nombre = basename($path);

        if ($request->boolean('inline')) {
            return response()->file($path, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="'.$nombre.'"',
            ]);
        }

        return response()->download($path, $nombre);
    }

    public function verFacturaAnitaPdf(Request $request, int $id, int $documento)
    {
        $this->autorizar();
        $oc = $this->ocParaLectura($id);
        $oc->loadMissing('empresas:id,codigo,nombre');
        if (! OrdencompraLegajoAnitaScanFacturaSupport::perteneceAlLegajo($oc, $documento)) {
            abort(404, 'La factura escaneada no pertenece a este legajo.');
        }
        $path = OrdencompraLegajoAnitaScanFacturaSupport::rutaPdf($documento);
        if ($path === null) {
            abort(404, 'No se encontró el PDF escaneado en Anita.');
        }
        $nombre = basename($path);

        if ($request->boolean('inline', true)) {
            return response()->file($path, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="'.$nombre.'"',
            ]);
        }

        return response()->download($path, $nombre);
    }

    public function verComPdf(Request $request, int $id, int $recepcion)
    {
        $this->autorizar();
        $oc = $this->ocParaLectura($id);
        $this->paqueteService->assertComDelLegajo($oc, $recepcion);

        return $this->comPdfService->descargarCom($recepcion, $request->boolean('inline', true));
    }

    public function guardarNota(Request $request, int $id)
    {
        $this->autorizar();
        $oc = $this->paqueteService->encontrarOcVisible($id);

        $validated = $request->validate([
            'nota_legajo' => 'nullable|string|max:4000',
        ]);
        $nota = trim((string) ($validated['nota_legajo'] ?? ''));
        $oc->nota_legajo = $nota !== '' ? $nota : null;
        $oc->save();

        $mensaje = $nota !== ''
            ? 'Nota del legajo guardada.'
            : 'Nota del legajo eliminada.';

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'ok' => true,
                'mensaje' => $mensaje,
                'nota_legajo' => $nota,
                'tiene_nota' => $nota !== '',
            ]);
        }

        return redirect()
            ->route('consultar_legajo_compra', OrdencompraLegajoBandejaFiltros::paraQueryString($this->filtrosDesdeRequest($request)))
            ->with('mensaje', $mensaje);
    }

    private function filtrosDesdeRequest(Request $request): array
    {
        $empresaDefault = optional($this->empresaRepository->allFiltrado()->first())->id;
        $filtros = OrdencompraLegajoBandejaFiltros::resolverDesdeRequest(
            $request,
            $empresaDefault ? (int) $empresaDefault : null
        );

        $vistaForzada = OrdencompraSectorVisibilidadSupport::vistaBandejaForzada();
        if ($vistaForzada !== null) {
            $filtros['vista'] = $vistaForzada;
        } elseif (! $request->has('vista')) {
            $filtros['vista'] = $this->vistaInicialSegunRol();
        }

        return $filtros;
    }

    private function vistaInicialSegunRol(): string
    {
        $porSector = OrdencompraSectorVisibilidadSupport::vistaBandejaForzada();
        if ($porSector !== null) {
            return $porSector;
        }

        $nombre = OrdencompraSectorVisibilidadSupport::nombreSectorUsuario();
        if ($nombre === OrdencompraEnvioCuentasAPagarGateSupport::SECTOR_CUENTAS_A_PAGAR) {
            return OrdencompraLegajoBandejaFiltros::VISTA_CXP;
        }
        if ($nombre === OrdencompraEnvioCuentasAPagarGateSupport::SECTOR_PAGOS) {
            return OrdencompraLegajoBandejaFiltros::VISTA_PAGOS;
        }

        $compras = can('actualizar-ordencompra', false);
        $cxp = can('crear-comprobante-proveedor', false);
        $pagos = can('editar-pagoproveedor', false) || can('crear-pagoproveedor', false);
        if ($cxp && ! $compras) {
            return OrdencompraLegajoBandejaFiltros::VISTA_CXP;
        }
        if ($pagos && ! $compras && ! $cxp) {
            return OrdencompraLegajoBandejaFiltros::VISTA_PAGOS;
        }

        return OrdencompraLegajoBandejaFiltros::VISTA_PENDIENTES;
    }

    private function autorizar(): void
    {
        if (can('listar-legajo-compra', false) || can('listar-ordencompra', false)) {
            return;
        }
        can('listar-legajo-compra');
    }

    /**
     * Lectura de paquete/PDF/historia: sector propio, o consulta global (seguimiento / ver todos).
     */
    private function ocParaLectura(int $id): \App\Models\Compras\Ordencompra
    {
        if (OrdencompraSectorVisibilidadSupport::puedeVerTodos()
            || can('listar-seguimiento-legajo-compra', false)) {
            return $this->paqueteService->encontrarOcConsulta($id);
        }

        return $this->paqueteService->encontrarOcVisible($id);
    }

    private function puedeAsignarCom(): bool
    {
        return can('actualizar-ordencompra', false)
            || can('crear-comprobante-proveedor', false)
            || can('editar-precarga-proveedores', false)
            || can('listar-legajo-compra', false);
    }

    private function tituloVista(string $vista): string
    {
        return match ($vista) {
            OrdencompraLegajoBandejaFiltros::VISTA_ESTADOS => 'Legajos — estados',
            OrdencompraLegajoBandejaFiltros::VISTA_HISTORICO => 'Legajos — histórico',
            default => 'Legajos — pendientes',
        };
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private function subtitulo(array $filtros): string
    {
        $partes = [];
        $tab = (string) ($filtros['tab'] ?? '');
        if ($tab === OrdencompraLegajoBandejaFiltros::TAB_GASTRONOMIA) {
            $partes[] = 'Gastronomía';
        } elseif ($tab === OrdencompraLegajoBandejaFiltros::TAB_RESTO) {
            $partes[] = 'Resto de centros de costo';
        }
        if (! empty($filtros['atajo'])) {
            $partes[] = (string) $filtros['atajo'];
        }
        if (OrdencompraListadoFiltros::tieneCriteriosTexto($filtros)) {
            $partes[] = trim((string) ($filtros['valor'] ?? ''));
        }
        foreach (['nro_oc' => 'OC', 'nro_factura' => 'FC', 'nro_com' => 'COM', 'nro_op' => 'OP'] as $k => $etq) {
            if (! empty($filtros[$k])) {
                $partes[] = $etq.' '.$filtros[$k];
            }
        }

        return implode(' · ', $partes);
    }
}
