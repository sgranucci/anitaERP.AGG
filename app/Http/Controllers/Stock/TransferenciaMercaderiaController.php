<?php

namespace App\Http\Controllers\Stock;

use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionTransferenciaMercaderia;
use App\Models\Contable\BienUso;
use App\Models\Contable\Centrocosto;
use App\Models\Stock\Articulo;
use App\Models\Stock\Depmae;
use App\Models\Stock\Transferencia_Mercaderia_Token;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Repositories\Stock\DepmaeRepositoryInterface;
use App\Repositories\Stock\Tipotransaccion_StockRepository;
use App\Services\Stock\TransferenciaMercaderiaService;
use App\Support\Stock\TransferenciaMercaderiaLineaContableSupport;
use App\Support\Stock\TransferenciaMercaderiaAprobacionSupport;
use App\Support\Stock\TransferenciaMercaderiaDestinatarioSupport;
use App\Support\Stock\TransferenciaMercaderiaEstados;
use App\Support\Stock\TransferenciaBienUsoSupport;
use App\Support\Stock\UsuarioDepositoAutorizado;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TransferenciaMercaderiaController extends Controller
{
    public function __construct(
        private TransferenciaMercaderiaService $transferenciaService,
        private Tipotransaccion_StockRepository $tipotransaccionStockRepository,
        private DepmaeRepositoryInterface $depmaeRepository,
        private EmpresaRepositoryInterface $empresaRepository,
    ) {}

    public function index()
    {
        can('crear-transferencia-mercaderia');

        $tipotransacciones = $this->tipotransaccionStockRepository->all(['T'], ['A']);
        $defaults = $this->transferenciaService->defaultsUsuario();
        $empresa_query = $this->empresaRepository->allFiltrado();
        $empresa_id = old('empresa_id', $empresa_query->first()->id ?? null);

        $depSalida = $this->resolverDepositoDefault($defaults['deposito_salida_id'] ?? null, $empresa_id);
        $depEntrada = $this->resolverDepositoDefault($defaults['deposito_entrada_id'] ?? null, $empresa_id);
        $bienUsoDestino = $this->resolverBienUsoDefault($defaults['bien_uso_destino_id'] ?? null);
        $bienUsoOrigen = $this->resolverBienUsoDefault($defaults['bien_uso_origen_id'] ?? null);
        $bienesUsoActivos = BienUso::query()
            ->with('centrocostos:id,codigo,nombre')
            ->where('estado', 'A')
            ->orderByRaw('COALESCE(uid, hostname)')
            ->get(array_merge(TransferenciaBienUsoSupport::BIEN_USO_RELATION_COLUMNS, ['centrocosto_id']));

        $pendientesCount = count($this->transferenciaService->listarPendientes());

        $centrocosto_query = Centrocosto::query()
            ->orderBy('codigo')
            ->get(['id', 'codigo', 'nombre']);

        return view('stock.transferencia_mercaderia.index', compact(
            'tipotransacciones',
            'defaults',
            'depSalida',
            'depEntrada',
            'bienUsoDestino',
            'bienUsoOrigen',
            'bienesUsoActivos',
            'empresa_query',
            'empresa_id',
            'pendientesCount',
            'centrocosto_query',
        ));
    }

    public function pendientes()
    {
        can('listar-transferencias-pendientes');

        $pendientes = $this->transferenciaService->listarPendientes();

        return view('stock.transferencia_mercaderia.pendientes', [
            'pendientes' => $pendientes,
            'estados' => TransferenciaMercaderiaEstados::etiquetas(),
        ]);
    }

    public function destinatarios(Request $request): JsonResponse
    {
        can('crear-transferencia-mercaderia');

        if ($request->boolean('destino_bien_uso')) {
            return response()->json([
                'ok' => true,
                'opciones' => TransferenciaMercaderiaDestinatarioSupport::opcionesSelectorBienUso(),
            ]);
        }

        $depositoId = (int) $request->input('deposito_entrada_id', 0);
        if ($depositoId <= 0) {
            return response()->json(['ok' => false, 'opciones' => []]);
        }

        return response()->json([
            'ok' => true,
            'opciones' => TransferenciaMercaderiaDestinatarioSupport::opcionesSelector($depositoId),
        ]);
    }

    private function resolverDepositoDefault($id, $empresaId = null): ?Depmae
    {
        $id = (int) $id;
        if ($id <= 0) {
            return null;
        }

        $query = Depmae::query()->whereKey($id);
        $empresaId = (int) $empresaId;
        if ($empresaId > 0) {
            $query->paraEmpresa($empresaId);
        }

        UsuarioDepositoAutorizado::aplicarFiltroQuery($query);

        return $query->first();
    }

    private function resolverBienUsoDefault($id): ?BienUso
    {
        $id = (int) $id;
        if ($id <= 0) {
            return null;
        }

        return BienUso::query()->whereKey($id)->where('estado', 'A')->first();
    }

    public function preferencias(Request $request): JsonResponse
    {
        can('crear-transferencia-mercaderia');

        $this->transferenciaService->persistirPreferencias($request->only([
            'deposito_salida_id',
            'deposito_entrada_id',
            'bien_uso_destino_id',
            'bien_uso_origen_id',
            'tipotransaccion_id',
            'tipotransaccion_stock_id',
        ]));

        return response()->json(['ok' => true]);
    }

    public function inventario(Request $request): JsonResponse
    {
        can('crear-transferencia-mercaderia');

        if ($request->boolean('origen_bien_uso')) {
            $bienId = (int) $request->input('bien_uso_origen_id', 0);
            if ($bienId <= 0) {
                return response()->json(['ok' => false, 'mensaje' => 'Seleccione bien de uso de origen.'], 422);
            }

            try {
                $filas = $this->transferenciaService->inventarioBienUsoOrigen($bienId);
            } catch (\Throwable $e) {
                return response()->json(['ok' => false, 'mensaje' => $e->getMessage()], 422);
            }

            return response()->json(['ok' => true, 'filas' => $filas]);
        }

        $depositoId = (int) $request->input('deposito_salida_id', 0);
        if ($depositoId <= 0) {
            return response()->json(['ok' => false, 'mensaje' => 'Seleccione depósito de salida.'], 422);
        }

        try {
            $filas = $this->transferenciaService->inventarioDepositoSalida($depositoId);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'mensaje' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true, 'filas' => $filas]);
    }

    public function saldoArticulo(Request $request): JsonResponse
    {
        can('crear-transferencia-mercaderia');

        $articuloId = (int) $request->input('articulo_id', 0);
        $depositoId = (int) $request->input('deposito_id', 0);

        if ($articuloId <= 0 || $depositoId <= 0) {
            return response()->json(['ok' => false, 'mensaje' => 'Artículo o depósito inválido.'], 422);
        }

        try {
            $saldo = $this->transferenciaService->saldoArticuloEnDeposito($articuloId, $depositoId);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'mensaje' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true, 'saldo' => $saldo]);
    }

    public function validarLineaContable(Request $request): JsonResponse
    {
        can('crear-transferencia-mercaderia');

        $articuloId = (int) $request->input('articulo_id', 0);
        $depositoOrigenId = (int) $request->input('deposito_salida_id', 0);
        $empresaId = (int) $request->input('empresa_id', 0);
        $tipoId = (int) $request->input('tipotransaccion_stock_id', 0);

        if ($articuloId <= 0) {
            return response()->json(['ok' => false, 'mensaje' => 'Artículo no indicado.'], 422);
        }

        $tipo = $tipoId > 0
            ? $this->tipotransaccionStockRepository->find($tipoId)
            : null;

        if ($tipo === null || ! TransferenciaMercaderiaAprobacionSupport::manejaContabilidad($tipo)) {
            return response()->json([
                'ok' => true,
                'permitido' => true,
                'contabilidad_activa' => false,
                'familia' => TransferenciaMercaderiaLineaContableSupport::FAMILIA_NO_CONTABILIZABLE,
                'motivo' => '',
            ]);
        }

        if ($empresaId <= 0) {
            return response()->json(['ok' => false, 'mensaje' => 'Seleccione empresa.'], 422);
        }

        $articulo = Articulo::query()
            ->with('articulo_cuentacontables')
            ->find($articuloId);

        if ($articulo === null) {
            return response()->json(['ok' => false, 'mensaje' => 'Artículo no encontrado.'], 422);
        }

        $resultado = TransferenciaMercaderiaLineaContableSupport::validarLinea(
            $articulo,
            $empresaId,
            $depositoOrigenId
        );

        return response()->json([
            'ok' => true,
            'contabilidad_activa' => true,
            'permitido' => $resultado['permitido'],
            'familia' => $resultado['familia'],
            'motivo' => $resultado['motivo'],
            'deposito_recepcion_id' => $resultado['deposito_recepcion_id'],
            'deposito_recepcion_codigo' => $resultado['deposito_recepcion_codigo'],
        ]);
    }

    public function guardar(ValidacionTransferenciaMercaderia $request): JsonResponse
    {
        can('crear-transferencia-mercaderia');

        $lineas = [];
        foreach ($request->input('lineas', []) as $linea) {
            $cantidad = (float) ($linea['cantidad'] ?? 0);
            if ($cantidad > 0) {
                $lineas[] = [
                    'articulo_id' => (int) $linea['articulo_id'],
                    'cantidad' => $cantidad,
                ];
            }
        }

        $resultado = $this->transferenciaService->grabarTransferencia(
            $request->only([
                'empresa_id',
                'deposito_salida_id',
                'deposito_entrada_id',
                'bien_uso_destino_id',
                'bien_uso_origen_id',
                'tipotransaccion_id',
                'tipotransaccion_stock_id',
                'usuario_destino_id',
                'centrocosto_destino_id',
                'observacion',
            ]),
            $lineas
        );

        $status = $resultado['ok'] ? 200 : 422;

        return response()->json($resultado, $status);
    }

    public function aprobar(int $id): JsonResponse
    {
        can('aprobar-transferencia-mercaderia');

        try {
            $transferencia = $this->transferenciaService->aprobarRecepcion($id);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'mensaje' => $e->getMessage()], 422);
        }

        return response()->json([
            'ok' => true,
            'mensaje' => 'Transferencia '.$transferencia->codigo.' confirmada.',
        ]);
    }

    public function rechazar(Request $request, int $id): JsonResponse
    {
        can('aprobar-transferencia-mercaderia');

        try {
            $transferencia = $this->transferenciaService->rechazarRecepcion(
                $id,
                null,
                trim((string) $request->input('motivo', '')) ?: null
            );
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'mensaje' => $e->getMessage()], 422);
        }

        return response()->json([
            'ok' => true,
            'mensaje' => 'Transferencia '.$transferencia->codigo.' rechazada.',
        ]);
    }

    public function aprobarPublico(string $token)
    {
        return $this->procesarAccionPublica($token, Transferencia_Mercaderia_Token::ACCION_APROBAR);
    }

    public function rechazarPublico(Request $request, string $token)
    {
        return $this->procesarAccionPublica(
            $token,
            Transferencia_Mercaderia_Token::ACCION_RECHAZAR,
            trim((string) $request->input('motivo', '')) ?: null
        );
    }

    public function verPublico(string $token)
    {
        $row = Transferencia_Mercaderia_Token::query()->where('token', $token)->first();
        if ($row === null || ! $row->estaActivo()) {
            return response()->view('stock.transferencia_mercaderia.publico_resultado', [
                'titulo' => 'Enlace no válido',
                'detalle' => 'Este enlace ya fue utilizado, fue invalidado o expiró.',
                'tipo' => 'error',
            ], 410);
        }

        $transferencia = $this->transferenciaService->buscar((int) $row->transferencia_mercaderia_id);
        $tokensActivos = Transferencia_Mercaderia_Token::query()
            ->where('transferencia_mercaderia_id', $transferencia->id)
            ->whereNull('usado_el')
            ->get()
            ->keyBy('accion');
        $tokenAprobar = $tokensActivos[Transferencia_Mercaderia_Token::ACCION_APROBAR]->token ?? null;
        $tokenRechazar = $tokensActivos[Transferencia_Mercaderia_Token::ACCION_RECHAZAR]->token ?? null;

        return view('stock.transferencia_mercaderia.publico_ver', compact(
            'transferencia',
            'token',
            'tokenAprobar',
            'tokenRechazar',
        ));
    }

    private function procesarAccionPublica(string $token, string $accion, ?string $motivo = null)
    {
        try {
            $row = $this->transferenciaService->consumirToken($token, $accion);
        } catch (\Throwable $e) {
            return response()->view('stock.transferencia_mercaderia.publico_resultado', [
                'titulo' => 'Acción no procesada',
                'detalle' => $e->getMessage(),
                'tipo' => 'error',
            ], 410);
        }

        try {
            if ($accion === Transferencia_Mercaderia_Token::ACCION_APROBAR) {
                $this->transferenciaService->aprobarRecepcion(
                    (int) $row->transferencia_mercaderia_id,
                    (int) $row->usuario_destino_id,
                    'Aprobado por enlace de correo'
                );
                $titulo = 'Transferencia aprobada';
                $detalle = 'Se registró el ingreso en el depósito destino.';
                $tipo = 'ok';
            } else {
                $this->transferenciaService->rechazarRecepcion(
                    (int) $row->transferencia_mercaderia_id,
                    (int) $row->usuario_destino_id,
                    $motivo ?: 'Rechazado por enlace de correo'
                );
                $titulo = 'Transferencia rechazada';
                $detalle = 'Se revirtió la salida del depósito origen y se notificó al remitente.';
                $tipo = 'ok';
            }
        } catch (\Throwable $e) {
            return response()->view('stock.transferencia_mercaderia.publico_resultado', [
                'titulo' => 'No se pudo completar',
                'detalle' => $e->getMessage(),
                'tipo' => 'error',
            ], 422);
        }

        return response()->view('stock.transferencia_mercaderia.publico_resultado', compact('titulo', 'detalle', 'tipo'));
    }
}
