<?php

namespace App\Http\Controllers\Compras;

use App\Exports\Compras\ProgramaPagoExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionProgramaPago;
use App\Models\Compras\ProgramaPago;
use App\Models\Compras\ProgramaPagoCheque;
use App\Repositories\Compras\ProgramaPagoRepositoryInterface;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Models\Compras\ProgramaPagoOp;
use App\Services\Compras\ProgramaPagoGenerarOpService;
use App\Services\Compras\ProgramaPagoService;
use App\Support\Caja\ProgramaPagoAsignarChequesConfigSupport;
use App\Support\Compras\ProgramaPagoListadoFiltros;
use App\Support\Compras\ProgramaPagoOpPlanSupport;
use App\Support\Configuracion\EntornoEmpresaSupport;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

class ProgramaPagoController extends Controller
{
    public function __construct(
        private ProgramaPagoRepositoryInterface $programaPagoRepository,
        private ProgramaPagoService $programaPagoService,
        private ProgramaPagoGenerarOpService $programaPagoGenerarOpService,
        private EmpresaRepositoryInterface $empresaRepository,
    ) {
    }

    public function index(Request $request)
    {
        $this->assertFerli();
        can('listar-programa-pago');

        $filtros = ProgramaPagoListadoFiltros::resolverDesdeRequest($request);
        $filtrosQuery = ProgramaPagoListadoFiltros::paraQueryString($filtros);
        $coleccion = $this->programaPagoRepository->leeProgramaPago($filtros, true);
        $empresa_query = $this->empresaRepository->allFiltrado();

        return view('compras.programa_pago.index', compact(
            'coleccion',
            'filtros',
            'filtrosQuery',
            'empresa_query'
        ));
    }

    public function configuracion()
    {
        $this->assertFerli();
        can('editar-programa-pago');

        $anticipoMaximo = ProgramaPagoAsignarChequesConfigSupport::anticipoMaximo();
        $faltanteToleranciaPct = ProgramaPagoAsignarChequesConfigSupport::faltanteToleranciaPct();

        return view('compras.programa_pago.configuracion', compact(
            'anticipoMaximo',
            'faltanteToleranciaPct'
        ));
    }

    public function actualizarConfiguracion(Request $request)
    {
        $this->assertFerli();
        can('actualizar-programa-pago');

        $data = $request->validate([
            'cheque_anticipo_maximo' => 'required|numeric|min:0',
            'cheque_faltante_tolerancia_pct' => 'required|numeric|min:0|max:100',
        ]);

        ProgramaPagoAsignarChequesConfigSupport::guardarAnticipoMaximo((float) $data['cheque_anticipo_maximo']);
        ProgramaPagoAsignarChequesConfigSupport::guardarFaltanteToleranciaPct((float) $data['cheque_faltante_tolerancia_pct']);

        return redirect()
            ->route('configuracion_programa_pago')
            ->with('mensaje', 'Configuración de asignación de cheques guardada.');
    }

    public function listar(Request $request, $formato = null)
    {
        $this->assertFerli();
        can('listar-programa-pago');
        ini_set('memory_limit', '512M');
        ini_set('max_execution_time', '120');

        $filtros = ProgramaPagoListadoFiltros::resolverDesdeRequest($request);
        if (! $formato) {
            return redirect()->route('programa_pago', ProgramaPagoListadoFiltros::paraQueryString($filtros));
        }

        $formato = strtoupper((string) $formato);
        $datas = $this->programaPagoRepository->leeProgramaPago($filtros, false);
        foreach ($datas as $fila) {
            $fila->nombreempresa = $fila->empresas->nombre ?? '';
        }

        if ($formato === 'PDF') {
            $pdf = Pdf::loadView('compras.programa_pago.listado_index', ['datas' => $datas])
                ->setPaper('legal', 'landscape');
            $path = storage_path('pdf/listados/listado_programa_pago.pdf');
            if (! is_dir(dirname($path))) {
                mkdir(dirname($path), 0755, true);
            }
            $pdf->save($path);

            return response()->file($path);
        }

        if (in_array($formato, ['EXCEL', 'CSV'], true)) {
            $export = app(ProgramaPagoExport::class)->parametrosListado($filtros);
            $nombre = 'programas_pago_'.date('Ymd_His');

            return $formato === 'CSV'
                ? Excel::download($export, $nombre.'.csv', \Maatwebsite\Excel\Excel::CSV)
                : Excel::download($export, $nombre.'.xlsx');
        }

        return redirect()->route('programa_pago', ProgramaPagoListadoFiltros::paraQueryString($filtros));
    }

