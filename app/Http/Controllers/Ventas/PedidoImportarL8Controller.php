<?php

namespace App\Http\Controllers\Ventas;

use App\Http\Controllers\Controller;
use App\Services\Ventas\Ferli\PedidoImportarFaltantesDesdeL8Service;
use App\Services\Ventas\Ferli\PedidoImportarTareasDesdeL8Service;
use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Http\Request;

class PedidoImportarL8Controller extends Controller
{
    public function __construct(
        private readonly PedidoImportarTareasDesdeL8Service $tareasService,
        private readonly PedidoImportarFaltantesDesdeL8Service $pedidosService,
    ) {
    }

    public function importarTareasPedido(Request $request, int $id)
    {
        can('importar-pedido-l8');
        $this->assertFerli();

        try {
            $resumen = $this->tareasService->importarPorPedidoId($id);
        } catch (\Throwable $e) {
            return redirect()
                ->route('editar_pedido', ['id' => $id])
                ->with('mensaje_error', 'No se pudo importar tareas desde L8: '.$e->getMessage());
        }

        $mensaje = sprintf(
            'Importación tareas L8 (fuente %s): OT %d — cabeceras OT +%d, tareas +%d/~%d, movimientos +%d'
            .(isset($resumen['ot_sincronizados']) ? ', ot_id sincronizados %d (reclamados %d)' : '')
            .'.',
            $resumen['fuente'] ?: '?',
            $resumen['ots'],
            $resumen['insert_ordentrabajo'],
            $resumen['insert_tarea'],
            $resumen['update_tarea'],
            $resumen['insert_movimiento'],
            $resumen['ot_sincronizados'] ?? 0,
            $resumen['ot_reclamados'] ?? 0
        );

        return redirect()
            ->route('editar_pedido', ['id' => $id])
            ->with('mensaje', $mensaje);
    }

    /**
     * Desde liquidación de tareas: trae de L8 las tareas del rango que faltan en L12 (sin duplicar).
     */
    public function importarTareasLiquidacion(Request $request)
    {
        can('importar-pedido-l8');
        $this->assertFerli();

        $fechaDesde = trim((string) $request->input('desdefecha', $request->input('fecha_desde', '')));
        $fechaHasta = trim((string) $request->input('hastafecha', $request->input('fecha_hasta', '')));
        if ($fechaDesde === '') {
            $fechaDesde = date('Y-m-01');
        }
        if ($fechaHasta === '') {
            $fechaHasta = date('Y-m-d');
        }

        try {
            $resumen = $this->tareasService->importarFaltantesPorRangoFechas($fechaDesde, $fechaHasta);
        } catch (\Throwable $e) {
            return redirect()
                ->route('rep_liquidaciontarea')
                ->withInput()
                ->with('mensaje_error', 'No se pudo importar tareas desde L8: '.$e->getMessage());
        }

        $mensaje = sprintf(
            'Tareas L8 → L12 (fuente %s, %s a %s): OT %d — tareas nuevas +%d, fechas actualizadas %d, movimientos +%d.',
            $resumen['fuente'] ?: '?',
            $fechaDesde,
            $fechaHasta,
            $resumen['ots'],
            $resumen['insert_tarea'],
            $resumen['update_tarea'],
            $resumen['insert_movimiento']
        );

        return redirect()
            ->route('rep_liquidaciontarea')
            ->withInput()
            ->with('mensaje', $mensaje);
    }

    public function importarPedidosIndex(Request $request)
    {
        can('importar-pedido-l8');
        $this->assertFerli();

        $fechaDesde = trim((string) $request->input('fecha_desde', ''));
        if ($fechaDesde !== '' && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaDesde)) {
            $fechaDesde = '';
        }
        $limite = max(1, min(300, (int) $request->input('limite', 100)));

        try {
            $resumen = $this->pedidosService->importar(
                $fechaDesde !== '' ? $fechaDesde : null,
                $limite
            );
        } catch (\Throwable $e) {
            return redirect()
                ->route('pedido')
                ->with('mensaje_error', 'No se pudo importar pedidos desde L8: '.$e->getMessage());
        }

        $mensaje = sprintf(
            'Importación pedidos L8 (fuente %s): %d candidatos — pedidos +%d, combinaciones +%d, OT +%d, tareas +%d.',
            $resumen['fuente'] ?: '?',
            $resumen['candidatos'],
            $resumen['insert_pedido'],
            $resumen['insert_combinacion'],
            $resumen['insert_ordentrabajo'],
            $resumen['insert_tarea']
        );

        $redirect = redirect()->route('pedido')->with('mensaje', $mensaje);
        if ($resumen['errores'] !== []) {
            $redirect->with('mensaje_error', implode(' | ', array_slice($resumen['errores'], 0, 10)));
        }

        return $redirect;
    }

    private function assertFerli(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            abort(404);
        }
    }
}
