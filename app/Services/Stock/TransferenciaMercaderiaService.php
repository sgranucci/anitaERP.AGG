<?php

namespace App\Services\Stock;

use App\Models\Stock\Articulo;
use App\Models\Stock\Depmae;
use App\Models\Stock\Tipotransaccion_Stock;
use App\Models\Stock\Transferencia_Mercaderia;
use App\Models\Stock\Transferencia_Mercaderia_Articulo;
use App\Models\Stock\Transferencia_Mercaderia_Token;
use App\Repositories\Stock\Tipotransaccion_StockRepositoryInterface;
use App\Services\Configuracion\ModuloAvisoService;
use App\Support\Contable\PeriodoContableCierreSupport;
use App\Support\Stock\TransferenciaMercaderiaAprobacionSupport;
use App\Support\Stock\TransferenciaMercaderiaDestinatarioSupport;
use App\Support\Stock\TransferenciaMercaderiaEstados;
use App\Support\Stock\TransferenciaMercaderiaLineaSupport;
use App\Support\Stock\TransferenciaMercaderiaSignoSupport;
use App\Support\Stock\UsuarioDepositoAutorizado;
use Auth;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class TransferenciaMercaderiaService
{
    public const CACHE_DEPOSITO_SALIDA = 'transferencia-deposito-salida';

    public const CACHE_DEPOSITO_ENTRADA = 'transferencia-deposito-entrada';

    public const CACHE_TIPO_TRANSACCION = 'transferencia-tipotransaccion';

    public function __construct(
        private MovimientoStockService $movimientoStockService,
        private Tipotransaccion_StockRepositoryInterface $tipotransaccionStockRepository,
        private StkdepSaldoAnitaService $stkdepSaldoAnitaService,
        private ModuloAvisoService $moduloAvisoService,
        private TransferenciaMercaderiaAsientoService $asientoService,
    ) {}

    public function defaultsUsuario(): array
    {
        return [
            'deposito_salida_id' => cache()->get(generaKey(self::CACHE_DEPOSITO_SALIDA)),
            'deposito_entrada_id' => cache()->get(generaKey(self::CACHE_DEPOSITO_ENTRADA)),
            'tipotransaccion_stock_id' => $this->resolverTipoTransaccionStockIdDefault(),
        ];
    }

    public function persistirPreferencias(array $data): void
    {
        if (! empty($data['deposito_salida_id'])) {
            Cache::forever(generaKey(self::CACHE_DEPOSITO_SALIDA), (int) $data['deposito_salida_id']);
        }
        if (! empty($data['deposito_entrada_id'])) {
            Cache::forever(generaKey(self::CACHE_DEPOSITO_ENTRADA), (int) $data['deposito_entrada_id']);
        }
        $tipoStockId = (int) ($data['tipotransaccion_stock_id'] ?? $data['tipotransaccion_id'] ?? 0);
        if ($tipoStockId > 0) {
            Cache::forever(generaKey(self::CACHE_TIPO_TRANSACCION), $tipoStockId);
        }
    }

    /**
     * @return list<array{sku_anita: string, saldo: float, articulo_id: int|null, sku: string|null, descripcion: string|null}>
     */
    public function inventarioDepositoSalida(int $depositoSalidaId): array
    {
        $this->assertDepositoAutorizado($depositoSalidaId);

        return $this->stkdepSaldoAnitaService->inventarioPorDepositoId($depositoSalidaId);
    }

    /** @return list<Transferencia_Mercaderia> */
    public function listarPendientes(?int $depositoDestinoId = null): array
    {
        $query = Transferencia_Mercaderia::query()
            ->with(['depositoOrigen', 'depositoDestino', 'usuarioOrigen', 'articulos.articuloOrigen', 'articulos.articuloDestino'])
            ->where('estado', TransferenciaMercaderiaEstados::PENDIENTE_RECEPCION)
            ->orderByDesc('fecha')
            ->orderByDesc('id');

        if ($depositoDestinoId > 0) {
            $query->where('deposito_destino_id', $depositoDestinoId);
        }

        if (UsuarioDepositoAutorizado::tieneRestriccion()) {
            $ids = UsuarioDepositoAutorizado::idsRestringidos() ?? [];
            if ($ids !== []) {
                $query->whereIn('deposito_destino_id', $ids);
            }
        }

        return $query->get()->all();
    }

    public function buscar(int $id): Transferencia_Mercaderia
    {
        return Transferencia_Mercaderia::query()
            ->with([
                'depositoOrigen',
                'depositoDestino',
                'tipotransaccion_stock',
                'usuarioOrigen',
                'usuarioDestino',
                'usuarioAprobador',
                'articulos.articuloOrigen',
                'articulos.articuloDestino',
            ])
            ->findOrFail($id);
    }

    /**
     * @param  list<array{articulo_id: int, cantidad: float}>  $lineas
     * @return array{ok: bool, mensaje: string, codigo?: string, transferencia_id?: int, requiere_aprobacion?: bool}
     */
    public function grabarTransferencia(array $cabecera, array $lineas): array
    {
        $depositoSalidaId = (int) ($cabecera['deposito_salida_id'] ?? 0);
        $depositoEntradaId = (int) ($cabecera['deposito_entrada_id'] ?? 0);
        $tipotransaccionId = (int) ($cabecera['tipotransaccion_stock_id'] ?? $cabecera['tipotransaccion_id'] ?? 0);
        $empresaId = (int) ($cabecera['empresa_id'] ?? 0);
        $usuarioDestinoId = (int) ($cabecera['usuario_destino_id'] ?? 0) ?: null;

        if ($depositoSalidaId <= 0 || $depositoEntradaId <= 0) {
            return ['ok' => false, 'mensaje' => 'Debe indicar depósito de salida y de entrada.'];
        }
        if ($depositoSalidaId === $depositoEntradaId) {
            return ['ok' => false, 'mensaje' => 'El depósito de salida y el de entrada deben ser distintos.'];
        }
        if ($tipotransaccionId <= 0) {
            return ['ok' => false, 'mensaje' => 'Debe seleccionar un tipo de transacción.'];
        }
        if ($lineas === []) {
            return ['ok' => false, 'mensaje' => 'Indique al menos un artículo con cantidad a transferir.'];
        }

        $this->assertDepositoAutorizado($depositoSalidaId);
        if (! Depmae::autorizadoParaUsuarioYEmpresa($depositoSalidaId, $empresaId)) {
            return ['ok' => false, 'mensaje' => 'Depósito de salida no autorizado para su usuario o empresa.'];
        }
        if (! Depmae::autorizadoParaUsuarioYEmpresa($depositoEntradaId, $empresaId)) {
            return ['ok' => false, 'mensaje' => 'Depósito de entrada no autorizado para la empresa seleccionada.'];
        }

        $tipoTransferencia = $this->tipotransaccionStockRepository->find($tipotransaccionId);
        $this->validarTipoTransferencia($tipoTransferencia);

        $depositoSalida = Depmae::query()->findOrFail($depositoSalidaId);
        $depositoEntrada = Depmae::query()->findOrFail($depositoEntradaId);

        $ahora = Carbon::now();
        $fecha = $ahora->format('Y-m-d');

        PeriodoContableCierreSupport::assertOperacionPermitida(
            (int) ($depositoSalida->empresa_id ?? $empresaId),
            $fecha,
            PeriodoContableCierreSupport::ALCANCE_TRANSFERENCIA
        );

        $requiereAprobacion = TransferenciaMercaderiaAprobacionSupport::requiereAprobacion($tipoTransferencia);
        $usuarioDestino = TransferenciaMercaderiaDestinatarioSupport::resolverUsuarioDestino(
            $depositoEntradaId,
            $usuarioDestinoId
        );

        if ($requiereAprobacion && $usuarioDestino === null) {
            return [
                'ok' => false,
                'mensaje' => 'La transferencia requiere aprobación: indique un usuario destino o configure un encargado del depósito de entrada.',
            ];
        }

        $lote = (int) $ahora->format('ymdHis');
        $codigoBase = 'TR-'.$ahora->format('YmdHis');

        $this->persistirPreferencias($cabecera);

        try {
            $lineasResueltas = $this->resolverLineas($lineas, $depositoEntrada, $empresaId);
            $this->validarCantidadesContraSaldo($depositoSalidaId, $lineasResueltas);
        } catch (\Throwable $e) {
            return ['ok' => false, 'mensaje' => $e->getMessage()];
        }

        try {
            return DB::transaction(function () use (
                $cabecera,
                $lineasResueltas,
                $tipoTransferencia,
                $depositoSalida,
                $depositoEntrada,
                $depositoSalidaId,
                $depositoEntradaId,
                $empresaId,
                $fecha,
                $lote,
                $codigoBase,
                $requiereAprobacion,
                $usuarioDestino
            ) {
                $transferencia = Transferencia_Mercaderia::create([
                    'codigo' => $codigoBase,
                    'lote' => $lote,
                    'empresa_id' => $empresaId > 0 ? $empresaId : (int) $depositoSalida->empresa_id,
                    'deposito_origen_id' => $depositoSalidaId,
                    'deposito_destino_id' => $depositoEntradaId,
                    'tipotransaccion_stock_id' => $tipoTransferencia->id,
                    'estado' => $requiereAprobacion
                        ? TransferenciaMercaderiaEstados::PENDIENTE_RECEPCION
                        : TransferenciaMercaderiaEstados::CONFIRMADA,
                    'requiere_aprobacion' => $requiereAprobacion,
                    'usuario_origen_id' => Auth::id(),
                    'usuario_destino_id' => $usuarioDestino?->id,
                    'fecha' => $fecha,
                    'observacion' => trim((string) ($cabecera['observacion'] ?? '')) ?: null,
                ]);

                $this->persistirLineasTransferencia($transferencia, $lineasResueltas);

                $payloadSalida = $this->armarPayloadMovimiento($lineasResueltas, 'salida');
                $salida = $this->grabarMovimiento(
                    $tipoTransferencia->id,
                    $depositoSalidaId,
                    $fecha,
                    $lote,
                    $codigoBase.'-S',
                    'Transferencia a '.$depositoEntrada->nombre,
                    $payloadSalida,
                    esSalida: true
                );
                $transferencia->movimientostock_salida_id = (int) $salida['id'];
                $transferencia->save();

                if (! $requiereAprobacion) {
                    $payloadEntrada = $this->armarPayloadMovimiento($lineasResueltas, 'entrada');
                    $entrada = $this->grabarMovimiento(
                        $tipoTransferencia->id,
                        $depositoEntradaId,
                        $fecha,
                        $lote,
                        $codigoBase.'-E',
                        'Transferencia desde '.$depositoSalida->nombre,
                        $payloadEntrada,
                        esSalida: false
                    );
                    $transferencia->movimientostock_entrada_id = (int) $entrada['id'];
                    $transferencia->save();

                    if (TransferenciaMercaderiaAprobacionSupport::manejaContabilidad($tipoTransferencia)) {
                        $asientoId = $this->asientoService->generarDesdeTransferencia($transferencia->fresh(['articulos']));
                        if ($asientoId > 0) {
                            $transferencia->asiento_id = $asientoId;
                            $transferencia->save();
                        }
                    }

                    $this->moduloAvisoService->enviar('stock', 'transferencia_confirmada', (int) $transferencia->id);
                } else {
                    $this->generarTokensYNotificarAprobacion($transferencia->fresh(['articulos', 'depositoOrigen', 'depositoDestino']));
                }

                $mensaje = $requiereAprobacion
                    ? 'Transferencia enviada. Pendiente de aprobación por el depósito destino ('.count($lineasResueltas).' artículos).'
                    : 'Transferencia registrada ('.count($lineasResueltas).' artículos).';

                return [
                    'ok' => true,
                    'mensaje' => $mensaje,
                    'codigo' => $codigoBase,
                    'transferencia_id' => (int) $transferencia->id,
                    'requiere_aprobacion' => $requiereAprobacion,
                ];
            });
        } catch (\Throwable $e) {
            Log::warning('TransferenciaMercaderia: error al grabar', ['error' => $e->getMessage()]);

            return ['ok' => false, 'mensaje' => $e->getMessage()];
        }
    }

    public function aprobarRecepcion(int $id, ?int $usuarioAprobadorId = null, ?string $observaciones = null): Transferencia_Mercaderia
    {
        $transferencia = $this->buscar($id);
        if ($transferencia->estado !== TransferenciaMercaderiaEstados::PENDIENTE_RECEPCION) {
            throw new \RuntimeException('Solo se puede aprobar una transferencia pendiente de recepción.');
        }

        $usuarioAprobadorId = $usuarioAprobadorId ?? (int) Auth::id();
        if (! UsuarioDepositoAutorizado::depositoAutorizado((int) $transferencia->deposito_destino_id)
            && ! TransferenciaMercaderiaDestinatarioSupport::usuarioPuedeRecibirAprobacion(
                (int) $transferencia->deposito_destino_id,
                \App\Models\Seguridad\Usuario::query()->findOrFail($usuarioAprobadorId)
            )) {
            throw new \RuntimeException('No está autorizado para aprobar transferencias en el depósito destino.');
        }

        PeriodoContableCierreSupport::assertOperacionPermitida(
            (int) $transferencia->empresa_id,
            $transferencia->fecha?->format('Y-m-d') ?? now()->format('Y-m-d'),
            PeriodoContableCierreSupport::ALCANCE_TRANSFERENCIA,
            $usuarioAprobadorId
        );

        return DB::transaction(function () use ($transferencia, $usuarioAprobadorId, $observaciones) {
            $lineas = $transferencia->articulos->all();
            $payloadEntrada = $this->armarPayloadMovimientoDesdePersistidas($lineas, 'entrada');
            $tipo = $transferencia->tipotransaccion_stock;

            $entrada = $this->grabarMovimiento(
                (int) $transferencia->tipotransaccion_stock_id,
                (int) $transferencia->deposito_destino_id,
                $transferencia->fecha?->format('Y-m-d') ?? now()->format('Y-m-d'),
                (int) $transferencia->lote,
                $transferencia->codigo.'-E',
                'Transferencia desde '.optional($transferencia->depositoOrigen)->nombre,
                $payloadEntrada,
                esSalida: false
            );

            $transferencia->movimientostock_entrada_id = (int) $entrada['id'];
            $transferencia->usuario_aprobador_id = $usuarioAprobadorId;
            $transferencia->fecha_aprobacion = now()->toDateString();
            $transferencia->estado = TransferenciaMercaderiaEstados::CONFIRMADA;
            if ($observaciones) {
                $transferencia->observacion = trim((string) $transferencia->observacion."\n".$observaciones);
            }
            $transferencia->save();

            $this->invalidarTokens($transferencia);

            if (TransferenciaMercaderiaAprobacionSupport::manejaContabilidad($tipo)) {
                $asientoId = $this->asientoService->generarDesdeTransferencia($transferencia->fresh(['articulos']));
                if ($asientoId > 0) {
                    $transferencia->asiento_id = $asientoId;
                    $transferencia->save();
                }
            }

            $this->moduloAvisoService->enviar('stock', 'transferencia_confirmada', (int) $transferencia->id);

            return $transferencia->fresh();
        });
    }

    public function rechazarRecepcion(int $id, ?int $usuarioId = null, ?string $motivo = null): Transferencia_Mercaderia
    {
        $transferencia = $this->buscar($id);
        if ($transferencia->estado !== TransferenciaMercaderiaEstados::PENDIENTE_RECEPCION) {
            throw new \RuntimeException('Solo se puede rechazar una transferencia pendiente de recepción.');
        }

        $usuarioId = $usuarioId ?? (int) Auth::id();

        return DB::transaction(function () use ($transferencia, $usuarioId, $motivo) {
            $lineas = $transferencia->articulos->all();
            $payloadReverso = $this->armarPayloadMovimientoDesdePersistidas($lineas, 'salida');

            $this->grabarMovimiento(
                (int) $transferencia->tipotransaccion_stock_id,
                (int) $transferencia->deposito_origen_id,
                now()->format('Y-m-d'),
                (int) $transferencia->lote,
                $transferencia->codigo.'-RV',
                'Reverso transferencia rechazada',
                $payloadReverso,
                esSalida: false
            );

            $transferencia->usuario_aprobador_id = $usuarioId;
            $transferencia->motivo_rechazo = $motivo;
            $transferencia->estado = TransferenciaMercaderiaEstados::RECHAZADA;
            $transferencia->save();

            $this->invalidarTokens($transferencia);
            $this->moduloAvisoService->enviar('stock', 'transferencia_rechazada', (int) $transferencia->id, [
                'motivo' => $motivo,
            ]);

            return $transferencia->fresh();
        });
    }

    public function consumirToken(string $token, string $accionEsperada): Transferencia_Mercaderia_Token
    {
        $row = Transferencia_Mercaderia_Token::query()->where('token', $token)->first();
        if ($row === null) {
            throw new \RuntimeException('Enlace inválido.');
        }
        if ($row->accion !== $accionEsperada) {
            throw new \RuntimeException('Enlace para una acción distinta.');
        }
        if (! $row->estaActivo()) {
            throw new \RuntimeException('Este enlace ya fue utilizado o expiró.');
        }

        $row->usado_el = now();
        $row->save();

        return $row;
    }

    private function assertDepositoAutorizado(int $depositoId): void
    {
        if ($depositoId <= 0) {
            throw new \InvalidArgumentException('Depósito inválido.');
        }
        if (! UsuarioDepositoAutorizado::depositoAutorizado($depositoId)) {
            throw new \InvalidArgumentException('No está autorizado para operar el depósito seleccionado.');
        }
    }

    /**
     * @param  list<array{articulo_id: int, cantidad: float}>  $lineas
     * @return list<array<string, mixed>>
     */
    private function resolverLineas(array $lineas, Depmae $depositoEntrada, int $empresaId): array
    {
        $resueltas = [];
        $item = 0;

        foreach ($lineas as $linea) {
            $articuloId = (int) ($linea['articulo_id'] ?? 0);
            $cantidad = (float) ($linea['cantidad'] ?? 0);
            if ($articuloId <= 0 || $cantidad <= 0) {
                continue;
            }

            $articulo = Articulo::query()->findOrFail($articuloId);
            $conv = TransferenciaMercaderiaLineaSupport::resolverLinea(
                $articulo,
                $depositoEntrada,
                $cantidad,
                $empresaId > 0 ? $empresaId : null
            );
            $item++;
            $resueltas[] = array_merge($conv, ['item' => $item]);
        }

        if ($resueltas === []) {
            throw new \InvalidArgumentException('No hay líneas válidas para transferir.');
        }

        return $resueltas;
    }

    /**
     * @param  list<array<string, mixed>>  $lineas
     */
    private function persistirLineasTransferencia(Transferencia_Mercaderia $transferencia, array $lineas): void
    {
        foreach ($lineas as $linea) {
            Transferencia_Mercaderia_Articulo::create([
                'transferencia_mercaderia_id' => $transferencia->id,
                'item' => (int) $linea['item'],
                'articulo_origen_id' => (int) $linea['articulo_origen_id'],
                'articulo_destino_id' => (int) $linea['articulo_destino_id'],
                'cantidad_origen' => (float) $linea['cantidad_origen'],
                'cantidad_destino' => (float) $linea['cantidad_destino'],
                'precio_costo_origen' => (float) $linea['precio_costo_origen'],
                'precio_costo_destino' => (float) $linea['precio_costo_destino'],
                'coeficienteconversion' => (float) $linea['coeficienteconversion'],
                'fl_conversion_formula' => (bool) $linea['fl_conversion_formula'],
            ]);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $lineas
     */
    private function validarCantidadesContraSaldo(int $depositoSalidaId, array $lineas): void
    {
        $inventario = $this->stkdepSaldoAnitaService->inventarioPorDepositoId($depositoSalidaId);
        $saldoPorArticulo = [];
        foreach ($inventario as $fila) {
            if (! empty($fila['articulo_id'])) {
                $saldoPorArticulo[(int) $fila['articulo_id']] = (float) $fila['saldo'];
            }
        }

        foreach ($lineas as $linea) {
            $articuloId = (int) $linea['articulo_origen_id'];
            $cantidad = (float) $linea['cantidad_origen'];
            if (! isset($saldoPorArticulo[$articuloId])) {
                throw new \InvalidArgumentException('Artículo sin saldo en el depósito de salida.');
            }
            if ($cantidad > $saldoPorArticulo[$articuloId] + 0.000001) {
                throw new \InvalidArgumentException('La cantidad supera el saldo disponible.');
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $lineas
     */
    private function armarPayloadMovimiento(array $lineas, string $lado): array
    {
        $articulosId = [];
        $cantidades = [];
        $precios = [];
        $items = [];

        foreach ($lineas as $linea) {
            if ($lado === 'salida') {
                $articulosId[] = (int) $linea['articulo_origen_id'];
                $cantidades[] = (float) $linea['cantidad_origen'];
                $precios[] = (float) $linea['precio_costo_origen'];
            } else {
                $articulosId[] = (int) $linea['articulo_destino_id'];
                $cantidades[] = (float) $linea['cantidad_destino'];
                $precios[] = (float) $linea['precio_costo_destino'];
            }
            $items[] = (int) $linea['item'];
        }

        $n = count($articulosId);

        return [
            'articulos_id' => $articulosId,
            'skus' => array_fill(0, $n, ''),
            'combinaciones_id' => array_fill(0, $n, null),
            'modulos_id' => array_fill(0, $n, null),
            'items' => $items,
            'cantidades' => $cantidades,
            'cajas' => array_fill(0, $n, 0),
            'piezas' => array_fill(0, $n, 0),
            'precios' => $precios,
            'listasprecios_id' => array_fill(0, $n, null),
            'incluyeimpuestos' => array_fill(0, $n, '0'),
            'monedas_id' => array_fill(0, $n, null),
            'descuentos' => array_fill(0, $n, 0),
            'loteids' => array_fill(0, $n, 0),
            'medidas' => [],
        ];
    }

    /**
     * @param  list<Transferencia_Mercaderia_Articulo>  $lineas
     */
    private function armarPayloadMovimientoDesdePersistidas(array $lineas, string $lado): array
    {
        $mapped = [];
        foreach ($lineas as $linea) {
            $mapped[] = [
                'item' => (int) $linea->item,
                'articulo_origen_id' => (int) $linea->articulo_origen_id,
                'articulo_destino_id' => (int) $linea->articulo_destino_id,
                'cantidad_origen' => (float) $linea->cantidad_origen,
                'cantidad_destino' => (float) $linea->cantidad_destino,
                'precio_costo_origen' => (float) $linea->precio_costo_origen,
                'precio_costo_destino' => (float) $linea->precio_costo_destino,
            ];
        }

        return $this->armarPayloadMovimiento($mapped, $lado);
    }

    private function validarTipoTransferencia(?Tipotransaccion_Stock $tipo): void
    {
        if ($tipo === null) {
            throw new \RuntimeException('Tipo de transacción no encontrado.');
        }
        if ($tipo->operacion !== TransferenciaMercaderiaSignoSupport::OPERACION_TIPO) {
            throw new \RuntimeException(
                'El tipo de transacción debe ser de operación Transferencia de stock (T).'
            );
        }
        if ($tipo->estado !== 'A') {
            throw new \RuntimeException('El tipo de transacción de transferencia no está activo.');
        }
    }

    private function grabarMovimiento(
        int $tipotransaccionId,
        int $depositoId,
        string $fecha,
        int $lote,
        string $codigo,
        string $leyenda,
        array $payloadLineas,
        bool $esSalida
    ): array {
        $data = array_merge($payloadLineas, [
            'tipotransaccion_stock_id' => $tipotransaccionId,
            'signo_cantidad' => TransferenciaMercaderiaSignoSupport::signoCantidad($esSalida),
            'fecha' => $fecha,
            'fechajornada' => $fecha,
            'deposito_id' => $depositoId,
            'mventa_id' => null,
            'lote' => $lote,
            'leyenda' => $leyenda,
            'loteimportacion_id' => null,
            'codigo' => $codigo,
            'letra' => '',
            'puntoventa' => '',
            'numerocomprobante' => '',
            'codigocliente' => '',
            'codigotransporte' => '',
            'codigovendedor' => '',
            'codigozona' => '',
            'codigoprovincia' => '',
            'pedido' => '',
            'empresa' => config('app.empresa'),
        ]);

        $resultado = $this->movimientoStockService->guardaMovimientoStock($data, 'create');
        if (! is_array($resultado) || empty($resultado['id'])) {
            throw new \RuntimeException(is_string($resultado) ? $resultado : 'No se pudo grabar el movimiento de stock.');
        }

        return [
            'id' => (int) $resultado['id'],
            'codigo' => (string) ($resultado['codigo'] ?? $codigo),
        ];
    }

    private function generarTokensYNotificarAprobacion(Transferencia_Mercaderia $transferencia): void
    {
        $this->invalidarTokens($transferencia);

        $usuarioDestinoId = (int) ($transferencia->usuario_destino_id ?? 0);
        $horas = max(1, (int) config('stock.transferencia_horas_validez_token', 168));
        $expira = now()->addHours($horas);

        if ($usuarioDestinoId > 0) {
            foreach ([
                Transferencia_Mercaderia_Token::ACCION_APROBAR,
                Transferencia_Mercaderia_Token::ACCION_RECHAZAR,
                Transferencia_Mercaderia_Token::ACCION_VISUALIZAR,
            ] as $accion) {
                Transferencia_Mercaderia_Token::create([
                    'transferencia_mercaderia_id' => $transferencia->id,
                    'token' => Str::random(48),
                    'accion' => $accion,
                    'usuario_destino_id' => $usuarioDestinoId,
                    'expira_el' => $expira,
                ]);
            }
        }

        $this->moduloAvisoService->enviar('stock', 'transferencia_pendiente_aprobacion', (int) $transferencia->id);
    }

    private function invalidarTokens(Transferencia_Mercaderia $transferencia): void
    {
        Transferencia_Mercaderia_Token::query()
            ->where('transferencia_mercaderia_id', $transferencia->id)
            ->whereNull('usado_el')
            ->update(['usado_el' => now()]);
    }

    private function resolverTipoTransaccionStockIdDefault(): ?int
    {
        $cached = $this->resolverTipoTransaccionStockIdCacheado();
        if ($cached !== null) {
            return $cached;
        }

        return $this->resolverPrimeraTipotransaccionTransferencia();
    }

    private function resolverTipoTransaccionStockIdCacheado(): ?int
    {
        $cached = (int) cache()->get(generaKey(self::CACHE_TIPO_TRANSACCION));
        if ($cached <= 0) {
            return null;
        }

        if (Tipotransaccion_Stock::query()->whereKey($cached)->exists()) {
            return $cached;
        }

        if (! Schema::hasTable('tipotransaccion_stock_map')) {
            return null;
        }

        $mapped = DB::table('tipotransaccion_stock_map')
            ->where('tipotransaccion_id', $cached)
            ->value('tipotransaccion_stock_id');

        return $mapped ? (int) $mapped : null;
    }

    private function resolverPrimeraTipotransaccionTransferencia(): ?int
    {
        $id = Tipotransaccion_Stock::query()
            ->where('operacion', TransferenciaMercaderiaSignoSupport::OPERACION_TIPO)
            ->where('estado', 'A')
            ->orderBy('nombre')
            ->value('id');

        return $id ? (int) $id : null;
    }
}
