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

    public function editar(Request $request, $id)
    {
        $soloConsulta = $request->query('origen') === 'modal_consulta'
            || $request->query('vista') === 'consulta';
        if ($soloConsulta) {
            if (! $this->puedeConsultarConceptoOperativo()) {
                abort(403);
            }
        } else {
            can('editar-concepto-solicitud-pago');
        }

        $data = $this->repository->findOrFail($id);
        $ocultarVolver = $soloConsulta;
        $puedeActualizar = can('actualizar-concepto-solicitud-pago', false);

        return view('solicitudpago.concepto_solicitudpago.editar', array_merge(
            $this->datosFormulario(),
            compact('data', 'soloConsulta', 'ocultarVolver', 'puedeActualizar')
        ));
    }

    public function actualizar(ValidacionConcepto_Solicitudpago $request, $id)
    {
        can('actualizar-concepto-solicitud-pago');

        try {
            $this->repository->update($request->validated(), $id);

            if ($request->input('origen') === 'modal_consulta' || $request->input('vista') === 'consulta') {
                return redirect()
                    ->route('editar_concepto_solicitudpago', [
                        'id' => $id,
                        'origen' => 'modal_consulta',
                        'vista' => 'consulta',
                    ])
                    ->with('mensaje', 'Concepto actualizado con éxito');
            }

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

    public function consultaConcepto(Request $request)
    {
        if (! $this->puedeConsultarConceptoOperativo()) {
            abort(403);
        }

        $consulta = (string) ($request->input('consulta') ?? '');
        $sectorId = (int) $request->input('sector_solicitudpago_id', 0);
        $sectorId = $sectorId > 0 ? $sectorId : null;

        $data = $this->repository->listadoOperativoParaConsulta($consulta, $sectorId);
        $puedeAbrirAbm = can('editar-concepto-solicitud-pago', false)
            || can('listar-concepto-solicitud-pago', false);

        $output = ['data' => ''];
        if ($data->isEmpty()) {
            $output['data'] = '<tr><td colspan="5">Sin resultados</td></tr>';
        } else {
            foreach ($data as $row) {
                $sector = $row->sectores;
                $output['data'] .= '<tr>';
                $output['data'] .= '<td class="concepto_id">'.e($row->id).'</td>';
                $output['data'] .= '<td class="codigoconcepto">'.e($row->codigo).'</td>';
                $output['data'] .= '<td class="nombreconcepto">'.e($row->nombre).'</td>';
                $output['data'] .= '<td class="sectorconcepto">'.e(
                    $sector
                        ? trim(($sector->codigo ?? '').' — '.($sector->nombre ?? ''), ' —')
                        : '—'
                ).'</td>';
                $output['data'] .= '<td class="sector_solicitudpago_id d-none">'.e((string) ($row->sector_solicitudpago_id ?? '')).'</td>';
                $output['data'] .= '<td class="forma_pago d-none">'.e((string) ($row->forma_pago ?? '')).'</td>';
                $output['data'] .= '<td class="text-nowrap">';
                $output['data'] .= '<a class="btn btn-warning btn-sm eligeconsultaconcepto_solicitudpago">Elegir</a>';
                if ($puedeAbrirAbm) {
                    $url = route('editar_concepto_solicitudpago', [
                        'id' => $row->id,
                        'origen' => 'modal_consulta',
                        'vista' => 'consulta',
                    ]);
                    $output['data'] .= ' <a class="btn btn-info btn-sm" href="'.e($url).'" target="_blank" rel="noopener">Consultar</a>';
                }
                $output['data'] .= '</td>';
                $output['data'] .= '</tr>';
            }
        }

        return response()->json($output);
    }

    public function leeUnConceptoPorCodigo(Request $request, $codigo)
    {
        if (! $this->puedeConsultarConceptoOperativo()) {
            abort(403);
        }

        $codigoInt = (int) $codigo;
        if ($codigoInt <= 0) {
            return response()->json(['error' => 'Concepto no encontrado'], 404);
        }

        $sectorId = (int) $request->input('sector_solicitudpago_id', 0);
        $sectorId = $sectorId > 0 ? $sectorId : null;
        $empresaId = (int) $request->input('empresa_id', 0);
        $empresaId = $empresaId > 0 ? $empresaId : null;
        $concepto = $this->repository->findOperativoPorCodigo($codigoInt, $sectorId);

        if ($concepto === null) {
            return response()->json(['error' => 'Concepto no encontrado'], 404);
        }

        return response()->json($this->payloadConceptoOperativo($concepto, $empresaId));
    }

    public function leeConcepto(Request $request, $id)
    {
        if (! $this->puedeConsultarConceptoOperativo()) {
            abort(403);
        }

        try {
            $concepto = $this->repository->find((int) $id);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['error' => 'Concepto no encontrado'], 404);
        }

        if ($concepto->estado === ConceptoSolicitudpagoEstados::SUSPENDIDO) {
            return response()->json(['error' => 'Concepto no encontrado'], 404);
        }

        $empresaId = (int) $request->input('empresa_id', 0);
        $empresaId = $empresaId > 0 ? $empresaId : null;

        return response()->json($this->payloadConceptoOperativo($concepto, $empresaId));
    }

    public function cuentasTemplate(Request $request, $id)
    {
        if (! $this->puedeConsultarConceptoOperativo()) {
            abort(403);
        }

        $empresaId = (int) $request->input('empresa_id', 0);
        $empresaId = $empresaId > 0 ? $empresaId : null;

        return response()->json([
            'cuentas' => $this->repository->cuentasTemplateParaSolicitud((int) $id, $empresaId),
        ]);
    }

    private function puedeConsultarConceptoOperativo(): bool
    {
        return can('listar-concepto-solicitud-pago', false)
            || can('crear-concepto-solicitud-pago', false)
            || can('editar-concepto-solicitud-pago', false)
            || can('listar-solicitud-pago', false)
            || can('crear-solicitud-pago', false)
            || can('editar-solicitud-pago', false)
            || can('actualizar-solicitud-pago', false);
    }

    /**
     * @param  \App\Models\Solicitudpago\Concepto_Solicitudpago  $concepto
     * @return array<string, mixed>
     */
    private function payloadConceptoOperativo($concepto, ?int $empresaId = null): array
    {
        $sector = $concepto->sectores;

        return [
            'id' => (int) $concepto->id,
            'codigo' => (int) $concepto->codigo,
            'nombre' => (string) $concepto->nombre,
            'forma_pago' => (string) ($concepto->forma_pago ?? ''),
            'sector_solicitudpago_id' => $concepto->sector_solicitudpago_id
                ? (int) $concepto->sector_solicitudpago_id
                : null,
            'sector_codigo' => $sector?->codigo,
            'sector_nombre' => $sector?->nombre,
            'cuentas' => $this->repository->cuentasTemplateParaSolicitud((int) $concepto->id, $empresaId),
        ];
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
