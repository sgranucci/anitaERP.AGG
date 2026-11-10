<?php

namespace App\Http\Controllers\Ventas\GastronomiaCrown;

use App\Http\Controllers\Controller;
use App\Models\Configuracion\Sala;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Services\Ventas\GastronomiaCrown\GastronomiaCrownSaneamientoService;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Ventas\UsuarioSalaAsignadaSupport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

class GastronomiaCrownSaneamientoController extends Controller
{
    public function __construct(
        private readonly GastronomiaCrownSaneamientoService $saneamientoService,
        private readonly EmpresaRepositoryInterface $empresaRepository,
    ) {
    }

    public function index(Request $request): View
    {
        $this->assertCrown();
        can('gestionar-saneamiento-cuentas-gastronomia-crown');

        $empresas = $this->empresaRepository->allFiltrado();
        $empresaId = (int) $request->input('empresa_id', $empresas->first()->id ?? 0);
        $this->assertEmpresaPermitida($empresaId);

        $salas = $this->salasParaFiltro();
        $salaId = (int) $request->input('sala_id', $salas->count() === 1 ? ($salas->first()->id ?? 0) : 0);
        $this->assertSalaPermitida($salaId);
        $salaFiltro = $salaId > 0 ? $salaId : null;
        $faltaElegirSala = $salaFiltro === null;

        $diagnostico = $empresaId > 0 && ! $faltaElegirSala
            ? $this->saneamientoService->diagnostico($empresaId, $salaFiltro)
            : [
                'total' => 0,
                'con_items' => 0,
                'vacias' => 0,
                'confirmacion' => null,
                'cuentas' => [],
            ];

        $desdeJornada = (int) $request->input('desde_jornada', 0) === 1;

        return view('ventas.gastronomia_crown.saneamiento.index', [
            'empresas' => $empresas,
            'empresa_id' => $empresaId,
            'salas' => $salas,
            'sala_id' => $salaId,
            'falta_elegir_sala' => $faltaElegirSala,
            'diagnostico' => $diagnostico,
            'desde_jornada' => $desdeJornada,
            'puede_ejecutar' => can('ejecutar-saneamiento-cuentas-gastronomia-crown', false),
            'url_jornada' => url('ventas/gastronomia/jornada').'?empresa_id='.$empresaId
                .($salaId > 0 ? '&sala_id='.$salaId : ''),
        ]);
    }

    public function apiCerrarCuentas(Request $request): JsonResponse
    {
        $this->assertCrown();
        can('ejecutar-saneamiento-cuentas-gastronomia-crown');

        $empresaId = (int) $request->input('empresa_id', 0);
        $salaId = (int) $request->input('sala_id', 0);
        $cuentaId = (int) $request->input('cuenta_id', 0);
        $confirmacion = (string) $request->input('confirmacion', '');

        $this->assertEmpresaPermitida($empresaId);
        $this->assertSalaPermitida($salaId);
        if ($salaId <= 0) {
            return response()->json(['ok' => false, 'mensaje' => 'Debe indicar la sala.'], 422);
        }

        try {
            $resultado = $cuentaId > 0
                ? $this->saneamientoService->cerrarCuenta($empresaId, $salaId, $cuentaId)
                : $this->saneamientoService->cerrarCuentas($empresaId, $salaId, $confirmacion);
        } catch (InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'mensaje' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'mensaje' => $e->getMessage()], 500);
        }

        $n = (int) ($resultado['cerradas'] ?? 0);
        $diagnostico = $this->saneamientoService->diagnostico($empresaId, $salaId);

        return response()->json([
            'ok' => true,
            'cerradas' => $n,
            'mensaje' => $n === 1 ? '1 cuenta cerrada sin facturar.' : $n.' cuentas cerradas sin facturar.',
            'diagnostico' => $diagnostico,
        ]);
    }

    /**
     * @return \Illuminate\Support\Collection<int, Sala>
     */
    private function salasParaFiltro()
    {
        $q = Sala::query()->orderBy('codigo')->orderBy('nombre');
        $ids = UsuarioSalaAsignadaSupport::idsDesdeSesion();
        if (count($ids) >= 1) {
            $q->whereIn('id', $ids);
        }

        return $q->get(['id', 'codigo', 'nombre']);
    }

    private function assertCrown(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            abort(404);
        }
    }

    private function assertEmpresaPermitida(int $empresaId): void
    {
        if ($empresaId > 0 && ! $this->empresaRepository->empresaIdPermitida($empresaId)) {
            abort(403, 'Empresa no permitida para su usuario.');
        }
    }

    private function assertSalaPermitida(int $salaId): void
    {
        if ($salaId > 0 && ! UsuarioSalaAsignadaSupport::salaIdPermitida($salaId)) {
            abort(403, 'Sala no permitida para su usuario.');
        }
    }
}