    public function crear()
    {
        $this->assertFerli();
        can('crear-programa-pago');

        $data = new ProgramaPago([
            'fecha_base' => date('Y-m-d'),
            'anio_mes_inicio' => date('Y-m'),
            'cantidad_meses' => 4,
            'incluye_transf' => true,
            'estado' => ProgramaPago::ESTADO_BORRADOR,
        ]);
        $empresa_query = $this->empresaRepository->allFiltrado();

        return view('compras.programa_pago.crear', compact('data', 'empresa_query'));
    }

    public function guardar(ValidacionProgramaPago $request)
    {
        $this->assertFerli();
        can('crear-programa-pago');

        $empresaId = (int) $request->input('empresa_id');
        if (! $this->empresaRepository->empresaIdPermitida($empresaId)) {
            return back()->with('error', 'Empresa no permitida.')->withInput();
        }

        try {
            $programa = $this->programaPagoService->crear([
                'empresa_id' => $empresaId,
                'titulo' => $request->input('titulo'),
                'fecha_base' => $request->input('fecha_base'),
                'anio_mes_inicio' => $request->input('anio_mes_inicio'),
                'cantidad_meses' => (int) $request->input('cantidad_meses', 4),
                'incluye_transf' => (bool) $request->input('incluye_transf', true),
                'detalle' => $request->input('detalle'),
                'sembrar_deuda' => (bool) $request->input('sembrar_deuda', true),
            ]);
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }

        return redirect()
            ->route('editar_programa_pago', $programa->id)
            ->with('mensaje', 'Programa creado. Ajuste montos por mes y guarde.');
    }

    public function editar(int $id)
    {
        $this->assertFerli();
        can('editar-programa-pago');

        $data = $this->programaPagoRepository->find($id);
        if (! $this->empresaRepository->empresaIdPermitida((int) $data->empresa_id)) {
            abort(403);
        }

        $matriz = $this->programaPagoService->armarVistaMatriz($data);
        $empresa_query = $this->empresaRepository->allFiltrado();
        $puedeEditar = can('actualizar-programa-pago', false) && $data->esEditable();

        return view('compras.programa_pago.editar', compact(
            'data',
            'matriz',
            'empresa_query',
            'puedeEditar'
        ));
    }

    public function actualizar(ValidacionProgramaPago $request, int $id)
    {
        $this->assertFerli();
        can('actualizar-programa-pago');

        $programa = $this->programaPagoRepository->find($id);
        if (! $this->empresaRepository->empresaIdPermitida((int) $programa->empresa_id)) {
            abort(403);
        }

        try {
            $this->programaPagoService->actualizar($programa, $request->validated());
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }

        return redirect()
            ->route('editar_programa_pago', $id)
            ->with('mensaje', 'Programa actualizado.');
    }

