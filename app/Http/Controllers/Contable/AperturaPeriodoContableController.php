<?php

namespace App\Http\Controllers\Contable;

use App\Http\Controllers\Controller;
use App\Models\Seguridad\Usuario;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Services\Contable\AperturaPeriodoContableService;
use App\Support\Contable\AperturaPeriodoContablePermiso;
use App\Support\Contable\PeriodoContableCierreSupport;
use Illuminate\Http\Request;
use InvalidArgumentException;

class AperturaPeriodoContableController extends Controller
{
    public function __construct(
        private readonly AperturaPeriodoContableService $aperturaService,
        private readonly EmpresaRepositoryInterface $empresaRepository,
    ) {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        can('listar-apertura-periodo-contable');

        $empresaQuery = $this->empresaRepository->allFiltrado();
        $empresaId = (int) $request->input('empresa_id', 0);
        $estado = $request->input('estado');

        if ($empresaId <= 0 && $empresaQuery->count() === 1) {
            $empresaId = (int) $empresaQuery->first()->id;
        }

        $aperturas = $this->aperturaService->listar(
            $empresaId > 0 ? $empresaId : null,
            is_string($estado) ? $estado : null
        );
        $aperturas->appends(array_filter([
            'empresa_id' => $empresaId > 0 ? $empresaId : null,
            'estado' => $estado,
        ]));

        return view('contable.apertura_periodo.index', [
            'empresa_query' => $empresaQuery,
            'empresa_id' => $empresaId,
            'estado_filtro' => $estado,
            'aperturas' => $aperturas,
            'alcances' => PeriodoContableCierreSupport::alcancesDisponibles(),
            'jerarquia_alcances' => PeriodoContableCierreSupport::jerarquiaAgenda(),
            'usuarios' => $this->usuariosParaSelect(),
            'puede_solicitar' => can('solicitar-apertura-periodo-contable', false),
            'puede_aprobar' => can('aprobar-apertura-periodo-contable', false),
            'puede_habilitar' => can(AperturaPeriodoContablePermiso::SLUG_HABILITAR, false),
            'puede_gestionar' => AperturaPeriodoContablePermiso::puedeGestionarSolicitudes(),
            'puede_revocar' => can('revocar-apertura-periodo-contable', false),
        ]);
    }

    public function solicitar(Request $request)
    {
        can('solicitar-apertura-periodo-contable');

        $request->validate([
            'empresa_id' => 'required|integer|min:1',
            'usuario_habilitado_id' => 'required|integer|min:1',
            'fecha_operacion_desde' => 'required|date',
            'fecha_operacion_hasta' => 'required|date|after_or_equal:fecha_operacion_desde',
            'alcance' => 'required|string|max:32',
            'duracion_cantidad' => 'required|integer|min:1|max:720',
            'duracion_unidad' => 'required|in:horas,dias',
            'motivo' => 'required|string|min:10|max:2000',
        ]);

        try {
            $this->aperturaService->solicitar($request->all());
        } catch (InvalidArgumentException $e) {
            return redirect()
                ->route('apertura_periodo_contable', ['empresa_id' => $request->input('empresa_id')])
                ->with('error', $e->getMessage())
                ->withInput();
        }

        return redirect()
            ->route('apertura_periodo_contable', ['empresa_id' => $request->input('empresa_id')])
            ->with('mensaje', 'Solicitud de apertura registrada. Aguarda aprobación de contaduría.');
    }

    public function aprobar(Request $request, int $id)
    {
        if (! AperturaPeriodoContablePermiso::puedeGestionarSolicitudes()) {
            abort(403);
        }

        try {
            $this->aperturaService->aprobar($id, $request->input('observacion'));
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('mensaje', 'Apertura habilitada. Se envió aviso al usuario.');
    }

    public function habilitarDesdeAviso(Request $request, int $id)
    {
        if (! $request->hasValidSignature()) {
            return redirect()
                ->route('apertura_periodo_contable')
                ->with('error', 'El enlace de habilitación es inválido o expiró. Ingrese al módulo de aperturas para gestionar la solicitud.');
        }

        if (! AperturaPeriodoContablePermiso::puedeGestionarSolicitudes()) {
            abort(403, 'No tiene permiso para habilitar aperturas de período contable.');
        }

        try {
            $this->aperturaService->aprobar($id, $request->input('observacion'));
        } catch (InvalidArgumentException $e) {
            return redirect()
                ->route('apertura_periodo_contable')
                ->with('error', $e->getMessage());
        }

        return redirect()
            ->route('apertura_periodo_contable')
            ->with('mensaje', 'Apertura habilitada correctamente desde el aviso por correo.');
    }

    public function rechazar(Request $request, int $id)
    {
        can('aprobar-apertura-periodo-contable');

        try {
            $this->aperturaService->rechazar($id, $request->input('observacion'));
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('mensaje', 'Solicitud rechazada.');
    }

    public function revocar(Request $request, int $id)
    {
        can('revocar-apertura-periodo-contable');

        try {
            $this->aperturaService->revocar($id, $request->input('observacion'));
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('mensaje', 'Apertura revocada.');
    }

    /** @return \Illuminate\Support\Collection<int, Usuario> */
    private function usuariosParaSelect()
    {
        return Usuario::query()
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'usuario', 'email']);
    }
}
