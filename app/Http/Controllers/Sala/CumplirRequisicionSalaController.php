<?php

namespace App\Http\Controllers\Sala;

use App\Http\Controllers\Controller;
use App\Models\Sala\RequisicionSala;
use App\Models\Stock\Depmae;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Repositories\Sala\CumplimientoRequisicionSalaRepositoryInterface;
use App\Repositories\Sala\TecnicoLaboratorioRepositoryInterface;
use App\Services\Sala\CumplimientoRequisicionSalaRevertirService;
use App\Services\Sala\CumplirRequisicionSalaPdfService;
use App\Services\Sala\CumplirRequisicionSalaService;
use App\Support\Sala\CumplimientoRequisicionSalaListadoFiltros;
use App\Traits\Sala\RequisicionSalaArticuloEstadoParcialTrait;
use App\Traits\Sala\RequisicionSalaArticuloEstadoTrait;
use Illuminate\Http\Request;

class CumplirRequisicionSalaController extends Controller
{
    use RequisicionSalaArticuloEstadoParcialTrait;

    public function __construct(
        private CumplirRequisicionSalaService $service,
        private CumplirRequisicionSalaPdfService $pdfService,
        private CumplimientoRequisicionSalaRepositoryInterface $cumplimientoRepository,
        private CumplimientoRequisicionSalaRevertirService $revertirService,
        private TecnicoLaboratorioRepositoryInterface $tecnicoRepository,
        private EmpresaRepositoryInterface $empresaRepository,
    ) {
    }

    public function index(Request $request)
    {
        can('cumplir-requisicion-sala');

        $filtros = CumplimientoRequisicionSalaListadoFiltros::resolverDesdeRequest($request);
        $filtrosQuery = CumplimientoRequisicionSalaListadoFiltros::paraQueryString($filtros);
        $coleccion = $this->cumplimientoRepository->leeCumplimientos($filtros, true);
        $camposFiltro = CumplimientoRequisicionSalaListadoFiltros::CAMPOS;
        $requisicionSalaId = (int) ($filtros['requisicion_sala_id'] ?? 0);

        return view('sala.cumplir_requisicion_sala.index', compact(
            'coleccion',
            'filtros',
            'filtrosQuery',
            'camposFiltro',
            'requisicionSalaId',
        ));
    }

    public function crear(Request $request)
    {
        can('cumplir-requisicion-sala');

        $requisicionId = (int) $request->query('requisicion_sala_id', 0);
        $modoNpu = $request->query('modo') === 'npu';
        $requisicion = null;
        $lineas = collect();
        $errorCarga = null;
        $pdfToken = session('cumple_pdf_token');

        if ($requisicionId > 0 && ! $modoNpu) {
            $carga = $this->service->cargarRequisicion($requisicionId);
            if ($carga['ok'] ?? false) {
                $requisicion = $carga['requisicion'];
                $lineas = $carga['lineas'];
            } else {
                $errorCarga = $carga['mensaje'] ?? 'No se pudo cargar la requisici&oacute;n.';
            }
        }

        $depositoLabId = $this->service->resolverDepositoLaboratorioId();
        $depositoLab = $depositoLabId > 0
            ? Depmae::query()->find($depositoLabId)
            : null;
        $tecnicos = $requisicion
            ? $this->tecnicoRepository->allActivos((int) $requisicion->empresa_id)
            : collect();

        return view('sala.cumplir_requisicion_sala.crear', [
            'requisicion' => $requisicion,
            'lineas' => $lineas,
            'errorCarga' => $errorCarga,
            'modoNpu' => $modoNpu,
            'depositoLabId' => $depositoLabId,
            'depositoLab' => $depositoLab,
            'tecnicos' => $tecnicos,
            'pdfToken' => $pdfToken,
            'estado_linea_enum' => RequisicionSalaArticuloEstadoTrait::$enumEstado,
            'estado_parcial_enum' => self::$enumEstadoParcial,
            'estados_cumplir' => CumplirRequisicionSalaService::estadosPermitidosParaCumplir(),
        ]);
    }

