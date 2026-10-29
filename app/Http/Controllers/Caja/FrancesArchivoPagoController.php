<?php

declare(strict_types=1);

namespace App\Http\Controllers\Caja;

use App\Http\Controllers\Controller;
use App\Models\Caja\Cuentacaja;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Services\Caja\FrancesArchivoPagoService;
use App\Support\Caja\Frances\FrancesArchivoPagoCuentaSupport;
use App\Support\Caja\Frances\FrancesArchivoPagoFiltros;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Reportes\ReportePreferenciasUsuario;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response as ResponseFacade;
use Illuminate\View\View;

/**
 * Exportación de pagos al Banco Francés (p-enviafrances.c). Solo Crown.
 */
class FrancesArchivoPagoController extends Controller
{
    private const PERMISO = 'generar-archivo-pago-frances';

    private const PREFERENCIAS = 'frances_archivo_pago';

    public function __construct(
        private readonly FrancesArchivoPagoService $service,
        private readonly EmpresaRepositoryInterface $empresaRepository,
    ) {
        $this->middleware('auth');
    }

    public function index(Request $request): View
    {
        $this->asegurar();

        $empresaQuery = $this->empresaRepository->allFiltrado();
        $filtros = FrancesArchivoPagoFiltros::resolverDesdeRequest($request);
        $filtros = $this->aplicarPreferencias($request, $filtros, $empresaQuery);
        $cuenta = $this->cuentaSeleccionada($filtros);
        if ($cuenta === null && ! $request->has('cuentacaja_id')) {
            $cuenta = FrancesArchivoPagoCuentaSupport::porDefecto((int) ($filtros['empresa_id'] ?? 0));
        }
        $filtros['cuentacaja_id'] = $cuenta ? (int) $cuenta->id : 0;

        $consultado = $request->boolean('consultar')
            && FrancesArchivoPagoFiltros::tieneCriteriosAplicados($filtros);

        $resultado = null;
        if ($consultado) {
            ini_set('memory_limit', '512M');
            ini_set('max_execution_time', '180');
            $this->persistir($filtros);
            $resultado = $this->service->generar($filtros);
        }

        $filtrosQuery = FrancesArchivoPagoFiltros::paraQueryString($filtros);
        if ($consultado) {
            $filtrosQuery['consultar'] = 1;
        }

        return view('caja.frances.archivo_pago', [
            'empresa_query' => $empresaQuery,
            'filtros' => $filtros,
            'filtrosQuery' => $filtrosQuery,
            'consultado' => $consultado,
            'resultado' => $resultado,
            'cuenta' => $cuenta,
        ]);
    }

    public function descargar(Request $request): \Illuminate\Http\Response|RedirectResponse
    {
        $this->asegurar();

        $filtros = FrancesArchivoPagoFiltros::resolverDesdeRequest($request);
        if (! FrancesArchivoPagoFiltros::tieneCriteriosAplicados($filtros)) {
            return redirect()->route('frances_archivo_pago')
                ->with('mensaje_error', 'Indique empresa, fechas y cuenta de caja.');
        }

        ini_set('memory_limit', '512M');
        ini_set('max_execution_time', '180');
        $resultado = $this->service->generar($filtros);
        if (($resultado['cantidad'] ?? 0) <= 0 || ($resultado['archivo'] ?? '') === '') {
            return redirect()
                ->route('frances_archivo_pago', array_merge(
                    FrancesArchivoPagoFiltros::paraQueryString($filtros),
                    ['consultar' => 1]
                ))
                ->with('mensaje_error', $resultado['mensaje'] ?? 'No hay pagos para exportar.');
        }

        $codigo = preg_replace('/[^\w.-]+/', '-', (string) ($resultado['cuentacaja_codigo'] ?? '')) ?? '';
        $nombre = sprintf(
            'frances_%s_%s.csv',
            str_replace('-', '', (string) $filtros['fecha_desde']),
            $codigo !== '' ? $codigo : 'cuenta'
        );

        return ResponseFacade::make((string) $resultado['archivo'], 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$nombre.'"',
        ]);
    }

    private function asegurar(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            abort(404);
        }
        can(self::PERMISO);
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @param  \Illuminate\Support\Collection<int, mixed>  $empresaQuery
     * @return array<string, mixed>
     */
    private function aplicarPreferencias(Request $request, array $filtros, $empresaQuery): array
    {
        if (! $request->has('empresa_id')) {
            $empresaId = ReportePreferenciasUsuario::leerEmpresaId(self::PREFERENCIAS);
            if ($empresaId !== null) {
                $filtros['empresa_id'] = $empresaId;
            } elseif ($empresaQuery->count() === 1) {
                $filtros['empresa_id'] = (int) $empresaQuery->first()->id;
            }
        }
        if (! $request->has('cuentacaja_id')) {
            $cuentaId = (int) ReportePreferenciasUsuario::leerString(self::PREFERENCIAS, 'cuentacaja_id');
            if ($cuentaId <= 0) {
                $defecto = FrancesArchivoPagoCuentaSupport::porDefecto((int) ($filtros['empresa_id'] ?? 0));
                $cuentaId = $defecto ? (int) $defecto->id : 0;
            }
            $filtros['cuentacaja_id'] = $cuentaId;
        }
        if (! $request->has('fecha_desde')) {
            $desde = ReportePreferenciasUsuario::leerString(self::PREFERENCIAS, 'fecha_desde');
            if ($desde !== '') {
                $filtros['fecha_desde'] = $desde;
            }
        }
        if (! $request->has('fecha_hasta')) {
            $hasta = ReportePreferenciasUsuario::leerString(self::PREFERENCIAS, 'fecha_hasta');
            if ($hasta !== '') {
                $filtros['fecha_hasta'] = $hasta;
            }
        }

        return $filtros;
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private function cuentaSeleccionada(array $filtros): ?Cuentacaja
    {
        $id = (int) ($filtros['cuentacaja_id'] ?? 0);
        if ($id <= 0) {
            return null;
        }

        $cuenta = Cuentacaja::query()->find($id);
        if ($cuenta === null) {
            return null;
        }
        $empresaId = (int) ($filtros['empresa_id'] ?? 0);
        if ($empresaId > 0 && ! Cuentacaja::existeParaEmpresa($id, $empresaId)) {
            return null;
        }

        return $cuenta;
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private function persistir(array $filtros): void
    {
        ReportePreferenciasUsuario::persistir(self::PREFERENCIAS, [
            'empresa_id' => (int) ($filtros['empresa_id'] ?? 0),
        ]);
        ReportePreferenciasUsuario::persistirString(
            self::PREFERENCIAS,
            'cuentacaja_id',
            (string) max(0, (int) ($filtros['cuentacaja_id'] ?? 0))
        );
        ReportePreferenciasUsuario::persistirString(
            self::PREFERENCIAS,
            'fecha_desde',
            (string) ($filtros['fecha_desde'] ?? '')
        );
        ReportePreferenciasUsuario::persistirString(
            self::PREFERENCIAS,
            'fecha_hasta',
            (string) ($filtros['fecha_hasta'] ?? '')
        );
    }
}
