<?php

namespace App\Services\Stock;

use App\Models\Stock\Articulo;
use App\Models\Stock\Depmae;
use App\Models\Stock\Tipotransaccion_Stock;
use App\Models\Stock\Transferencia_Mercaderia;
use App\Models\Stock\Transferencia_Mercaderia_Articulo;
use App\Models\Stock\Transferencia_Mercaderia_Token;
use App\Repositories\Stock\Articulo_Saldo_DepositoRepositoryInterface;
use App\Repositories\Stock\Tipotransaccion_StockRepositoryInterface;
use App\Services\Configuracion\ModuloAvisoService;
use App\Support\Contable\PeriodoContableCierreSupport;
use App\Support\Stock\DepmaeControlStockSupport;
use App\Support\Configuracion\OperacionPublicaTokenSupport;
use App\Support\Stock\MovimientoStockSalidaSaldoSupport;
use App\Support\Stock\TransferenciaBienUsoSupport;
use App\Support\Stock\TransferenciaMercaderiaAprobacionSupport;
use App\Support\Stock\TransferenciaMercaderiaDestinatarioSupport;
use App\Support\Stock\TransferenciaMercaderiaEstados;
use App\Support\Stock\TransferenciaMercaderiaLineaContableSupport;
use App\Support\Stock\TransferenciaMercaderiaLineaSupport;
use App\Support\Stock\StkmaePrecioCompraAnitaBridgeSupport;
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

    public const CACHE_BIEN_USO_DESTINO = 'transferencia-bien-uso-destino';

    public const CACHE_BIEN_USO_ORIGEN = 'transferencia-bien-uso-origen';

    public const CACHE_TIPO_TRANSACCION = 'transferencia-tipotransaccion';

    public function __construct(
        private MovimientoStockService $movimientoStockService,
        private Tipotransaccion_StockRepositoryInterface $tipotransaccionStockRepository,
        private Articulo_Saldo_DepositoRepositoryInterface $saldoDepositoRepository,
        private ModuloAvisoService $moduloAvisoService,
        private TransferenciaMercaderiaAsientoService $transferenciaAsientoService,
    ) {}

    public function defaultsUsuario(): array
    {
        return [
            'deposito_salida_id' => cache()->get(generaKey(self::CACHE_DEPOSITO_SALIDA)),
            'deposito_entrada_id' => cache()->get(generaKey(self::CACHE_DEPOSITO_ENTRADA)),
            'bien_uso_destino_id' => cache()->get(generaKey(self::CACHE_BIEN_USO_DESTINO)),
            'bien_uso_origen_id' => cache()->get(generaKey(self::CACHE_BIEN_USO_ORIGEN)),
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
        if (! empty($data['bien_uso_destino_id'])) {
            Cache::forever(generaKey(self::CACHE_BIEN_USO_DESTINO), (int) $data['bien_uso_destino_id']);
        }
        if (! empty($data['bien_uso_origen_id'])) {
            Cache::forever(generaKey(self::CACHE_BIEN_USO_ORIGEN), (int) $data['bien_uso_origen_id']);
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

        $rows = $this->saldoDepositoRepository->saldosDeposito($depositoSalidaId);
        $out = [];
        foreach ($rows as $row) {
            $saldo = (float) ($row->cantidad ?? 0);
            if ($saldo <= 0) {
                continue;
            }
            $art = $row->articulos;
            if ($art === null) {
                continue;
            }
            $sku = trim((string) ($art->sku ?? ''));
            if ($sku === '') {
                continue;
            }
            $out[] = [
                'sku_anita' => $sku,
                'saldo' => $saldo,
                'articulo_id' => (int) $art->id,
                'sku' => $sku,
                'descripcion' => (string) ($art->descripcion ?? ''),
            ];
        }

        usort($out, static function (array $a, array $b): int {
            return strcasecmp(
                (string) ($a['descripcion'] ?? $a['sku'] ?? ''),
                (string) ($b['descripcion'] ?? $b['sku'] ?? '')
            );
        });

        return $out;
    }

    public function saldoArticuloEnDeposito(int $articuloId, int $depositoId): float
    {
        if ($articuloId <= 0 || $depositoId <= 0) {
            return 0.0;
        }

        $this->assertDepositoAutorizado($depositoId);

        return $this->saldoDepositoRepository->saldo($articuloId, $depositoId);
    }

    /**
     * @return list<array{sku_anita: string, saldo: float, articulo_id: int|null, sku: string|null, descripcion: string|null}>
     */
    public function inventarioBienUsoOrigen(int $bienUsoOrigenId): array
    {
        TransferenciaBienUsoSupport::assertBienActivo($bienUsoOrigenId);

        return array_map(static fn (array $fila) => [
            'sku_anita' => (string) ($fila['sku'] ?? ''),
            'saldo' => (float) $fila['cantidad'],
            'articulo_id' => (int) $fila['articulo_id'],
            'sku' => $fila['sku'] ?? null,
            'descripcion' => $fila['descripcion'] ?? null,
        ], \App\Support\Stock\BienUsoAsignacionSupport::inventarioActual($bienUsoOrigenId));
    }

    /** @return list<Transferencia_Mercaderia> */
    public function listarPendientes(?int $depositoDestinoId = null): array
    {
        $query = Transferencia_Mercaderia::query()
            ->with(['depositoOrigen', 'depositoDestino', 'bienUsoOrigen', 'bienUsoDestino', 'usuarioOrigen', 'usuarioDestino', 'articulos.articuloOrigen', 'articulos.articuloDestino'])
            ->where('estado', TransferenciaMercaderiaEstados::PENDIENTE_RECEPCION)
            ->orderByDesc('fecha')
            ->orderByDesc('id');

        if ($depositoDestinoId > 0) {
            $query->where('deposito_destino_id', $depositoDestinoId);
        }

        if (UsuarioDepositoAutorizado::tieneRestriccion()) {
            $ids = UsuarioDepositoAutorizado::idsRestringidos() ?? [];
            $usuarioId = (int) (Auth::id() ?? 0);
            if ($ids !== [] || $usuarioId > 0) {
                $query->where(function ($q) use ($ids, $usuarioId) {
                    if ($ids !== []) {
                        $q->whereIn('deposito_destino_id', $ids);
                    }
                    if ($usuarioId > 0) {
                        $q->orWhere('usuario_destino_id', $usuarioId);
                    }
                });
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
                'bienUsoOrigen',
                'bienUsoDestino',
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
        $bienUsoDestinoId = (int) ($cabecera['bien_uso_destino_id'] ?? 0);
        $bienUsoOrigenId = (int) ($cabecera['bien_uso_origen_id'] ?? 0);
        $tipotransaccionId = (int) ($cabecera['tipotransaccion_stock_id'] ?? $cabecera['tipotransaccion_id'] ?? 0);
        $empresaId = (int) ($cabecera['empresa_id'] ?? 0);
        $usuarioDestinoId = (int) ($cabecera['usuario_destino_id'] ?? 0) ?: null;

        if ($tipotransaccionId <= 0) {
            return ['ok' => false, 'mensaje' => 'Debe seleccionar un tipo de transacción.'];
        }
        if ($lineas === []) {
            return ['ok' => false, 'mensaje' => 'Indique al menos un artículo con cantidad a transferir.'];
        }

        $tipoTransferencia = $this->tipotransaccionStockRepository->find($tipotransaccionId);
        try {
            $this->validarTipoTransferencia($tipoTransferencia);
        } catch (\Throwable $e) {
            return ['ok' => false, 'mensaje' => $e->getMessage()];
        }

        $destinoBienUso = TransferenciaBienUsoSupport::tipoDestinoBienUso($tipoTransferencia);
        $origenBienUso = TransferenciaBienUsoSupport::tipoOrigenBienUso($tipoTransferencia);

        if ($origenBienUso) {
            if ($bienUsoOrigenId <= 0) {
                return ['ok' => false, 'mensaje' => 'Debe seleccionar el bien de uso de origen.'];
            }
            if ($depositoSalidaId > 0) {
                return ['ok' => false, 'mensaje' => 'Este tipo de transferencia no usa depósito de salida.'];
            }
            if ($depositoEntradaId <= 0) {
                return ['ok' => false, 'mensaje' => 'Debe indicar depósito de entrada.'];
            }
            if ($bienUsoDestinoId > 0) {
                return ['ok' => false, 'mensaje' => 'Este tipo de transferencia no admite bien de uso como destino.'];
            }
            try {
                $bienOrigen = TransferenciaBienUsoSupport::assertBienActivo($bienUsoOrigenId);
            } catch (\Throwable $e) {
                return ['ok' => false, 'mensaje' => $e->getMessage()];
            }
            $bienDestino = null;
        } elseif ($destinoBienUso) {
            if ($depositoSalidaId <= 0) {
                return ['ok' => false, 'mensaje' => 'Debe indicar depósito de salida.'];
            }
            if ($bienUsoDestinoId <= 0) {
                return ['ok' => false, 'mensaje' => 'Debe seleccionar el bien de uso destino.'];
            }
            if ($depositoEntradaId > 0) {
                return ['ok' => false, 'mensaje' => 'Este tipo de transferencia no usa depósito de entrada.'];
            }
            if ($bienUsoOrigenId > 0) {
                return ['ok' => false, 'mensaje' => 'El tipo de transacción seleccionado no admite bien de uso como origen.'];
            }
            try {
                $bienDestino = TransferenciaBienUsoSupport::assertBienActivo($bienUsoDestinoId);
            } catch (\Throwable $e) {
                return ['ok' => false, 'mensaje' => $e->getMessage()];
            }
            $bienOrigen = null;
        } else {
            $bienOrigen = null;
            $bienDestino = null;
            if ($depositoSalidaId <= 0) {
                return ['ok' => false, 'mensaje' => 'Debe indicar depósito de salida.'];
            }
            if ($depositoEntradaId <= 0) {
                return ['ok' => false, 'mensaje' => 'Debe indicar depósito de entrada.'];
            }
            if ($depositoSalidaId === $depositoEntradaId) {
                return ['ok' => false, 'mensaje' => 'El depósito de salida y el de entrada deben ser distintos.'];
            }
            if ($bienUsoDestinoId > 0 || $bienUsoOrigenId > 0) {
                return ['ok' => false, 'mensaje' => 'El tipo de transacción seleccionado no admite bienes de uso.'];
            }
        }

        if (! $origenBienUso) {
            $this->assertDepositoAutorizado($depositoSalidaId);
            if (! Depmae::autorizadoParaUsuarioYEmpresa($depositoSalidaId, $empresaId)) {
                return ['ok' => false, 'mensaje' => 'Depósito de salida no autorizado para su usuario o empresa.'];
            }
        }
        if (! $destinoBienUso && ! Depmae::autorizadoParaUsuarioYEmpresa($depositoEntradaId, $empresaId)) {
            return ['ok' => false, 'mensaje' => 'Depósito de entrada no autorizado para la empresa seleccionada.'];
        }

        $depositoSalida = $origenBienUso ? null : Depmae::query()->findOrFail($depositoSalidaId);
        $depositoEntrada = $destinoBienUso ? null : Depmae::query()->findOrFail($depositoEntradaId);

        $ahora = Carbon::now();
        $fecha = $ahora->format('Y-m-d');

        PeriodoContableCierreSupport::assertOperacionPermitida(
            (int) ($depositoSalida?->empresa_id ?? $depositoEntrada?->empresa_id ?? $empresaId),
            $fecha,
            PeriodoContableCierreSupport::ALCANCE_TRANSFERENCIA
        );

        $requiereAprobacion = TransferenciaMercaderiaAprobacionSupport::requiereAprobacion($tipoTransferencia);
        $usuarioDestino = $destinoBienUso
            ? TransferenciaMercaderiaDestinatarioSupport::resolverUsuarioDestinoBienUso($usuarioDestinoId)
            : TransferenciaMercaderiaDestinatarioSupport::resolverUsuarioDestino(
                $depositoEntradaId,
                $usuarioDestinoId
            );

        if ($requiereAprobacion && $usuarioDestino === null) {
            $mensajeDestino = $destinoBienUso
                ? 'La transferencia requiere aprobación: indique el usuario receptor del bien.'
                : 'La transferencia requiere aprobación: indique un usuario destino o configure un encargado del depósito de entrada.';

            return [
                'ok' => false,
                'mensaje' => $mensajeDestino,
            ];
        }

        $lote = (int) $ahora->format('ymdHis');
        $codigoBase = 'TR-'.$ahora->format('YmdHis');

        $this->persistirPreferencias($cabecera);

        try {
            $lineasResueltas = $this->resolverLineas($lineas, $depositoEntrada, $empresaId, $destinoBienUso || $origenBienUso);
            if ($origenBienUso) {
                $this->validarCantidadesContraSaldoBien($bienUsoOrigenId, $lineasResueltas);
            } else {
                $this->validarCantidadesContraSaldo($depositoSalidaId, $lineasResueltas);
            }
        } catch (\Throwable $e) {
            return ['ok' => false, 'mensaje' => $e->getMessage()];
        }

        $etiquetaDestino = $destinoBienUso
            ? TransferenciaBienUsoSupport::etiquetaBien($bienDestino)
            : (string) $depositoEntrada->nombre;
        $etiquetaOrigen = $origenBienUso
            ? TransferenciaBienUsoSupport::etiquetaBien($bienOrigen)
            : (string) $depositoSalida->nombre;

        $ccDestinoId = (int) ($cabecera['centrocosto_destino_id'] ?? 0);
        $manejaContabilidad = TransferenciaMercaderiaAprobacionSupport::manejaContabilidad($tipoTransferencia);
        if ($manejaContabilidad && $ccDestinoId <= 0) {
            return ['ok' => false, 'mensaje' => 'Debe indicar centro de costo destino para transferencias con contabilidad.'];
        }

        if ($manejaContabilidad) {
            if ($origenBienUso) {
                return [
                    'ok' => false,
                    'mensaje' => 'Las transferencias contables (TRCONT) requieren depósito de salida (no bien de uso como origen).',
                ];
            }

            try {
                TransferenciaMercaderiaLineaContableSupport::assertLineasValidasParaTrcont(
                    array_column($lineas, 'articulo_id'),
                    $depositoSalidaId,
                    $empresaId
                );
            } catch (\Throwable $e) {
                return ['ok' => false, 'mensaje' => $e->getMessage()];
            }
        }

        if ($manejaContabilidad && ! $requiereAprobacion) {
            try {
                $this->transferenciaAsientoService->assertCuadreAntesDeConfirmar(
                    $this->construirTransferenciaPreviewParaCuadre(
                        $lineasResueltas,
                        $tipoTransferencia,
                        $empresaId > 0 ? $empresaId : (int) ($depositoSalida?->empresa_id ?? $depositoEntrada?->empresa_id ?? 0),
                        $fecha,
                        $ccDestinoId,
                        $depositoSalidaId
                    )
                );
            } catch (\Throwable $e) {
                return ['ok' => false, 'mensaje' => $e->getMessage()];
            }
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
                $bienUsoDestinoId,
                $bienUsoOrigenId,
                $destinoBienUso,
                $origenBienUso,
                $empresaId,
                $fecha,
                $lote,
                $codigoBase,
                $requiereAprobacion,
                $usuarioDestino,
                $etiquetaDestino,
                $etiquetaOrigen,
                $ccDestinoId,
                $manejaContabilidad
            ) {
                $transferencia = Transferencia_Mercaderia::create([
                    'codigo' => $codigoBase,
                    'lote' => $lote,
                    'empresa_id' => $empresaId > 0 ? $empresaId : (int) ($depositoSalida?->empresa_id ?? $depositoEntrada?->empresa_id ?? 0),
                    'deposito_origen_id' => $origenBienUso ? null : $depositoSalidaId,
                    'bien_uso_origen_id' => $origenBienUso ? $bienUsoOrigenId : null,
                    'deposito_destino_id' => $destinoBienUso ? null : $depositoEntradaId,
                    'bien_uso_destino_id' => $destinoBienUso ? $bienUsoDestinoId : null,
                    'tipotransaccion_stock_id' => $tipoTransferencia->id,
                    'centrocosto_destino_id' => $manejaContabilidad ? $ccDestinoId : null,
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
                    $origenBienUso ? null : $depositoSalidaId,
                    $fecha,
                    $lote,
                    $codigoBase.'-S',
                    $origenBienUso
                        ? 'Desasignación hacia '.$etiquetaDestino
                        : 'Transferencia a '.$etiquetaDestino,
                    $payloadSalida,
                    esSalida: true,
                    bienUsoId: $origenBienUso ? $bienUsoOrigenId : null,
                    empresaId: $empresaId > 0 ? $empresaId : (int) ($depositoSalida?->empresa_id ?? $depositoEntrada?->empresa_id ?? 0)
                );
                $transferencia->movimientostock_salida_id = (int) $salida['id'];
                $transferencia->save();

                if (! $requiereAprobacion) {
                    $payloadEntrada = $this->armarPayloadMovimiento($lineasResueltas, 'entrada');
                    $entrada = $this->grabarMovimiento(
                        $tipoTransferencia->id,
                        $destinoBienUso ? null : $depositoEntradaId,
                        $fecha,
                        $lote,
                        $codigoBase.'-E',
                        'Transferencia desde '.$etiquetaOrigen,
                        $payloadEntrada,
                        esSalida: false,
                        bienUsoId: $destinoBienUso ? $bienUsoDestinoId : null,
                        empresaId: $empresaId > 0 ? $empresaId : (int) ($depositoSalida?->empresa_id ?? $depositoEntrada?->empresa_id ?? 0)
                    );
                    $transferencia->movimientostock_entrada_id = (int) $entrada['id'];
                    $transferencia->save();

                    $this->actualizarStkmaePrecioDestino($transferencia);

                    $this->confirmarAsientoContable($transferencia->fresh([
                        'articulos.articuloOrigen.articulo_cuentacontables',
                        'tipotransaccion_stock',
                    ]));

                    $this->generarTokenConsultaPublica($transferencia->fresh());
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
        $esDestinoBien = (int) ($transferencia->bien_uso_destino_id ?? 0) > 0;
        $esOrigenBien = (int) ($transferencia->bien_uso_origen_id ?? 0) > 0;

        if ($esDestinoBien) {
            if (! TransferenciaMercaderiaDestinatarioSupport::usuarioPuedeAprobarBienUso(
                $transferencia,
                \App\Models\Seguridad\Usuario::query()->findOrFail($usuarioAprobadorId)
            )) {
                throw new \RuntimeException('No está autorizado para aprobar esta transferencia al bien de uso.');
            }
        } elseif (! UsuarioDepositoAutorizado::depositoAutorizado((int) $transferencia->deposito_destino_id)
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

        return DB::transaction(function () use ($transferencia, $usuarioAprobadorId, $observaciones, $esDestinoBien, $esOrigenBien) {
            $this->transferenciaAsientoService->assertCuadreAntesDeConfirmar($transferencia);

            $lineas = $transferencia->articulos->all();
            $payloadEntrada = $this->armarPayloadMovimientoDesdePersistidas($lineas, 'entrada');
            $tipo = $transferencia->tipotransaccion_stock;
            $etiquetaOrigen = $esOrigenBien
                ? TransferenciaBienUsoSupport::etiquetaBien($transferencia->bienUsoOrigen)
                : (string) optional($transferencia->depositoOrigen)->nombre;

            $entrada = $this->grabarMovimiento(
                (int) $transferencia->tipotransaccion_stock_id,
                $esDestinoBien ? null : (int) $transferencia->deposito_destino_id,
                $transferencia->fecha?->format('Y-m-d') ?? now()->format('Y-m-d'),
                (int) $transferencia->lote,
                $transferencia->codigo.'-E',
                'Transferencia desde '.$etiquetaOrigen,
                $payloadEntrada,
                esSalida: false,
                bienUsoId: $esDestinoBien ? (int) $transferencia->bien_uso_destino_id : null,
                empresaId: (int) $transferencia->empresa_id
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

            $this->actualizarStkmaePrecioDestino($transferencia);

            $this->confirmarAsientoContable($transferencia->fresh([
                'articulos.articuloOrigen.articulo_cuentacontables',
                'tipotransaccion_stock',
            ]));

            $this->generarTokenConsultaPublica($transferencia->fresh());
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
            $esOrigenBien = (int) ($transferencia->bien_uso_origen_id ?? 0) > 0;

            $this->grabarMovimiento(
                (int) $transferencia->tipotransaccion_stock_id,
                $esOrigenBien ? null : (int) $transferencia->deposito_origen_id,
                now()->format('Y-m-d'),
                (int) $transferencia->lote,
                $transferencia->codigo.'-RV',
                'Reverso transferencia rechazada',
                $payloadReverso,
                esSalida: false,
                bienUsoId: $esOrigenBien ? (int) $transferencia->bien_uso_origen_id : null,
                empresaId: (int) $transferencia->empresa_id
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
    private function resolverLineas(array $lineas, ?Depmae $depositoEntrada, int $empresaId, bool $destinoBienUso = false): array
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
            if ($destinoBienUso) {
                $conv = TransferenciaMercaderiaLineaSupport::resolverLineaParaBienUso($articulo, $cantidad);
            } else {
                $conv = TransferenciaMercaderiaLineaSupport::resolverLinea(
                    $articulo,
                    $depositoEntrada,
                    $cantidad,
                    $empresaId > 0 ? $empresaId : null
                );
            }
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
     * Evalúa saldo en depósito de salida sin grabar (preflight requisición sala, avisos portal/mail).
     *
     * @param  list<array{articulo_id: int, cantidad: float}>  $lineas
     * @return array{
     *     viable: bool,
     *     controla_stock: bool,
     *     mensaje_resumen: string,
     *     lineas_detalle: list<array{
     *         articulo_id: int,
     *         sku: string,
     *         descripcion: string,
     *         cantidad_requerida: float,
     *         saldo_disponible: float|null,
     *         ok: bool,
     *         motivo: string
     *     }>
     * }
     */
    public function evaluarSaldosTransferenciaDesdePayload(
        int $depositoSalidaId,
        int $depositoEntradaId,
        int $empresaId,
        array $lineas
    ): array {
        if ($lineas === []) {
            return [
                'viable' => false,
                'controla_stock' => true,
                'mensaje_resumen' => 'No hay ítems para transferir.',
                'lineas_detalle' => [],
            ];
        }

        $depositoSalida = Depmae::query()->find($depositoSalidaId);
        $depositoEntrada = Depmae::query()->find($depositoEntradaId);
        if ($depositoSalida === null || $depositoEntrada === null) {
            return [
                'viable' => false,
                'controla_stock' => true,
                'mensaje_resumen' => 'Depósito de origen o destino inexistente.',
                'lineas_detalle' => [],
            ];
        }

        $controlaStock = DepmaeControlStockSupport::manejaControlStock($depositoSalida);

        try {
            $lineasResueltas = $this->resolverLineas($lineas, $depositoEntrada, $empresaId, false);
        } catch (\Throwable $e) {
            return [
                'viable' => false,
                'controla_stock' => $controlaStock,
                'mensaje_resumen' => $e->getMessage(),
                'lineas_detalle' => [],
            ];
        }

        if (! $controlaStock) {
            return [
                'viable' => true,
                'controla_stock' => false,
                'mensaje_resumen' => '',
                'lineas_detalle' => $this->armarDetalleEvaluacionSaldos($lineasResueltas, [], true),
            ];
        }

        $saldoPorArticulo = $this->saldosErpPorLineasResueltas($depositoSalidaId, $lineasResueltas);

        $detalle = $this->armarDetalleEvaluacionSaldos($lineasResueltas, $saldoPorArticulo, false);
        $viable = collect($detalle)->every(static fn (array $fila): bool => (bool) ($fila['ok'] ?? false));

        return [
            'viable' => $viable,
            'controla_stock' => true,
            'mensaje_resumen' => $viable
                ? ''
                : 'No hay saldo suficiente en el depósito de origen para uno o más ítems de reparación/devolución. '
                    .'La aprobación puede registrarse, pero la transferencia automática al laboratorio no se realizará.',
            'lineas_detalle' => $detalle,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $lineasResueltas
     * @param  array<int, float>  $saldoPorArticulo
     * @return list<array{
     *     articulo_id: int,
     *     sku: string,
     *     descripcion: string,
     *     cantidad_requerida: float,
     *     saldo_disponible: float|null,
     *     ok: bool,
     *     motivo: string
     * }>
     */
    private function armarDetalleEvaluacionSaldos(array $lineasResueltas, array $saldoPorArticulo, bool $omitirValidacion): array
    {
        $detalle = [];
        foreach ($lineasResueltas as $linea) {
            $articuloId = (int) $linea['articulo_origen_id'];
            $cantidad = (float) $linea['cantidad_origen'];
            $art = Articulo::query()->find($articuloId);
            $sku = (string) ($art?->sku ?? $articuloId);
            $desc = (string) ($art?->descripcion ?? '');

            if ($omitirValidacion) {
                $detalle[] = [
                    'articulo_id' => $articuloId,
                    'sku' => $sku,
                    'descripcion' => $desc,
                    'cantidad_requerida' => $cantidad,
                    'saldo_disponible' => null,
                    'ok' => true,
                    'motivo' => '',
                ];

                continue;
            }

            $saldo = (float) ($saldoPorArticulo[$articuloId] ?? 0.0);
            if ($cantidad > $saldo + 0.000001) {
                $detalle[] = [
                    'articulo_id' => $articuloId,
                    'sku' => $sku,
                    'descripcion' => $desc,
                    'cantidad_requerida' => $cantidad,
                    'saldo_disponible' => $saldo,
                    'ok' => false,
                    'motivo' => $saldo <= 0
                        ? 'Sin saldo en el depósito de origen.'
                        : 'La cantidad supera el saldo disponible.',
                ];

                continue;
            }

            $detalle[] = [
                'articulo_id' => $articuloId,
                'sku' => $sku,
                'descripcion' => $desc,
                'cantidad_requerida' => $cantidad,
                'saldo_disponible' => $saldo,
                'ok' => true,
                'motivo' => '',
            ];
        }

        return $detalle;
    }

    /**
     * @param  list<array<string, mixed>>  $lineas
     */
    private function validarCantidadesContraSaldoBien(int $bienUsoOrigenId, array $lineas): void
    {
        $inventario = \App\Support\Stock\BienUsoAsignacionSupport::inventarioActual($bienUsoOrigenId);
        $saldoPorArticulo = [];
        foreach ($inventario as $fila) {
            $saldoPorArticulo[(int) $fila['articulo_id']] = (float) $fila['cantidad'];
        }

        foreach ($lineas as $linea) {
            $articuloId = (int) $linea['articulo_origen_id'];
            $cantidad = (float) $linea['cantidad_origen'];
            if (! isset($saldoPorArticulo[$articuloId])) {
                throw new \InvalidArgumentException('Artículo sin saldo asignado al bien de uso.');
            }
            if ($cantidad > $saldoPorArticulo[$articuloId] + 0.000001) {
                throw new \InvalidArgumentException('La cantidad supera el saldo asignado al bien.');
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $lineas
     */
    private function validarCantidadesContraSaldo(int $depositoSalidaId, array $lineas): void
    {
        $deposito = Depmae::query()->find($depositoSalidaId);
        if (! DepmaeControlStockSupport::manejaControlStock($deposito)) {
            return;
        }

        /** @var array<int, float> $cantidadPorArticulo */
        $cantidadPorArticulo = [];
        foreach ($lineas as $linea) {
            $articuloId = (int) $linea['articulo_origen_id'];
            $cantidad = (float) $linea['cantidad_origen'];
            if ($articuloId <= 0 || $cantidad <= 0) {
                continue;
            }
            $cantidadPorArticulo[$articuloId] = ($cantidadPorArticulo[$articuloId] ?? 0.0) + $cantidad;
        }

        if ($cantidadPorArticulo === []) {
            return;
        }

        MovimientoStockSalidaSaldoSupport::validarCantidadesPorDeposito(
            $depositoSalidaId,
            $cantidadPorArticulo,
            $this->saldoDepositoRepository
        );
    }

    /**
     * @param  list<array<string, mixed>>  $lineasResueltas
     * @return array<int, float>
     */
    private function saldosErpPorLineasResueltas(int $depositoSalidaId, array $lineasResueltas): array
    {
        $saldoPorArticulo = [];
        foreach ($lineasResueltas as $linea) {
            $articuloId = (int) $linea['articulo_origen_id'];
            if ($articuloId <= 0 || array_key_exists($articuloId, $saldoPorArticulo)) {
                continue;
            }
            $saldoPorArticulo[$articuloId] = $this->saldoDepositoRepository->saldo($articuloId, $depositoSalidaId);
        }

        return $saldoPorArticulo;
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
        TransferenciaBienUsoSupport::validarFlagsTipo($tipo);
        if ($tipo->destino_bien_uso && $tipo->operacion !== TransferenciaMercaderiaSignoSupport::OPERACION_TIPO) {
            throw new \RuntimeException('El destino bien de uso solo aplica a tipos de operación Transferencia (T).');
        }
        if ($tipo->origen_bien_uso && $tipo->operacion !== TransferenciaMercaderiaSignoSupport::OPERACION_TIPO) {
            throw new \RuntimeException('El origen bien de uso solo aplica a tipos de operación Transferencia (T).');
        }
    }

    private function grabarMovimiento(
        int $tipotransaccionId,
        ?int $depositoId,
        string $fecha,
        int $lote,
        string $codigo,
        string $leyenda,
        array $payloadLineas,
        bool $esSalida,
        ?int $bienUsoId = null,
        ?int $empresaId = null
    ): array {
        $data = array_merge($payloadLineas, [
            'tipotransaccion_stock_id' => $tipotransaccionId,
            'signo_cantidad' => TransferenciaMercaderiaSignoSupport::signoCantidad($esSalida),
            'fecha' => $fecha,
            'fechajornada' => $fecha,
            'deposito_id' => $depositoId,
            'bien_uso_id' => $bienUsoId,
            'empresa_id' => $empresaId,
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
            'omitir_asiento_contable' => true,
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

    /**
     * Enlace público de solo consulta (mail confirmada / post-aprobación), sin login ERP.
     */
    public function generarTokenConsultaPublica(Transferencia_Mercaderia $transferencia): Transferencia_Mercaderia_Token
    {
        $horas = max(1, (int) config('stock.transferencia_horas_validez_token', 168));
        $usuarioId = (int) ($transferencia->usuario_destino_id ?? $transferencia->usuario_origen_id ?? 0);

        $token = OperacionPublicaTokenSupport::renovarVisualizar(
            Transferencia_Mercaderia_Token::class,
            'transferencia_mercaderia_id',
            (int) $transferencia->id,
            $usuarioId > 0 ? $usuarioId : null,
            $horas,
        );

        return Transferencia_Mercaderia_Token::query()->where('token', $token)->firstOrFail();
    }

    private function invalidarTokens(Transferencia_Mercaderia $transferencia): void
    {
        Transferencia_Mercaderia_Token::query()
            ->where('transferencia_mercaderia_id', $transferencia->id)
            ->whereNull('usado_el')
            ->update(['usado_el' => now()]);
    }

    private function actualizarStkmaePrecioDestino(Transferencia_Mercaderia $transferencia): void
    {
        try {
            StkmaePrecioCompraAnitaBridgeSupport::actualizarDesdeTransferencia(
                $transferencia->fresh(['articulos.articuloDestino'])
            );
        } catch (\Throwable $e) {
            Log::warning('TransferenciaMercaderia: error actualizando stkmae precio destino', [
                'transferencia_id' => $transferencia->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
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

    private function confirmarAsientoContable(Transferencia_Mercaderia $transferencia): void
    {
        $asientoId = $this->transferenciaAsientoService->generarSiCorresponde($transferencia);
        if ($asientoId !== null && $asientoId > 0) {
            $transferencia->asiento_id = $asientoId;
            $transferencia->save();
        }
    }

    /**
     * @param  list<array<string, mixed>>  $lineasResueltas
     */
    private function construirTransferenciaPreviewParaCuadre(
        array $lineasResueltas,
        Tipotransaccion_Stock $tipo,
        int $empresaId,
        string $fecha,
        int $ccDestinoId,
        int $depositoOrigenId = 0,
    ): Transferencia_Mercaderia {
        $transferencia = new Transferencia_Mercaderia([
            'empresa_id' => $empresaId,
            'deposito_origen_id' => $depositoOrigenId > 0 ? $depositoOrigenId : null,
            'centrocosto_destino_id' => $ccDestinoId,
            'codigo' => 'PREVIEW',
            'fecha' => $fecha,
        ]);
        $transferencia->setRelation('tipotransaccion_stock', $tipo);

        $articulos = collect();
        foreach ($lineasResueltas as $linea) {
            $row = new Transferencia_Mercaderia_Articulo([
                'item' => (int) $linea['item'],
                'articulo_origen_id' => (int) $linea['articulo_origen_id'],
                'cantidad_origen' => (float) $linea['cantidad_origen'],
            ]);
            $origen = Articulo::query()
                ->with('articulo_cuentacontables')
                ->find((int) $linea['articulo_origen_id']);
            $row->setRelation('articuloOrigen', $origen);
            $articulos->push($row);
        }
        $transferencia->setRelation('articulos', $articulos);

        return $transferencia;
    }
}
