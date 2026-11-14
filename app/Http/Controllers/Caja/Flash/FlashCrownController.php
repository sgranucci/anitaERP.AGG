<?php

namespace App\Http\Controllers\Caja\Flash;

use App\Exports\Caja\Flash\FlashCrownDiaExport;
use App\Http\Controllers\Controller;
use App\Models\Caja\Flash\FlashCrown;
use App\Services\Caja\Flash\FlashCrownService;
use App\Support\Caja\Flash\FlashCrownCalculoSupport;
use App\Support\Caja\Flash\FlashCrownListadoFiltros;
use App\Support\Caja\Flash\FlashCrownNumeroSupport;
use App\Support\Caja\TesoreriaCrownSalaSupport;
use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;
use PDF;

class FlashCrownController extends Controller
{
    public function __construct(
        private readonly FlashCrownService $service,
    ) {}

    public function index(Request $request)
    {
        $this->assertCrown();
        can('listar-flash-crown');

        $filtros = FlashCrownListadoFiltros::resolverDesdeRequest($request);
        $filtrosQuery = FlashCrownListadoFiltros::paraQueryString($filtros);
        $salas = TesoreriaCrownSalaSupport::salas();
        $salaId = (int) ($filtros['sala_id'] ?? 0);
        $sala = $salaId > 0
            ? ($salas->firstWhere('id', $salaId) ?? abort(404))
            : null;

        $filas = collect();
        $consolidado = null;
        $onlineEmpresa = null;
        $avisos = [];

        if ($filtros['consultar']) {
            // Fallback Anita del día: varios Informix + JSON. 512M no alcanza si el bridge
            // trae historial; el WHERE por fecha mitiga, esto es red de seguridad.
            ini_set('memory_limit', '1024M');
            ini_set('max_execution_time', '600');

            $fecha = (string) $filtros['fecha'];
            $salaIds = $sala
                ? [(int) $sala->id]
                : $salas->pluck('id')->map(fn ($id) => (int) $id)->all();

            // Un solo plan Anita para todas las salas sin dato ERP usable.
            $avisos = array_merge($avisos, $this->service->asegurarListadoDesdeAnita($salaIds, $fecha));

            foreach ($salaIds as $idSala) {
                try {
                    $filas->push($this->service->obtenerParaConsulta($idSala, $fecha));
                } catch (\Throwable $e) {
                    $avisos[] = 'Sala '.$idSala.': '.$e->getMessage();
                }
            }

            $onlineEmpresa = FlashCrownCalculoSupport::valoresOnlineGlobal($fecha);

            if ($filas->count() > 1) {
                $consolidado = $this->service->consolidar($filas->all());
                $consolidado['_metricas'] = FlashCrownCalculoSupport::metricasDerivadas($consolidado);
            } elseif ($filas->count() === 1) {
                // Una sala: métricas de juego de esa sala + online empresa aparte.
                $base = FlashCrownCalculoSupport::baseVistaDesdeFlash($filas->first());
                $consolidado = array_merge($base, $onlineEmpresa);
                $consolidado['_metricas'] = FlashCrownCalculoSupport::metricasDerivadas($consolidado);
                $consolidado['_solo_una_sala'] = true;
            }
        }

        return view('caja.flash_crown.index', [
            'filtros' => $filtros,
            'filtrosQuery' => $filtrosQuery,
            'salas' => $salas,
            'sala' => $sala,
            'filas' => $filas,
            'consolidado' => $consolidado,
            'onlineEmpresa' => $onlineEmpresa,
            'avisos' => $avisos,
            'puedeValidar' => can('validar-flash-crown', false),
            'puedeEditar' => can('editar-flash-crown', false),
        ]);
    }

    public function editar(Request $request, $id)
    {
        $this->assertCrown();
        can('editar-flash-crown');

        $flash = FlashCrown::query()->with(['sala', 'empresa'])->findOrFail($id);
        $live = FlashCrownCalculoSupport::deSalaFecha((int) $flash->sala_id, $flash->fecha->format('Y-m-d'));
        $metricas = FlashCrownCalculoSupport::metricasDerivadas(
            FlashCrownCalculoSupport::baseVistaDesdeFlash($flash)
        );
        $anterior = FlashCrownCalculoSupport::combinedAnteriorAnio(
            (int) $flash->sala_id,
            $flash->fecha->format('Y-m-d')
        );
        $metricas['combined_anterior'] = $anterior;
        $metricas['variacion_anterior'] = abs($anterior) > 0.001
            ? round(($metricas['combined'] - $anterior) / $anterior * 100, 2)
            : 0.0;

        return view('caja.flash_crown.editar', [
            'flash' => $flash,
            'live' => $live,
            'metricas' => $metricas,
            'overrides' => is_array($flash->overrides_json) ? $flash->overrides_json : [],
            'retorno' => $request->only(['fecha', 'sala_id', 'consultar']),
        ]);
    }

