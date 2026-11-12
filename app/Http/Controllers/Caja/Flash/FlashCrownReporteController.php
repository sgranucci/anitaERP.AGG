<?php

namespace App\Http\Controllers\Caja\Flash;

use App\Exports\Caja\Flash\FlashCrownInformeExport;
use App\Exports\Caja\Flash\FlashCrownPeriodoExport;
use App\Http\Controllers\Controller;
use App\Mail\Caja\Flash\FlashCrownInforme;
use App\Models\Caja\Flash\FlashCrown;
use App\Models\Caja\Flash\FlashCrownSuscripcion;
use App\Services\Caja\Flash\FlashCrownDistribucionService;
use App\Support\Caja\Flash\FlashCrownInformeDestinatariosSupport;
use App\Support\Caja\Flash\FlashCrownInformeSupport;
use App\Support\Caja\Flash\FlashCrownPerfilVistaSupport;
use App\Support\Caja\Flash\FlashCrownSuscripcionSupport;
use App\Support\Caja\TesoreriaCrownSalaSupport;
use App\Support\Configuracion\EntornoEmpresaSupport;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class FlashCrownReporteController extends Controller
{
    public function __construct(
        private readonly FlashCrownSuscripcionSupport $suscripcionSupport,
        private readonly FlashCrownDistribucionService $distribucionService,
        private readonly FlashCrownInformeSupport $informeSupport,
        private readonly FlashCrownInformeDestinatariosSupport $destinatariosSupport,
    ) {}

    public function index(Request $request)
    {
        $this->assertCrown();
        can('listar-flash-reporte-crown');

        $fecha = $this->fechaPedida($request);
        $formato = FlashCrownInformeSupport::normalizarFormato($request->input('formato'));
        $destinatarios = $this->textoDestinatarios($request);
        $informe = null;
        if ($request->boolean('consultar')) {
            ini_set('memory_limit', '512M');
            $informe = $this->informeSupport->armar($formato, $fecha);
        }

        return view('caja.flash_crown.reporte.index', [
            'fecha' => $fecha->toDateString(),
            'formato' => $formato,
            'formatos' => FlashCrownInformeSupport::formatos(),
            'destinatarios' => $destinatarios,
            'informe' => $informe,
            'puedeEnviar' => can('administrar-flash-reporte-crown', false),
        ]);
    }

    public function enviar(Request $request)
    {
        $this->assertCrown();
        can('administrar-flash-reporte-crown');

        $fecha = $this->fechaPedida($request);
        $formato = FlashCrownInformeSupport::normalizarFormato($request->input('formato'));
        $texto = $this->textoDestinatarios($request);
        $destinatarios = $this->destinatariosSupport->parsear($texto);
        if ($destinatarios === []) {
            return redirect()
                ->route('flash_reporte_crown', $this->queryInforme($fecha, $formato))
                ->with('mensaje_error', 'No hay mails válidos para enviar.');
        }

        ini_set('memory_limit', '512M');
        $informe = $this->informeSupport->armar($formato, $fecha);
        $this->destinatariosSupport->guardar($texto, Auth::id() ? (int) Auth::id() : null);

        try {
            Mail::to($destinatarios)->send(new FlashCrownInforme($informe, $formato));
        } catch (\Throwable $e) {
            return redirect()
                ->route('flash_reporte_crown', $this->queryInforme($fecha, $formato, true))
                ->with('mensaje_error', 'No se pudo enviar el mail: '.$e->getMessage());
        }

        return redirect()
            ->route('flash_reporte_crown', $this->queryInforme($fecha, $formato, true))
            ->with('mensaje', 'Informe enviado a '.implode(', ', $destinatarios).'.');
    }

    public function guardarDestinatarios(Request $request)
    {
        $this->assertCrown();
        can('administrar-flash-reporte-crown');

        $texto = trim((string) $request->input('destinatarios', ''));
        if ($this->destinatariosSupport->parsear($texto) === []) {
            return redirect()
                ->route('flash_reporte_crown')
                ->with('mensaje_error', 'No hay mails válidos para guardar.');
        }

        $this->destinatariosSupport->guardar($texto, Auth::id() ? (int) Auth::id() : null);

        return redirect()
            ->route('flash_reporte_crown', [
                'fecha' => $this->fechaPedida($request)->toDateString(),
                'formato' => FlashCrownInformeSupport::normalizarFormato($request->input('formato')),
            ])
            ->with('mensaje', 'Destinatarios guardados.');
    }

    public function exportarInforme(Request $request): BinaryFileResponse
    {
        $this->assertCrown();
        can('exportar-flash-reporte-crown');
        ini_set('memory_limit', '512M');
        ini_set('max_execution_time', '300');

        $fecha = $this->fechaPedida($request);
        $formato = FlashCrownInformeSupport::normalizarFormato($request->input('formato'));
        $informe = $this->informeSupport->armar($formato, $fecha);

        $nombre = match ($formato) {
            FlashCrownInformeSupport::FORMATO_GAMING => 'gaming_revenues_'.$fecha->format('Ymd').'.xlsx',
            FlashCrownInformeSupport::FORMATO_DAILY => 'daily_flash_'.$fecha->format('Ymd').'.xlsx',
            default => 'flash_crown_'.$fecha->format('Ymd').'.xlsx',
        };

        return Excel::download(new FlashCrownInformeExport($informe, $formato), $nombre);
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
            ->whereIn('sala_id', TesoreriaCrownSalaSupport::idsOperativos())
            ->orderBy('fecha')
            ->get();
        $filas = TesoreriaCrownSalaSupport::ordenarColeccionPorFechaYSala($filas);

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

    private function fechaPedida(Request $request): Carbon
    {
        $raw = trim((string) $request->input('fecha', ''));
        if ($raw === '' || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
            return now()->subDay()->startOfDay();
        }

        return Carbon::parse($raw)->startOfDay();
    }

    private function textoDestinatarios(Request $request): string
    {
        if ($request->exists('destinatarios')) {
            return (string) $request->input('destinatarios');
        }

        return $this->destinatariosSupport->textoActual();
    }

    /**
     * @return array<string, mixed>
     */
    private function queryInforme(Carbon $fecha, string $formato, bool $consultar = false): array
    {
        $query = [
            'fecha' => $fecha->toDateString(),
            'formato' => $formato,
        ];
        if ($consultar) {
            $query['consultar'] = 1;
        }

        return $query;
    }

    private function assertCrown(): void
    {
        abort_unless(EntornoEmpresaSupport::esCrown(), 404);
    }
}
