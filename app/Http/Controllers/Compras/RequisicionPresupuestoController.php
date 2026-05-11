<?php

namespace App\Http\Controllers\Compras;

use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionRequisicionPresupuesto;
use App\Models\Compras\Requisicion;
use App\Repositories\Compras\Requisicion_Presupuesto_ArchivoRepositoryInterface;
use App\Repositories\Compras\RequisicionRepositoryInterface;
use App\Services\Compras\RequisicionPresupuestoService;
use App\Services\Compras\RequisicionService;
use Symfony\Component\HttpFoundation\Response;

class RequisicionPresupuestoController extends Controller
{
    private $presupuestoService;

    private $requisicionService;

    private $requisicionRepository;

    private $presupuestoArchivoRepository;

    public function __construct(
        RequisicionPresupuestoService $presupuestoService,
        RequisicionService $requisicionService,
        RequisicionRepositoryInterface $requisicionRepository,
        Requisicion_Presupuesto_ArchivoRepositoryInterface $presupuestoArchivoRepository
    ) {
        $this->presupuestoService = $presupuestoService;
        $this->requisicionService = $requisicionService;
        $this->requisicionRepository = $requisicionRepository;
        $this->presupuestoArchivoRepository = $presupuestoArchivoRepository;
    }

    private function puedeConsultar(): void
    {
        if (! can('listar-requisicion', false) && ! can('editar-requisicion', false) && ! can('crear-requisicion', false)) {
            abort(Response::HTTP_FORBIDDEN);
        }
    }

    /**
     * Misma regla que Imprimir PDF de la requisición (listar o editar).
     */
    private function puedeImprimirPdfPresupuesto(): void
    {
        if (! can('listar-requisicion', false) && ! can('editar-requisicion', false)) {
            abort(Response::HTTP_FORBIDDEN);
        }
    }

    private function puedeEditar(Requisicion $requisicion): void
    {
        can('actualizar-requisicion');
        if (! $this->requisicionService->usuarioPuedeEditarRequisicionEnCompras($requisicion)) {
            abort(Response::HTTP_FORBIDDEN, 'No puede modificar presupuestos de esta requisición.');
        }
    }

    public function index(Requisicion $requisicion)
    {
        $this->puedeConsultar();

        return response()->json([
            'presupuestos' => $this->presupuestoService->listarParaRequisicion((int) $requisicion->id),
            'lineas_requisicion' => $this->presupuestoService->lineasBaseRequisicion((int) $requisicion->id),
        ]);
    }

    public function show(Requisicion $requisicion, int $presupuesto)
    {
        $this->puedeConsultar();

        $detalle = $this->presupuestoService->obtenerDetalle((int) $requisicion->id, $presupuesto);
        if ($detalle === null) {
            abort(Response::HTTP_NOT_FOUND);
        }

        return response()->json($detalle);
    }

    public function store(ValidacionRequisicionPresupuesto $request, Requisicion $requisicion)
    {
        $this->puedeEditar($requisicion);

        $ret = $this->presupuestoService->crear($request, (int) $requisicion->id);
        if (! $ret['ok']) {
            return response()->json(['mensaje' => $ret['error'] ?? 'Error'], 422);
        }

        return response()->json(['mensaje' => 'ok', 'id' => $ret['id']]);
    }

    public function update(ValidacionRequisicionPresupuesto $request, Requisicion $requisicion, int $presupuesto)
    {
        $this->puedeEditar($requisicion);

        $ret = $this->presupuestoService->actualizar($request, (int) $requisicion->id, $presupuesto);
        if (! $ret['ok']) {
            return response()->json(['mensaje' => $ret['error'] ?? 'Error'], 422);
        }

        return response()->json(['mensaje' => 'ok']);
    }

    public function destroy(Requisicion $requisicion, int $presupuesto)
    {
        $this->puedeEditar($requisicion);

        $ret = $this->presupuestoService->eliminar((int) $requisicion->id, $presupuesto);
        if (! $ret['ok']) {
            return response()->json(['mensaje' => $ret['error'] ?? 'Error'], 422);
        }

        return response()->json(['mensaje' => 'ok']);
    }

    public function verArchivo(Requisicion $requisicion, int $presupuesto, int $archivo)
    {
        $this->puedeConsultar();

        $arch = $this->presupuestoArchivoRepository->findPorPresupuestoYId($presupuesto, $archivo);
        if (! $arch) {
            abort(Response::HTTP_NOT_FOUND);
        }

        $pres = $arch->requisicion_presupuesto;
        if (! $pres || (int) $pres->requisicion_id !== (int) $requisicion->id) {
            abort(Response::HTTP_NOT_FOUND);
        }

        $path = $this->presupuestoService->rutaFisicaArchivo((int) $requisicion->id, $presupuesto, $arch);
        if (! is_file($path)) {
            abort(Response::HTTP_NOT_FOUND);
        }

        return response()->file($path);
    }

    public function pdfPresupuesto(Requisicion $requisicion, int $presupuesto)
    {
        $this->puedeImprimirPdfPresupuesto();

        $detalle = $this->presupuestoService->obtenerDetalle((int) $requisicion->id, $presupuesto);
        if ($detalle === null) {
            abort(Response::HTTP_NOT_FOUND);
        }

        $req = $this->requisicionRepository->find((int) $requisicion->id);

        $html = view('compras.requisicion.presupuesto_pdf', compact('req', 'detalle'))->render();

        $pdf = \App::make('dompdf.wrapper');
        $pdf->setPaper('a4', 'portrait');
        $pdf->loadHTML($html);

        $provSlug = preg_replace('/[^\w\-]+/', '_', (string) ($detalle['proveedor_codigo'] ?? 'prov'));
        $nombreArchivo = 'Presupuesto_req'.$req->numerorequisicion.'_'.$provSlug.'_'.$presupuesto.'.pdf';

        return $pdf->download($nombreArchivo);
    }

    public function formularioImpresionPresupuesto(Requisicion $requisicion, int $presupuesto)
    {
        $this->puedeImprimirPdfPresupuesto();

        $detalle = $this->presupuestoService->obtenerDetalle((int) $requisicion->id, $presupuesto);
        if ($detalle === null) {
            abort(Response::HTTP_NOT_FOUND);
        }

        $req = $this->requisicionRepository->find((int) $requisicion->id);
        $urlPdf = route('requisicion_presupuesto_pdf', [
            'requisicion' => $requisicion->id,
            'presupuesto' => $presupuesto,
        ]);

        return view('compras.requisicion.presupuesto_impresion', compact('req', 'detalle', 'urlPdf'));
    }
}