    public function actualizar(Request $request, $id)
    {
        $this->assertCrown();
        can('actualizar-flash-crown');

        $flash = FlashCrown::query()->findOrFail($id);
        if ($flash->estaValidado() && ! can('validar-flash-crown', false)) {
            return redirect()->route('editar_flash_crown', $id)
                ->with('mensaje_error', 'El flash está validado; no se puede editar.');
        }

        $overrides = [];
        foreach (FlashCrown::camposNumericos() as $campo) {
            if ($request->filled('override_'.$campo) || $request->boolean('marcar_'.$campo)) {
                $raw = $request->input($campo);
                $overrides[$campo] = in_array($campo, ['att', 'cant_mesas', 'cant_slots', 'cant_rul_el'], true)
                    ? FlashCrownNumeroSupport::parsearEntero($raw)
                    : FlashCrownNumeroSupport::parsear($raw);
            }
        }
        if ($request->filled('comentario')) {
            $overrides['comentario'] = (string) $request->input('comentario');
        }

        if ($flash->estaValidado()) {
            $this->service->quitarValidacion($flash);
        }

        $this->service->sincronizar(
            (int) $flash->sala_id,
            $flash->fecha->format('Y-m-d'),
            $overrides,
            persistir: true,
            forzar: true,
            reemplazarOverrides: true,
        );

        return redirect()
            ->route('flash_crown', array_filter([
                'fecha' => $flash->fecha->format('Y-m-d'),
                'consultar' => 1,
            ]))
            ->with('mensaje', 'Flash Crown actualizado.');
    }

    public function recalcular(Request $request, $id)
    {
        $this->assertCrown();
        can('actualizar-flash-crown');

        $flash = FlashCrown::query()->findOrFail($id);
        if ($flash->estaValidado()) {
            return redirect()->route('editar_flash_crown', $id)
                ->with('mensaje_error', 'Quitá la validación antes de recalcular.');
        }

        // Recalcula limpio: borra overrides.
        $flash->overrides_json = null;
        $flash->save();
        $this->service->sincronizar(
            (int) $flash->sala_id,
            $flash->fecha->format('Y-m-d'),
            [],
            persistir: true,
            forzar: true,
        );

        return redirect()->route('editar_flash_crown', $id)
            ->with('mensaje', 'Flash recalculado desde Tesorería / Resultado online.');
    }

    public function validar(Request $request, $id)
    {
        $this->assertCrown();
        can('validar-flash-crown');

        $flash = FlashCrown::query()->findOrFail($id);
        $this->service->validar($flash, (int) Auth::id());

        return redirect()
            ->route('flash_crown', ['fecha' => $flash->fecha->format('Y-m-d'), 'consultar' => 1])
            ->with('mensaje', 'Flash Crown validado. Ya puede enviarse por mail.');
    }

    public function quitarValidacion(Request $request, $id)
    {
        $this->assertCrown();
        can('validar-flash-crown');

        $flash = FlashCrown::query()->findOrFail($id);
        $this->service->quitarValidacion($flash);

        return redirect()
            ->route('flash_crown', ['fecha' => $flash->fecha->format('Y-m-d'), 'consultar' => 1])
            ->with('mensaje', 'Validación quitada.');
    }

    public function listar(Request $request, $formato = null)
    {
        $this->assertCrown();
        can('exportar-flash-crown');
        ini_set('memory_limit', '1024M');
        ini_set('max_execution_time', '600');

        $filtros = FlashCrownListadoFiltros::resolverDesdeRequest($request);
        $salas = TesoreriaCrownSalaSupport::salas();
        $salaId = (int) ($filtros['sala_id'] ?? 0);
        $salaIds = $salaId > 0
            ? [$salaId]
            : $salas->pluck('id')->map(fn ($id) => (int) $id)->all();

        // Misma fuente que el index (fallback Anita en lote si falta dato ERP).
        $this->service->asegurarListadoDesdeAnita($salaIds, (string) $filtros['fecha']);
        $filas = [];
        foreach ($salaIds as $idSala) {
            $filas[] = $this->service->obtenerParaConsulta($idSala, (string) $filtros['fecha']);
        }

        $formato = strtoupper((string) $formato);
        if (! in_array($formato, ['PDF', 'EXCEL', 'CSV'], true)) {
            return redirect()->route('flash_crown', FlashCrownListadoFiltros::paraQueryString($filtros));
        }

        $titulo = 'Flash Crown '.$filtros['fecha'];
        if ($formato === 'PDF') {
            $pdf = PDF::loadView('caja.flash_crown.listado', [
                'filas' => $filas,
                'fecha' => $filtros['fecha'],
                'titulo' => $titulo,
                'consolidado' => count($filas) > 1 ? $this->service->consolidar($filas) : null,
            ])->setPaper('legal', 'landscape');

            return $pdf->download('flash_crown_'.$filtros['fecha'].'.pdf');
        }

        return Excel::download(
            new FlashCrownDiaExport($filas, (string) $filtros['fecha'], $formato === 'CSV'),
            'flash_crown_'.$filtros['fecha'].($formato === 'CSV' ? '.csv' : '.xlsx')
        );
    }

    private function assertCrown(): void
    {
        abort_unless(EntornoEmpresaSupport::esCrown(), 404);
    }

}
