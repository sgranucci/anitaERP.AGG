<?php

namespace App\Http\Controllers\Solicitudpago;

use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionConcepto_Solicitudpago;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Repositories\Contable\CentrocostoRepositoryInterface;
use App\Repositories\Solicitudpago\Concepto_SolicitudpagoRepositoryInterface;
use App\Repositories\Solicitudpago\FormapagosolRepositoryInterface;
use App\Repositories\Solicitudpago\Sector_SolicitudpagoRepositoryInterface;
use App\Support\Solicitudpago\ConceptoSolicitudpagoEstados;
use App\Support\Solicitudpago\ConceptoSolicitudpagoFormaPago;
use Illuminate\Http\Request;

class Concepto_SolicitudpagoController extends Controller
{
    public function __construct(
        private Concepto_SolicitudpagoRepositoryInterface $repository,
        private Sector_SolicitudpagoRepositoryInterface $sectorRepository,
        private FormapagosolRepositoryInterface $formapagosolRepository,
        private EmpresaRepositoryInterface $empresaRepository,
        private CentrocostoRepositoryInterface $centrocostoRepository,
    ) {
    }

    public function index()
    {
        can('listar-concepto-solicitud-pago');
        $datas = $this->repository->all();

        return view('solicitudpago.concepto_solicitudpago.index', compact('datas'));
    }

    public function crear()
    {
        can('crear-concepto-solicitud-pago');

        return view('solicitudpago.concepto_solicitudpago.crear', $this->datosFormulario());
    }

    public function guardar(ValidacionConcepto_Solicitudpago $request)
    {
        try {
            $this->repository->create($request->validated());

            return redirect('solicitudpago/concepto_solicitudpago')
                ->with('mensaje', 'Concepto creado con éxito');
        } catch (\Throwable $e) {
            return back()->withInput()->with('mensaje', $e->getMessage());
        }
    }

    public function editar($id)
    {
        can('editar-concepto-solicitud-pago');
        $data = $this->repository->findOrFail($id);

        return view('solicitudpago.concepto_solicitudpago.editar', array_merge(
            $this->datosFormulario(),
            compact('data')
        ));
    }

    public function actualizar(ValidacionConcepto_Solicitudpago $request, $id)
    {
        can('actualizar-concepto-solicitud-pago');

        try {
            $this->repository->update($request->validated(), $id);

            return redirect('solicitudpago/concepto_solicitudpago')
                ->with('mensaje', 'Concepto actualizado con éxito');
        } catch (\Throwable $e) {
            return back()->withInput()->with('mensaje', $e->getMessage());
        }
    }

    public function eliminar(Request $request, $id)
    {
        can('borrar-concepto-solicitud-pago');

        if ($request->ajax()) {
            if ($this->repository->delete($id)) {
                return response()->json(['mensaje' => 'ok']);
            }

            return response()->json(['mensaje' => 'ng']);
        }

        abort(404);
    }

    /** @return array<string, mixed> */
    private function datosFormulario(): array
    {
        return [
            'sector_query' => $this->sectorRepository->all(),
            'formapagosol_query' => $this->formapagosolRepository->all(),
            'empresa_query' => $this->empresaRepository->allFiltrado(),
            'centrocosto_query' => $this->centrocostoRepository->all(),
            'forma_pago_enum' => ConceptoSolicitudpagoFormaPago::opciones(),
            'estado_enum' => ConceptoSolicitudpagoEstados::opciones(),
        ];
    }
}
