<?php

namespace App\Http\Controllers\Caja\Flash;

use App\Exports\Caja\Flash\FlashCrownPeriodoExport;
use App\Http\Controllers\Controller;
use App\Models\Caja\Flash\FlashCrown;
use App\Models\Caja\Flash\FlashCrownSuscripcion;
use App\Models\Configuracion\Sala;
use App\Services\Caja\Flash\FlashCrownDistribucionService;
use App\Support\Caja\Flash\FlashCrownPerfilVistaSupport;
use App\Support\Caja\Flash\FlashCrownSuscripcionSupport;
use App\Support\Configuracion\EntornoEmpresaSupport;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;

class FlashCrownReporteController extends Controller
{
    public function __construct(
        private readonly FlashCrownSuscripcionSupport $suscripcionSupport,
        private readonly FlashCrownDistribucionService $distribucionService,
    ) {}

    public function index(Request $request)
    {
        $this->assertCrown();
        can('listar-flash-reporte-crown');

        $desde = $request->input('desde', now()->startOfMonth()->toDateString());
        $hasta = $request->input('hasta', now()->subDay()->toDateString());
        $perfil = FlashCrownPerfilVistaSupport::normalizar($request->input('perfil_vista'));

        $editando = null;
        if ($request->filled('editar')) {
            $editando = FlashCrownSuscripcion::query()->find((int) $request->input('editar'));
        }

        return view('caja.flash_crown.reporte.index', [
            'desde' => $desde,
            'hasta' => $hasta,
            'perfil_vista' => $perfil,
            'perfiles_vista' => FlashCrownPerfilVistaSupport::etiquetas(),
            'perfilesVistaDesc' => FlashCrownPerfilVistaSupport::descripciones(),
            'suscripciones' => $this->suscripcionSupport->listar(),
            'suscripcion_editar' => $editando,
            'periodicidades' => FlashCrownSuscripcion::periodicidades(),
            'periodos_relativos' => FlashCrownSuscripcion::periodosRelativos(),
            'dias_semana' => FlashCrownSuscripcion::diasSemana(),
            'puedeAdministrar' => can('administrar-flash-reporte-crown', false),
        ]);
    }

    public function exportar(Request $request)
    {
        $this->assertCrown();
        can('exportar-flash-reporte-crown');
        ini_set('memory_limit', '512M');

        $desde = Carbon::parse($request->input('desde', now()->startOfMonth()))->startOfDay();
        $hasta = Carbon::parse($request->input('hasta', now()->subDay()))->startOfDay();
        $perfil = FlashCrownPerfilVistaSupport::normalizar($request->input('perfil_vista'));
        $soloValidados = $request->boolean('solo_validados', true);

        $filas = FlashCrown::query()
            ->with(['sala', 'empresa'])
            ->whereDate('fecha', '>=', $desde->toDateString())
            ->whereDate('fecha', '<=', $hasta->toDateString())
            ->when($soloValidados, fn ($q) => $q->where('validado', true))
            ->whereIn('sala_id', Sala::query()->where('codigo', '!=', 'AC')->pluck('id'))
            ->orderBy('fecha')
            ->orderBy('sala_id')
            ->get();

        $nombre = sprintf('flash_crown_%s_%s.xlsx', $desde->format('Ymd'), $hasta->format('Ymd'));

        return Excel::download(new FlashCrownPeriodoExport($filas, $desde, $hasta, $perfil), $nombre);
    }

    public function guardarSuscripcion(Request $request)
    {
        $this->assertCrown();
        can('administrar-flash-reporte-crown');

        $this->suscripcionSupport->crear($request->all(), Auth::id());

        return redirect()->route('flash_reporte_crown')->with('mensaje', 'Suscripción creada.');
    }

    public function actualizarSuscripcion(Request $request, $id)
    {
        $this->assertCrown();
        can('administrar-flash-reporte-crown');

        $this->suscripcionSupport->actualizar((int) $id, $request->all());

        return redirect()->route('flash_reporte_crown')->with('mensaje', 'Suscripción actualizada.');
    }

    public function eliminarSuscripcion($id)
    {
        $this->assertCrown();
        can('administrar-flash-reporte-crown');

        $this->suscripcionSupport->eliminar((int) $id);

        return redirect()->route('flash_reporte_crown')->with('mensaje', 'Suscripción eliminada.');
    }

    public function probarSuscripcion(Request $request, $id)
    {
        $this->assertCrown();
        can('administrar-flash-reporte-crown');

        $suscripcion = FlashCrownSuscripcion::query()->findOrFail($id);
        $dry = ! $request->boolean('enviar');
        $resultado = $this->distribucionService->enviar($suscripcion, $dry);

        if ($resultado['estado'] === FlashCrownSuscripcion::ESTADO_OK && ! $dry) {
            $this->suscripcionSupport->marcarResultado($suscripcion, $resultado['estado'], $resultado['mensaje']);
        }

        return redirect()->route('flash_reporte_crown')
            ->with($resultado['estado'] === FlashCrownSuscripcion::ESTADO_ERROR ? 'mensaje_error' : 'mensaje', $resultado['mensaje']);
    }

    private function assertCrown(): void
    {
        abort_unless(EntornoEmpresaSupport::esCrown(), 404);
    }
}
