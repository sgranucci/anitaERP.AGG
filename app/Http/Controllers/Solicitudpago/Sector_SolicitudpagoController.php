<?php

namespace App\Http\Controllers\Solicitudpago;

use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionSector_Solicitudpago;
use App\Repositories\Contable\CentrocostoRepositoryInterface;
use App\Repositories\Solicitudpago\Sector_SolicitudpagoRepositoryInterface;
use Illuminate\Http\Request;

class Sector_SolicitudpagoController extends Controller
{
    private Sector_SolicitudpagoRepositoryInterface $repository;

    private CentrocostoRepositoryInterface $centrocostoRepository;

    public function __construct(
        Sector_SolicitudpagoRepositoryInterface $repository,
        CentrocostoRepositoryInterface $centrocostoRepository
    ) {
        $this->repository = $repository;
        $this->centrocostoRepository = $centrocostoRepository;
    }

    public function index()
    {
        can('listar-sector-solicitud-pago');
        $datas = $this->repository->all();

        return view('solicitudpago.sector_solicitudpago.index', compact('datas'));
    }

    public function crear()
    {
        can('crear-sector-solicitud-pago');
        $centrocosto_query = $this->centrocostoRepository->all();

        return view('solicitudpago.sector_solicitudpago.crear', compact('centrocosto_query'));
    }

    public function guardar(ValidacionSector_Solicitudpago $request)
    {
        $this->repository->create($request->validated());

        return redirect('solicitudpago/sector_solicitudpago')
            ->with('mensaje', 'Sector creado con éxito');
    }

    public function editar($id)
    {
        can('editar-sector-solicitud-pago');
        $data = $this->repository->findOrFail($id);
        $centrocosto_query = $this->centrocostoRepository->all();

        return view('solicitudpago.sector_solicitudpago.editar', compact('data', 'centrocosto_query'));
    }

    public function actualizar(ValidacionSector_Solicitudpago $request, $id)
    {
        can('actualizar-sector-solicitud-pago');
        $this->repository->update($request->validated(), $id);

        return redirect('solicitudpago/sector_solicitudpago')
            ->with('mensaje', 'Sector actualizado con éxito');
    }

    public function eliminar(Request $request, $id)
    {
        can('borrar-sector-solicitud-pago');

        if ($request->ajax()) {
            if ($this->repository->delete($id)) {
                return response()->json(['mensaje' => 'ok']);
            }

            return response()->json(['mensaje' => 'ng']);
        }

        abort(404);
    }
}