    public function consultar(int $id)
    {
        can('cumplir-requisicion-sala');

        $cumplimiento = $this->cumplimientoRepository->findConDetalle($id);
        if (! $cumplimiento) {
            return redirect()->route('cumplir_requisicion_sala')
                ->with('mensaje-error', 'Cumplimiento no encontrado.');
        }

        $requisiciones = [];
        foreach ($cumplimiento->articulos as $linea) {
            $req = $linea->requisicionSala;
            if ($req) {
                $requisiciones[(int) $req->id] = $req;
            }
        }

        return view('sala.cumplir_requisicion_sala.consultar', [
            'cumplimiento' => $cumplimiento,
            'requisiciones' => array_values($requisiciones),
        ]);
    }

    public function consultaRequisicion(Request $request)
    {
        can('cumplir-requisicion-sala');

        $busqueda = trim((string) $request->query('q', ''));
        $estados = CumplirRequisicionSalaService::estadosPermitidosParaCumplir();
        $empresas = $this->empresaRepository->traeEmpresasAsignadas();

        $query = RequisicionSala::query()
            ->with(['depositos', 'centrocostos', 'empresas'])
            ->whereIn('estado', $estados)
            ->whereIn('empresa_id', $empresas)
            ->orderByDesc('id')
            ->limit(30);

        if ($busqueda !== '') {
            if (ctype_digit($busqueda)) {
                $query->where(function ($q) use ($busqueda) {
                    $q->where('numerorequisicion', (int) $busqueda)
                        ->orWhere('id', (int) $busqueda);
                });
            } else {
                $query->where(function ($q) use ($busqueda) {
                    $q->where('comentario', 'like', '%'.$busqueda.'%')
                        ->orWhere('detalle', 'like', '%'.$busqueda.'%');
                });
            }
        }

        $filas = $query->get()->map(function (RequisicionSala $row) {
            return [
                'id' => $row->id,
                'numerorequisicion' => $row->numerorequisicion,
                'fecha' => optional($row->fecha)->format('d/m/Y'),
                'estado' => $row->estado,
                'empresa' => $row->empresas?->nombre,
                'deposito' => self::etiquetaDeposito($row->depositos),
                'centrocosto' => trim(($row->centrocostos?->codigo ?? '').' '.$row->centrocostos?->nombre),
            ];
        });

        return response()->json(['data' => $filas]);
    }

    public function consultaNpu(Request $request)
    {
        can('cumplir-requisicion-sala');

        $npu = trim((string) $request->query('npu', $request->input('npu', '')));
        $resultado = $this->service->buscarLineaPorNpu($npu);
        if (! ($resultado['ok'] ?? false)) {
            return response()->json(['ok' => false, 'mensaje' => $resultado['mensaje'] ?? 'NPU no encontrado'], 422);
        }

        $req = $resultado['requisicion'];
        $linea = $resultado['linea'];
        $depositoLabId = $this->service->resolverDepositoLaboratorioId();
        $depositoLab = $depositoLabId > 0 ? Depmae::query()->find($depositoLabId) : null;
        $tecnicos = $this->tecnicoRepository->allActivos((int) $req->empresa_id);
        $pendiente = (float) $linea->cantidad - (float) ($linea->cantidadentregada ?? 0);

        return response()->json([
            'ok' => true,
            'requisicion' => [
                'id' => $req->id,
                'numerorequisicion' => $req->numerorequisicion,
                'fecha' => optional($req->fecha)->format('d/m/Y'),
                'fecha_entrega' => optional($req->fecha_entrega)->format('d/m/Y'),
                'estado' => $req->estado,
                'empresa' => $req->empresas?->nombre,
                'empresa_id' => $req->empresa_id,
                'deposito_id' => $req->deposito_id,
                'deposito' => self::etiquetaDeposito($req->depositos),
                'centrocosto' => trim(($req->centrocostos?->codigo ?? '').' '.($req->centrocostos?->nombre)),
            ],
            'linea' => [
                'id' => $linea->id,
                'articulo_id' => $linea->articulo_id,
                'sku' => $linea->articulos?->sku,
                'descripcion' => $linea->descripcionArticulo(),
                'cantidad' => (float) $linea->cantidad,
                'cantidadentregada' => (float) ($linea->cantidadentregada ?? 0),
                'pendiente' => $pendiente,
                'uid' => $linea->uid,
                'numeroparte' => $linea->numeroparte,
                'destino' => (string) ($linea->destino ?? 'S'),
                'requiere_tecnico' => (string) ($linea->destino ?? '') === 'R',
                'deposito_origen_id' => $depositoLabId,
                'deposito_origen_codigo' => $depositoLab?->codigo,
                'deposito_origen_nombre' => $depositoLab?->nombre,
            ],
            'tecnicos' => $tecnicos->map(fn ($t) => ['id' => $t->id, 'nombre' => $t->nombre, 'legajo' => $t->legajo]),
        ]);
    }