    public function eliminar(int $id)
    {
        $this->assertFerli();
        can('borrar-programa-pago');

        $programa = $this->programaPagoRepository->find($id);
        if (! $this->empresaRepository->empresaIdPermitida((int) $programa->empresa_id)) {
            abort(403);
        }

        try {
            $this->programaPagoService->eliminar($programa);
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('programa_pago')->with('mensaje', 'Programa eliminado.');
    }

    public function refrescarSaldos(int $id)
    {
        $this->assertFerli();
        can('actualizar-programa-pago');

        $programa = $this->programaPagoRepository->find($id);
        if (! $this->empresaRepository->empresaIdPermitida((int) $programa->empresa_id)) {
            abort(403);
        }

        try {
            $n = $this->programaPagoService->refrescarSaldos($programa);
            $creadas = $this->programaPagoService->sembrarDesdeDeuda($programa);
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('mensaje', "Saldos actualizados ({$n}). Proveedores nuevos: {$creadas}.");
    }

    public function refrescarCheques(int $id)
    {
        $this->assertFerli();
        can('actualizar-programa-pago');

        $programa = $this->programaPagoRepository->find($id);
        if (! $this->empresaRepository->empresaIdPermitida((int) $programa->empresa_id)) {
            abort(403);
        }

        try {
            $stats = $this->programaPagoService->refrescarCheques($programa);
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        $mensaje = 'Cheques en cartera actualizados: '.$stats['cantidad']
            .' cheque(s), total '.number_format((float) $stats['total'], 2, ',', '.').'.';
        if ((int) $stats['montos_asignados'] > 0) {
            $mensaje .= ' Montos de cheques ya asignados actualizados: '.$stats['montos_asignados'].'.';
        }

        return back()->with('mensaje', $mensaje);
    }

    public function agregarProveedor(Request $request, int $id)
    {
        $this->assertFerli();
        can('actualizar-programa-pago');

        $programa = $this->programaPagoRepository->find($id);
        if (! $this->empresaRepository->empresaIdPermitida((int) $programa->empresa_id)) {
            abort(403);
        }

        $proveedorId = (int) $request->input('proveedor_id');
        try {
            $this->programaPagoService->agregarProveedor(
                $programa,
                $proveedorId,
                $request->input('observacion')
            );
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('mensaje', 'Proveedor agregado al programa.');
    }

    public function cerrar(int $id)
    {
        $this->assertFerli();
        can('actualizar-programa-pago');

        $programa = $this->programaPagoRepository->find($id);
        try {
            $this->programaPagoService->cerrar($programa);
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('mensaje', 'Programa cerrado.');
    }

    public function reabrir(int $id)
    {
        $this->assertFerli();
        can('actualizar-programa-pago');

        $programa = $this->programaPagoRepository->find($id);
        try {
            $this->programaPagoService->reabrir($programa);
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('mensaje', 'Programa reabierto.');
    }

    public function exportarMatriz(int $id, string $formato = 'EXCEL')
    {
        $this->assertFerli();
        can('listar-programa-pago');
        ini_set('memory_limit', '512M');

        $programa = $this->programaPagoRepository->find($id);
        if (! $this->empresaRepository->empresaIdPermitida((int) $programa->empresa_id)) {
            abort(403);
        }

        $matriz = $this->programaPagoService->armarVistaMatriz($programa);
        $formato = strtoupper($formato);

        if ($formato === 'PDF') {
            $pdf = Pdf::loadView('compras.programa_pago.listado', [
                'data' => $programa,
                'matriz' => $matriz,
            ])->setPaper('legal', 'landscape');

            return $pdf->stream('programa_pago_'.$id.'.pdf');
        }

        $export = app(ProgramaPagoExport::class)->parametrosMatriz($programa, $matriz);
        $nombre = 'programa_pago_'.$id.'_'.date('Ymd_His');

        return $formato === 'CSV'
            ? Excel::download($export, $nombre.'.csv', \Maatwebsite\Excel\Excel::CSV)
            : Excel::download($export, $nombre.'.xlsx');
    }

    public function asignarCheques(Request $request, int $id)
    {
        $this->assertFerli();
        can('actualizar-programa-pago');

        $programa = $this->programaPagoRepository->find($id);
        if (! $this->empresaRepository->empresaIdPermitida((int) $programa->empresa_id)) {
            abort(403);
        }

        $lineaId = (int) $request->input('linea_id', 0);
        if ($lineaId <= 0) {
            $lineaId = null;
        }

        try {
            $resultado = $this->programaPagoService->asignarChequesCartera($programa, $lineaId);
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        $resumen = $resultado['resumen'] ?? [];
        $nAsig = (int) ($resumen['asignados'] ?? 0);
        $nDisc = (int) ($resumen['discrepancias'] ?? 0);
        $propuestas = array_values($resultado['propuestas'] ?? []);
        $mensaje = "Asignación de cheques: {$nAsig} cheque(s).";
        if ($nDisc > 0) {
            $mensaje .= " Discrepancias: {$nDisc}.";
        }
        if ($propuestas !== []) {
            $mensaje .= ' Hay '.count($propuestas).' propuesta(s) con cheques posteriores: no se grabaron hasta que las aceptes.';
            session()->put('pp_propuestas_cheques.'.$id, $propuestas);
        } else {
            session()->forget('pp_propuestas_cheques.'.$id);
        }
        $mensaje .= $this->textoAjustes($resultado['ajustes'] ?? []);

        $redirect = $this->redirectPrograma($id, $lineaId)
            ->with('mensaje', $mensaje)
            ->with('pp_asignacion_cheques', $resultado);
        if ($lineaId !== null) {
            $redirect->with('pp_foco_linea', $lineaId);
        }

        return $redirect;
    }

    public function desasignarCheques(Request $request, int $id)
    {
        $this->assertFerli();
        can('actualizar-programa-pago');

        $programa = $this->programaPagoRepository->find($id);
        if (! $this->empresaRepository->empresaIdPermitida((int) $programa->empresa_id)) {
            abort(403);
        }

        $alcance = (string) $request->input('alcance', '');
        $lineaId = (int) $request->input('linea_id', 0);
        $clave = trim((string) $request->input('clave', ''));
        $asignacionId = (int) $request->input('asignacion_id', 0);

        if (! in_array($alcance, ['todos', 'linea', 'celda', 'uno'], true)) {
            return back()->with('error', 'No se indicó qué cheques quitar.');
        }
        if ($alcance === 'linea' && $lineaId <= 0) {
            return back()->with('error', 'Seleccioná un proveedor para quitar sus cheques.');
        }
        if ($alcance === 'celda' && ($lineaId <= 0 || $clave === '')) {
            return back()->with('error', 'No se indicó el mes a desasignar.');
        }
        if ($alcance === 'uno' && $asignacionId <= 0) {
            return back()->with('error', 'No se indicó el cheque a quitar.');
        }

        $foco = $lineaId;
        if ($foco <= 0 && $asignacionId > 0) {
            $foco = (int) ProgramaPagoCheque::query()->whereKey($asignacionId)->value('programa_pago_linea_id');
        }

        try {
            $n = $this->programaPagoService->desasignarCheques(
                $programa,
                $alcance,
                $lineaId > 0 ? $lineaId : null,
                $clave !== '' ? $clave : null,
                $asignacionId > 0 ? $asignacionId : null,
            );
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        $redirect = $this->redirectPrograma($id, $foco > 0 ? $foco : null)
            ->with('mensaje', $n > 0
                ? "Se quitaron {$n} cheque(s) asignados. Los meses volvieron al saldo adeudado (sin TRANSF)."
                : 'Ese proveedor no tenía cheques asignados.');
        if ($foco > 0) {
            $redirect->with('pp_foco_linea', $foco);
        }

        return $redirect;
    }

    public function aceptarChequesAdelantados(Request $request, int $id)
    {
        $this->assertFerli();
        can('actualizar-programa-pago');

        $programa = $this->programaPagoRepository->find($id);
        if (! $this->empresaRepository->empresaIdPermitida((int) $programa->empresa_id)) {
            abort(403);
        }

        $todas = session('pp_propuestas_cheques.'.$id, []);
        if (! is_array($todas)) {
            $todas = [];
        }
        $indices = array_values(array_unique(array_map('intval', (array) $request->input('propuestas', []))));
        $elegidas = [];
        foreach ($indices as $indice) {
            if (isset($todas[$indice]) && is_array($todas[$indice])) {
                $elegidas[$indice] = $todas[$indice];
            }
        }
        if ($elegidas === []) {
            return back()->with('error', 'Marcá al menos una propuesta para aceptarla.');
        }

        try {
            $res = $this->programaPagoService->persistirPropuestasCheques($programa, array_values($elegidas));
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        $ok = array_flip($res['claves_ok'] ?? []);
        foreach ($elegidas as $indice => $prop) {
            $claveOk = ((int) ($prop['linea_id'] ?? 0)).'|'.(string) ($prop['clave'] ?? '');
            if (isset($ok[$claveOk])) {
                unset($todas[$indice]);
            }
        }
        $todas = array_values($todas);
        if ($todas === []) {
            session()->forget('pp_propuestas_cheques.'.$id);
        } else {
            session()->put('pp_propuestas_cheques.'.$id, $todas);
        }

        $grabados = (int) ($res['grabados'] ?? 0);
        $errores = $res['errores'] ?? [];
        $mensaje = "Se asignaron {$grabados} cheque(s) de la propuesta.";
        if ($errores !== []) {
            $mensaje .= ' '.implode(' ', $errores);
        }

        $foco = null;
        $lineaIdsOk = [];
        foreach ($elegidas as $prop) {
            $claveOk = ((int) ($prop['linea_id'] ?? 0)).'|'.(string) ($prop['clave'] ?? '');
            if (! isset($ok[$claveOk])) {
                continue;
            }
            $lid = (int) $prop['linea_id'];
            $lineaIdsOk[$lid] = true;
            if ($foco === null) {
                $foco = $lid;
            }
        }
        if ($grabados > 0 && $lineaIdsOk !== []) {
            $excluir = [];
            foreach ($todas as $prop) {
                $lid = (int) ($prop['linea_id'] ?? 0);
                $clave = (string) ($prop['clave'] ?? '');
                if ($lid > 0 && $clave !== '') {
                    $excluir[$lid][] = $clave;
                }
            }
            try {
                $ajustes = $this->programaPagoService->alinearMontosACheques(
                    $programa,
                    array_keys($lineaIdsOk),
                    $excluir
                );
                $mensaje .= $this->textoAjustes($ajustes);
            } catch (Throwable $e) {
                $mensaje .= ' No se pudo reacomodar el programado: '.$e->getMessage();
            }
        }

        $redirect = $this->redirectPrograma($id, $foco)
            ->with($grabados > 0 ? 'mensaje' : 'error', $mensaje);
        if ($foco !== null) {
            $redirect->with('pp_foco_linea', $foco);
        }

        return $redirect;
    }

    public function descartarChequesAdelantados(int $id)
    {
        $this->assertFerli();
        can('actualizar-programa-pago');

        $programa = $this->programaPagoRepository->find($id);
        if (! $this->empresaRepository->empresaIdPermitida((int) $programa->empresa_id)) {
            abort(403);
        }

        session()->forget('pp_propuestas_cheques.'.$id);

        return redirect()
            ->route('editar_programa_pago', $id)
            ->with('mensaje', 'Se descartaron las propuestas. No se asignó ningún cheque de meses posteriores.');
    }

    public function prepararGenerarOp(Request $request, int $id)
    {
        $this->assertFerli();
        can('actualizar-programa-pago');
        can('crear-pagoproveedor');

        $programa = $this->programaPagoRepository->find($id);
        if (! $this->empresaRepository->empresaIdPermitida((int) $programa->empresa_id)) {
            abort(403);
        }
        if (! $programa->esEditable()) {
            return redirect()->route('editar_programa_pago', $id)
                ->with('error', 'El programa está cerrado. Reabrilo para generar órdenes de pago.');
        }

        $lineaIds = array_map('intval', (array) $request->input('linea_ids', []));
        $fechaHoy = date('Y-m-d');
        $plan = ProgramaPagoOpPlanSupport::armar($programa, $lineaIds, $fechaHoy);
        if ($plan === []) {
            return redirect()->route('editar_programa_pago', $id)
                ->with('error', 'Tildá al menos un proveedor con montos programados.');
        }

        $necesitaCuenta = false;
        foreach ($plan as $fila) {
            if ($fila['necesita_cuenta'] && ! $fila['pagoproveedor_id']) {
                $necesitaCuenta = true;
                break;
            }
        }

        return view('compras.programa_pago.generar_op', [
            'data' => $programa,
            'plan' => $plan,
            'necesitaCuenta' => $necesitaCuenta,
            'fechaHoy' => $fechaHoy,
        ]);
    }

    public function generarOp(Request $request, int $id)
    {
        $this->assertFerli();
        can('actualizar-programa-pago');
        can('crear-pagoproveedor');

        $programa = $this->programaPagoRepository->find($id);
        if (! $this->empresaRepository->empresaIdPermitida((int) $programa->empresa_id)) {
            abort(403);
        }

        $pedidos = [];
        foreach ((array) $request->input('ops', []) as $op) {
            if (empty($op['incluir'])) {
                continue;
            }
            $pedidos[] = [
                'linea_id' => (int) ($op['linea_id'] ?? 0),
                'fecha' => (string) ($op['fecha'] ?? ''),
            ];
        }

        $resultado = $this->programaPagoGenerarOpService->generar(
            $programa,
            $pedidos,
            (int) $request->input('cuentacaja_id')
        );

        return redirect()
            ->route('resultado_op_programa_pago', $id)
            ->with('pp_generacion_op', $resultado);
    }

    public function resultadoGenerarOp(int $id)
    {
        $this->assertFerli();
        can('editar-programa-pago');

        $programa = $this->programaPagoRepository->find($id);
        if (! $this->empresaRepository->empresaIdPermitida((int) $programa->empresa_id)) {
            abort(403);
        }

        $generadas = ProgramaPagoOp::query()
            ->whereHas('linea', static fn ($q) => $q->where('programa_pago_id', $programa->id))
            ->with([
                'linea.proveedores:id,codigo,nombre',
                'pagoproveedor:id,numerotransaccion,fecha,monto,estado,tipocomprobante,proveedor_id',
            ])
            ->orderByDesc('id')
            ->get();

        return view('compras.programa_pago.resultado_op', [
            'data' => $programa,
            'generadas' => $generadas,
            'corrida' => session('pp_generacion_op'),
        ]);
    }

    private function redirectPrograma(int $id, ?int $lineaId = null)
    {
        $params = ['id' => $id];
        if ($lineaId !== null && $lineaId > 0) {
            $params['linea'] = $lineaId;
        }

        return redirect()->route('editar_programa_pago', $params);
    }

    /**
     * @param  list<array<string, mixed>>  $ajustes
     */
    private function textoAjustes(array $ajustes): string
    {
        if (count($ajustes) > 3) {
            return ' Se alinearon '.count($ajustes).' proveedores a los cheques (neto de retenciones). Lo que falta a transferir quedó en TRANSF.';
        }
        $partes = [];
        foreach ($ajustes as $ajuste) {
            $transf = (float) ($ajuste['transf'] ?? 0);
            $ret = (float) ($ajuste['retenciones'] ?? 0);
            $texto = trim((string) ($ajuste['proveedor'] ?? '')).': los meses quedaron iguales a los cheques';
            if ($ret > 0.009) {
                $texto .= '. Retenciones est. '.number_format($ret, 2, ',', '.');
            }
            $texto .= $transf > 0.009
                ? '. Transferencia '.number_format($transf, 2, ',', '.')
                : '. Sin transferencia';
            $partes[] = $texto;
        }

        return $partes === [] ? '' : ' '.implode(' ', $partes).'.';
    }

    private function assertFerli(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            abort(404);
        }
    }
}
