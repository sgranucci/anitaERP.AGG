<?php

declare(strict_types=1);

namespace App\Http\Controllers\Ventas;

use App\Http\Controllers\Controller;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Repositories\Ventas\Contrato_VentaRepositoryInterface;
use App\Support\Ventas\ContratoVentaPrefillSupport;
use App\Support\Ventas\ContratoVentaSupport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContratoVentaColaController extends Controller
{
    public function __construct(
        private readonly Contrato_VentaRepositoryInterface $repository,
        private readonly EmpresaRepositoryInterface $empresaRepository,
    ) {}

    public function index(Request $request)
    {
        can('listar-contrato-venta-cola');

        $empresaId = (int) $request->input('empresa_id', 0);
        $clienteId = (int) $request->input('cliente_id', 0);
        $fecha = trim((string) $request->input('fecha', date('Y-m-d')));
        if ($fecha === '' || preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) !== 1) {
            $fecha = date('Y-m-d');
        }
        $consultar = $request->boolean('consultar') || $request->has('consultar');

        $empresaQuery = $this->empresaRepository->allFiltrado();
        if ($empresaQuery->count() === 1 && $empresaId <= 0) {
            $empresaId = (int) $empresaQuery->first()->id;
        }

        $datas = collect();
        $periodosPorContrato = [];
        if ($consultar) {
            $datas = $this->repository->listadoColaFacturacion(
                $empresaId > 0 ? $empresaId : null,
                $clienteId > 0 ? $clienteId : null,
                $fecha,
                true
            );
            foreach ($datas as $contrato) {
                $periodo = ContratoVentaSupport::periodoParaFecha(
                    $fecha,
                    (string) ($contrato->periodicidad ?? ContratoVentaSupport::PERIODICIDAD_MENSUAL)
                );
                $periodosPorContrato[(int) $contrato->id] = $periodo;
            }
        }

        return view('ventas.contrato_venta_cola.index', [
            'datas' => $datas,
            'periodosPorContrato' => $periodosPorContrato,
            'empresa_query' => $empresaQuery,
            'empresa_id' => $empresaId > 0 ? $empresaId : null,
            'cliente_id' => $clienteId > 0 ? $clienteId : null,
            'fecha' => $fecha,
            'consultar' => $consultar,
            'puede_facturar' => can('facturar-contrato-venta-cola', false),
        ]);
    }

    public function prefillBatch(Request $request): JsonResponse
    {
        if (! can('listar-contrato-venta-cola', false) && ! can('facturar-contrato-venta-cola', false)) {
            abort(403);
        }

        $ids = array_values(array_filter(array_map('intval', (array) $request->input('contrato_ids', []))));
        $fecha = trim((string) $request->input('fecha', date('Y-m-d')));
        if ($fecha === '' || preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) !== 1) {
            $fecha = date('Y-m-d');
        }

        $lineas = [];
        foreach ($ids as $id) {
            try {
                $contrato = $this->repository->findOrFail($id);
            } catch (\Throwable) {
                continue;
            }
            $lineas[] = ContratoVentaPrefillSupport::armarLinea($contrato, $fecha);
        }

        return response()->json([
            'ok' => true,
            'fecha' => $fecha,
            'lineas' => $lineas,
            'cantidad' => count($lineas),
        ]);
    }
}