    public function datosRequisicion(int $id)
    {
        can('cumplir-requisicion-sala');

        $carga = $this->service->cargarRequisicion($id);
        if (! ($carga['ok'] ?? false)) {
            return response()->json(['ok' => false, 'mensaje' => $carga['mensaje'] ?? 'Error'], 422);
        }

        /** @var RequisicionSala $req */
        $req = $carga['requisicion'];
        $depositoLabId = $this->service->resolverDepositoLaboratorioId();
        $depositoLab = $depositoLabId > 0 ? Depmae::query()->find($depositoLabId) : null;
        $tecnicos = $this->tecnicoRepository->allActivos((int) $req->empresa_id);

        $lineas = $carga['lineas']->map(function ($linea) use ($depositoLabId, $depositoLab) {
            $pendiente = (float) $linea->cantidad - (float) ($linea->cantidadentregada ?? 0);

            return [
                'id' => $linea->id,
                'articulo_id' => $linea->articulo_id,
                'sku' => $linea->articulos?->sku,
                'descripcion' => $linea->descripcionArticulo(),
                'cantidad' => (float) $linea->cantidad,
                'cantidadentregada' => (float) ($linea->cantidadentregada ?? 0),
                'pendiente' => $pendiente,
                'uid' => $linea->uid,
                'numeroparte' => $linea->numeroparte,
                'destino' => (string) ($linea->destino ?? 'S'),
                'requiere_tecnico' => (string) ($linea->destino ?? '') === 'R',
                'deposito_origen_id' => $depositoLabId,
                'deposito_origen_codigo' => $depositoLab?->codigo,
                'deposito_origen_nombre' => $depositoLab?->nombre,
            ];
        });

        return response()->json([
            'ok' => true,
            'requisicion' => [
                'id' => $req->id,
                'numerorequisicion' => $req->numerorequisicion,
                'fecha' => optional($req->fecha)->format('d/m/Y'),
                'fecha_entrega' => optional($req->fecha_entrega)->format('d/m/Y'),
                'estado' => $req->estado,
                'empresa' => $req->empresas?->nombre,
                'deposito_id' => $req->deposito_id,
                'deposito' => self::etiquetaDeposito($req->depositos),
                'empresa_id' => $req->empresa_id,
                'centrocosto' => trim(($req->centrocostos?->codigo ?? '').' '.$req->centrocostos?->nombre),
                'comentario' => $req->comentario,
            ],
            'lineas' => $lineas,
            'tecnicos' => $tecnicos->map(fn ($t) => ['id' => $t->id, 'nombre' => $t->nombre, 'legajo' => $t->legajo]),
            'deposito_lab_id' => $depositoLabId,
        ]);
    }

