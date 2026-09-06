<?php

namespace App\Http\Controllers\Stock;

use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionTransferenciaMercaderia;
use App\Models\Contable\BienUso;
use App\Models\Stock\Articulo;
use App\Models\Stock\Depmae;
use App\Models\Stock\Transferencia_Mercaderia_Token;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Repositories\Stock\DepmaeRepositoryInterface;
use App\Repositories\Stock\Tipotransaccion_StockRepository;
use App\Services\Stock\TransferenciaMercaderiaService;
use App\Support\Seguridad\UsuarioOperativoSupport;
use App\Support\Stock\CodigoBarrasImagenSupport;
use App\Support\Stock\TransferenciaBienUsoSupport;
use App\Support\Stock\TransferenciaMercaderiaAprobacionSupport;
use App\Support\Stock\TransferenciaMercaderiaDepositoRecepcionSupport;
use App\Support\Stock\TransferenciaMercaderiaDestinatarioSupport;
use App\Support\Stock\TransferenciaMercaderiaLineaContableSupport;
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
        $empresa_query = $this->empresaRepository->allFiltrado();
        $empresaIdsPermitidos = $empresa_query->pluck('id')->map(static fn ($id) => (int) $id)->all();
        $defaults = $this->transferenciaService->defaultsUsuario(
            old('empresa_id') !== null && old('empresa_id') !== '' ? (int) old('empresa_id') : null,
            $empresaIdsPermitidos
        );
        $empresa_id = $defaults['empresa_id'] ?? $empresa_query->first()->id ?? null;

        $depSalida = $this->resolverDepositoDefault($defaults['deposito_salida_id'] ?? null, $empresa_id, true);
        $depEntrada = $this->resolverDepositoDefault($defaults['deposito_entrada_id'] ?? null, $empresa_id, false);
        $bienUsoDestino = $this->resolverBienUsoDefault($defaults['bien_uso_destino_id'] ?? null);
        $bienUsoOrigen = $this->resolverBienUsoDefault($defaults['bien_uso_origen_id'] ?? null);
        $bienesUsoActivos = BienUso::query()
            ->with('centrocostos:id,codigo,nombre')
            ->where('estado', 'A')
            ->orderByRaw('COALESCE(uid, hostname)')
            ->get(array_merge(TransferenciaBienUsoSupport::BIEN_USO_RELATION_COLUMNS, ['centrocosto_id']));

        $pendientesCount = count($this->transferenciaService->listarPendientes());

        $tipoDefault = $tipotransacciones->firstWhere('id', (int) ($defaults['tipotransaccion_stock_id'] ?? 0));
        $mostrarPanelDestinatario = TransferenciaMercaderiaAprobacionSupport::requiereAprobacion($tipoDefault)
            || TransferenciaMercaderiaAprobacionSupport::avisoOpcional($tipoDefault);
        $opcionesDestinatario = [];
        if ($mostrarPanelDestinatario && (int) optional($depEntrada)->id > 0) {
            $opcionesDestinatario = TransferenciaMercaderiaDestinatarioSupport::opcionesSelector((int) $depEntrada->id);
        }

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
            'mostrarPanelDestinatario',
            'opcionesDestinatario',
        ));
    }

    public function pendientes()
    {
        can('listar-transferencias-pendientes');

        return redirect()->to(url('mis-aprobaciones').'?fuente=transferencia');
    }

    public function destinatarios(Request $request): JsonResponse
    {
        $this->autorizarConsultaDestinatarios();

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

    public function validarDestinatario(Request $request): JsonResponse
    {
        $this->autorizarConsultaDestinatarios();

        $usuarioId = (int) $request->input('usuario_id', 0);
        if ($usuarioId <= 0) {
            return response()->json(['ok' => false, 'mensaje' => 'Indique un usuario.']);
        }

        $usuario = UsuarioOperativoSupport::find($usuarioId);
        if ($usuario === null) {
            return response()->json(['ok' => false, 'mensaje' => 'Usuario no encontrado o suspendido.']);
        }

        if ($request->boolean('destino_bien_uso')) {
            if (TransferenciaMercaderiaDestinatarioSupport::resolverUsuarioDestinoBienUso($usuarioId) === null) {
                return response()->json([
                    'ok' => false,
                    'mensaje' => 'El usuario no tiene email configurado o no puede aprobar transferencias a bien de uso.',
                ]);
            }

            return response()->json([
                'ok' => true,
                'nombre' => (string) $usuario->nombre,
                'email' => (string) $usuario->email,
            ]);
        }

        $depositoId = (int) $request->input('deposito_entrada_id', 0);
        if ($depositoId <= 0) {
            return response()->json(['ok' => false, 'mensaje' => 'Seleccione el depósito destino.']);
        }

        if (! TransferenciaMercaderiaDestinatarioSupport::usuarioValidoDestinatarioExplicito($usuario)) {
            return response()->json([
                'ok' => false,
                'mensaje' => 'El usuario no está activo o no tiene email configurado.',
            ]);
        }

        return response()->json([
            'ok' => true,
            'nombre' => (string) $usuario->nombre,
            'email' => (string) $usuario->email,
        ]);
    }

    private function autorizarConsultaDestinatarios(): void
    {
        if (can('crear-transferencia-mercaderia', false)
            || can('crear-movimientos-de-stock', false)
            || can('editar-movimientos-de-stock', false)
            || can('actualizar-movimientos-de-stock', false)) {
            return;
        }

        abort(403);
    }

    private function resolverDepositoDefault($id, $empresaId = null, bool $filtrarUsuario = true): ?Depmae
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

        if ($filtrarUsuario) {
            UsuarioDepositoAutorizado::aplicarFiltroQuery($query);
        }

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
            'empresa_id',
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

    public function resolverArticulo(Request $request): JsonResponse
    {
        can('crear-transferencia-mercaderia');

        $codigo = trim((string) $request->input('codigo', ''));
        $depositoId = (int) $request->input('deposito_id', 0);

        if ($codigo === '') {
            return response()->json(['ok' => false, 'mensaje' => 'Ingrese o pickee el SKU o el código de barras.'], 422);
        }
        if ($depositoId <= 0) {
            return response()->json(['ok' => false, 'mensaje' => 'Seleccione depósito de salida.'], 422);
        }

        try {
            $resultado = $this->transferenciaService->resolverArticuloPickeo($codigo, $depositoId);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'mensaje' => $e->getMessage()], 422);
        }

        return response()->json($resultado, ! empty($resultado['ok']) ? 200 : 422);
    }

    public function decodificarFoto(Request $request): JsonResponse
    {
        can('crear-transferencia-mercaderia');

        $foto = $request->file('foto');
        if (! $foto || ! $foto->isValid()) {
            return response()->json([
                'ok' => false,
                'codigos' => [],
                'mensaje' => 'No llegó la foto.',
            ], 422);
        }

        $mime = (string) $foto->getMimeType();
        if (! str_starts_with($mime, 'image/')) {
            return response()->json([
                'ok' => false,
                'codigos' => [],
                'mensaje' => 'El archivo no es una imagen.',
            ], 422);
        }

        try {
            $resultado = CodigoBarrasImagenSupport::decodificarDesdePath((string) $foto->getRealPath());
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'codigos' => [],
                'mensaje' => $e->getMessage(),
            ], 422);
        }

        return response()->json($resultado, ! empty($resultado['ok']) ? 200 : 422);
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

        if ($empresaId <= 0) {
            return response()->json(['ok' => false, 'mensaje' => 'Seleccione empresa.'], 422);
        }

        $articulo = Articulo::query()
            ->with('articulo_cuentacontables')
            ->find($articuloId);

        if ($articulo === null) {
            return response()->json(['ok' => false, 'mensaje' => 'Artículo no encontrado.'], 422);
        }

        $tipo = $tipoId > 0
            ? $this->tipotransaccionStockRepository->find($tipoId)
            : null;
        $tipoTrcont = $this->tipotransaccionStockRepository
            ->all(['T'], ['A'])
            ->first(static fn ($item): bool => strtoupper(trim((string) ($item->abreviatura ?? ''))) === 'TRCONT'
                && (bool) ($item->maneja_contabilidad ?? false));
        $familia = TransferenciaMercaderiaLineaContableSupport::resolverFamilia($articulo, $empresaId);
        $esContabilizable = $familia !== TransferenciaMercaderiaLineaContableSupport::FAMILIA_NO_CONTABILIZABLE;
        $sinRecepcionDeposito = $familia === TransferenciaMercaderiaLineaContableSupport::FAMILIA_OTROS_ACTIVOS
            && $depositoOrigenId > 0
            && ! TransferenciaMercaderiaDepositoRecepcionSupport::existeEnDeposito(
                $articuloId,
                $empresaId,
                $depositoOrigenId
            );

        if ($tipo === null || ! TransferenciaMercaderiaAprobacionSupport::manejaContabilidad($tipo)) {
            return response()->json([
                'ok' => true,
                'permitido' => true,
                'contabilidad_activa' => false,
                'es_contabilizable' => $esContabilizable,
                'familia' => $familia,
                'motivo' => '',
                'tipo_trcont_id' => $tipoTrcont?->id,
                'sin_recepcion_deposito' => $sinRecepcionDeposito,
            ]);
        }

        $resultado = TransferenciaMercaderiaLineaContableSupport::validarLinea(
            $articulo,
            $empresaId,
            $depositoOrigenId
        );

        return response()->json([
            'ok' => true,
            'contabilidad_activa' => true,
            'es_contabilizable' => $esContabilizable,
            'permitido' => $resultado['permitido'],
            'familia' => $resultado['familia'],
            'motivo' => $resultado['motivo'],
            'tipo_trcont_id' => $tipoTrcont?->id,
            'deposito_recepcion_id' => $resultado['deposito_recepcion_id'],
            'deposito_recepcion_codigo' => $resultado['deposito_recepcion_codigo'],
            'sin_recepcion_deposito' => ! empty($resultado['sin_recepcion_deposito']),
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
                    'caja' => (float) ($linea['caja'] ?? 0),
                    'pieza' => (float) ($linea['pieza'] ?? 0),
                ];
            }
        }

        $cabecera = $request->only([
            'empresa_id',
            'deposito_salida_id',
            'deposito_entrada_id',
            'bien_uso_destino_id',
            'bien_uso_origen_id',
            'tipotransaccion_id',
            'tipotransaccion_stock_id',
            'usuario_destino_id',
            'centrocosto_destino_id',
            'enviar_aviso',
            'observacion',
        ]);
        $cabecera['seleccion_automatica_trcont'] = true;

        $resultado = $this->transferenciaService->grabarTransferencia(
            $cabecera,
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
