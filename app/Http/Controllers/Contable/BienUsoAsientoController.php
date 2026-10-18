<?php

declare(strict_types=1);

namespace App\Http\Controllers\Contable;

use App\Http\Controllers\Controller;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Services\Contable\BienUsoAsientoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Proceso asiento de amortizaciones / revalúo (Anita p-asibie.c).
 * Módulo estándar ERP.
 */
class BienUsoAsientoController extends Controller
{
    public function __construct(
        private readonly BienUsoAsientoService $asientoService,
        private readonly EmpresaRepositoryInterface $empresaRepository,
    ) {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        can('listar-bien-uso-asiento');

        $empresaQuery = $this->empresaRepository->allFiltrado();
        $filtros = $this->filtrosDesdeRequest($request, $empresaQuery);
        $preview = null;
        $consultado = false;
        $error = null;

        if ($request->boolean('consultar')) {
            try {
                $preview = $this->asientoService->preview($filtros);
                $consultado = true;
            } catch (\Throwable $e) {
                $error = $e->getMessage();
            }
        }

        return view('contable.bien_uso_asiento.index', [
            'filtros' => $filtros,
            'empresa_query' => $empresaQuery,
            'preview' => $preview,
            'consultado' => $consultado,
            'error' => $error,
            'tipos' => [
                BienUsoAsientoService::TIPO_AMORTIZACION => 'Amortización',
                BienUsoAsientoService::TIPO_REVALUO => 'Revalúo contable',
            ],
        ]);
    }

    public function confirmar(Request $request)
    {
        can('ejecutar-bien-uso-asiento');

        $empresaQuery = $this->empresaRepository->allFiltrado();
        $filtros = $this->filtrosDesdeRequest($request, $empresaQuery);

        try {
            $asiento = $this->asientoService->confirmar($filtros, (int) Auth::id());
        } catch (\Throwable $e) {
            return redirect()
                ->route('bien_uso_asiento', array_merge($filtros, ['consultar' => 1]))
                ->with('error', $e->getMessage());
        }

        return redirect()
            ->route('editar_asiento', [
                'id' => $asiento->id,
                'origen' => 'modal_consulta',
                'vista' => 'consulta',
            ])
            ->with('mensaje', 'Asiento '.$asiento->numeroasiento.' generado con éxito');
    }

    /**
     * @param  \Illuminate\Support\Collection<int, mixed>  $empresaQuery
     * @return array<string, mixed>
     */
    private function filtrosDesdeRequest(Request $request, $empresaQuery): array
    {
        $empresaId = (int) $request->input('empresa_id', 0);
        if ($empresaId <= 0 && $empresaQuery->count() === 1) {
            $empresaId = (int) $empresaQuery->first()->id;
        }

        return [
            'empresa_id' => $empresaId,
            'tipo_operacion' => (string) $request->input(
                'tipo_operacion',
                BienUsoAsientoService::TIPO_AMORTIZACION
            ),
            'ejercicio' => (int) $request->input('ejercicio', (int) date('Y')),
            'fecha_asiento' => (string) $request->input('fecha_asiento', date('Y-m-d')),
            'codigo_bien_desde' => $request->input('codigo_bien_desde') !== null && $request->input('codigo_bien_desde') !== ''
                ? (int) $request->input('codigo_bien_desde')
                : null,
            'codigo_bien_hasta' => $request->input('codigo_bien_hasta') !== null && $request->input('codigo_bien_hasta') !== ''
                ? (int) $request->input('codigo_bien_hasta')
                : null,
            'bien_uso_rubro_id' => $request->input('bien_uso_rubro_id') !== null && $request->input('bien_uso_rubro_id') !== ''
                ? (int) $request->input('bien_uso_rubro_id')
                : null,
            'centrocosto_id' => $request->input('centrocosto_id') !== null && $request->input('centrocosto_id') !== ''
                ? (int) $request->input('centrocosto_id')
                : null,
        ];
    }
}