    public function grabar(Request $request)
    {
        can('cumplir-requisicion-sala');

        $lineas = $request->input('lineas', []);
        if (! is_array($lineas) || $lineas === []) {
            return redirect()->route('crear_cumplir_requisicion_sala')
                ->with('mensaje-error', 'Debe cargar al menos una l&iacute;nea para cumplir.');
        }

        $result = $this->service->grabar($request->all());
        if (($result['mensaje'] ?? '') !== 'ok') {
            $requisicionId = (int) $request->input('requisicion_sala_id', 0);
            $params = $requisicionId > 0 ? ['requisicion_sala_id' => $requisicionId] : ['modo' => 'npu'];

            return redirect()->route('crear_cumplir_requisicion_sala', $params)
                ->withInput()
                ->with('mensaje-error', $result['errores'] ?? 'Error al grabar cumplimiento.');
        }

        $pdfToken = null;
        if (! empty($result['impresion'])) {
            $pdfToken = $this->pdfService->guardarEnSesion($result['impresion']);
        }

        $msg = 'Cumplimiento N&ordm; '.($result['cumplimiento_numero'] ?? '').' registrado con &eacute;xito.';
        $detalleTm = $result['transferencias_detalle'] ?? [];
        if ($detalleTm !== []) {
            $etiquetas = array_map(static function (array $tm): string {
                $codigo = trim((string) ($tm['codigo'] ?? ''));
                $id = (int) ($tm['id'] ?? 0);

                return $codigo !== '' ? $codigo : '#'.$id;
            }, $detalleTm);
            $msg .= ' Transferencias: '.implode(', ', $etiquetas).'.';
        } elseif (! empty($result['transferencias'])) {
            $msg .= ' Transferencias: '.implode(', ', $result['transferencias']).'.';
        }

        $redirectParams = [];
        $requisicionId = (int) $request->input('requisicion_sala_id', 0);
        if ($requisicionId > 0) {
            $req = RequisicionSala::query()->find($requisicionId);
            if ($req && $this->service->puedeCumplir($req)) {
                $redirectParams['requisicion_sala_id'] = $requisicionId;
                $msg .= ' La requisici&oacute;n sigue con &iacute;tems pendientes; puede continuar el cumplimiento.';
            }
        }

        return redirect()->route('crear_cumplir_requisicion_sala', $redirectParams)
            ->with('mensaje', $msg)
            ->with('cumple_pdf_token', $pdfToken);
    }

    public function imprimirPdf(Request $request, ?string $token = null)
    {
        can('cumplir-requisicion-sala');

        $token = $token ?? $request->query('token');
        try {
            $bytes = $this->pdfService->generarBytes($token);
        } catch (\Throwable $e) {
            return redirect()->route('cumplir_requisicion_sala')
                ->with('mensaje-error', 'No se pudo generar el PDF: '.$e->getMessage());
        }

        return response($bytes['contenido'], 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$bytes['nombre'].'"',
        ]);
    }

    public function imprimirCumplimientoPdf(int $id)
    {
        can('cumplir-requisicion-sala');

        try {
            $bytes = $this->pdfService->generarBytesDesdeCumplimientoId($id);
        } catch (\Throwable $e) {
            return redirect()->back()->with('mensaje-error', 'No se pudo generar el PDF: '.$e->getMessage());
        }

        return response($bytes['contenido'], 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$bytes['nombre'].'"',
        ]);
    }

    public function actualizar(Request $request, int $id)
    {
        can('cumplir-requisicion-sala');

        $result = $this->revertirService->actualizarLeyenda($id, $request->input('leyenda'));
        if (($result['mensaje'] ?? '') !== 'ok') {
            return redirect()->back()
                ->withInput()
                ->with('mensaje-error', $result['errores'] ?? 'Error al actualizar.');
        }

        return redirect()->route('consultar_cumplir_requisicion_sala', ['id' => $id])
            ->with('mensaje', 'Cumplimiento actualizado.');
    }

    public function revertir(Request $request, int $id)
    {
        can('cumplir-requisicion-sala');

        $obs = trim((string) $request->input('observacion_reversion', ''));
        $result = $this->revertirService->revertir($id, $obs);
        if (($result['mensaje'] ?? '') !== 'ok') {
            return redirect()->back()->with('mensaje-error', $result['errores'] ?? 'Error al revertir.');
        }

        return redirect()->route('consultar_cumplir_requisicion_sala', ['id' => $id])
            ->with('mensaje', 'Cumplimiento revertido. Se revirtieron las transferencias asociadas y el estado de las l&iacute;neas de requisici&oacute;n.');
    }

    private static function etiquetaDeposito(?Depmae $deposito): string
    {
        if (! $deposito) {
            return '';
        }

        return Depmae::etiquetaDesdePartes(
            (string) ($deposito->codigo ?? ''),
            (string) ($deposito->nombre ?? ''),
            (int) $deposito->id
        );
    }
}
