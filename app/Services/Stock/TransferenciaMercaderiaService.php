<?php

namespace App\Services\Stock;

use App\Models\Stock\Depmae;
use App\Models\Ventas\Tipotransaccion;
use App\Repositories\Ventas\TipotransaccionRepositoryInterface;
use App\Support\Stock\TransferenciaMercaderiaSignoSupport;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class TransferenciaMercaderiaService
{
    public const CACHE_DEPOSITO_SALIDA = 'transferencia-deposito-salida';

    public const CACHE_DEPOSITO_ENTRADA = 'transferencia-deposito-entrada';

    public const CACHE_TIPO_TRANSACCION = 'transferencia-tipotransaccion';

    public function __construct(
        private MovimientoStockService $movimientoStockService,
        private TipotransaccionRepositoryInterface $tipotransaccionRepository,
        private StkdepSaldoAnitaService $stkdepSaldoAnitaService,
    ) {}

    public function defaultsUsuario(): array
    {
        return [
            'deposito_salida_id' => cache()->get(generaKey(self::CACHE_DEPOSITO_SALIDA)),
            'deposito_entrada_id' => cache()->get(generaKey(self::CACHE_DEPOSITO_ENTRADA)),
            'tipotransaccion_id' => cache()->get(generaKey(self::CACHE_TIPO_TRANSACCION)),
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
        if (! empty($data['tipotransaccion_id'])) {
            Cache::forever(generaKey(self::CACHE_TIPO_TRANSACCION), (int) $data['tipotransaccion_id']);
        }
    }

    /**
     * @return list<array{sku_anita: string, saldo: float, articulo_id: int|null, sku: string|null, descripcion: string|null}>
     */
    public function inventarioDepositoSalida(int $depositoSalidaId): array
    {
        return $this->stkdepSaldoAnitaService->inventarioPorDepositoId($depositoSalidaId);
    }

    /**
     * @param  list<array{articulo_id: int, cantidad: float}>  $lineas
     * @return array{ok: bool, mensaje: string, codigo?: string}
     */
    public function grabarTransferencia(array $cabecera, array $lineas): array
    {
        $depositoSalidaId = (int) ($cabecera['deposito_salida_id'] ?? 0);
        $depositoEntradaId = (int) ($cabecera['deposito_entrada_id'] ?? 0);
        $tipotransaccionId = (int) ($cabecera['tipotransaccion_id'] ?? 0);

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

        $tipoTransferencia = $this->tipotransaccionRepository->find($tipotransaccionId);
        $this->validarTipoTransferencia($tipoTransferencia);

        $depositoSalida = Depmae::query()->findOrFail($depositoSalidaId);
        $depositoEntrada = Depmae::query()->findOrFail($depositoEntradaId);

        $ahora = Carbon::now();
        $fecha = $ahora->format('Y-m-d');
        $lote = (int) $ahora->format('ymdHis');
        $codigoBase = 'TR-'.$ahora->format('YmdHis');

        $this->persistirPreferencias($cabecera);

        $this->validarCantidadesContraSaldo($depositoSalidaId, $lineas);

        $payloadLineas = $this->armarPayloadLineas($lineas);

        $movimientoSalidaId = null;
        try {
            $salida = $this->grabarMovimiento(
                $tipoTransferencia->id,
                $depositoSalidaId,
                $fecha,
                $lote,
                $codigoBase.'-S',
                'Transferencia a '.$depositoEntrada->nombre,
                $payloadLineas,
                esSalida: true
            );
            $movimientoSalidaId = (int) ($salida['id'] ?? 0);

            $this->grabarMovimiento(
                $tipoTransferencia->id,
                $depositoEntradaId,
                $fecha,
                $lote,
                $codigoBase.'-E',
                'Transferencia desde '.$depositoSalida->nombre,
                $payloadLineas,
                esSalida: false
            );
        } catch (\Throwable $e) {
            if ($movimientoSalidaId > 0) {
                try {
                    $this->movimientoStockService->borraMovimientoStock($movimientoSalidaId);
                } catch (\Throwable $rollbackEx) {
                    Log::warning('TransferenciaMercaderia: no se pudo revertir salida', [
                        'movimiento_id' => $movimientoSalidaId,
                        'error' => $rollbackEx->getMessage(),
                    ]);
                }
            }

            return ['ok' => false, 'mensaje' => $e->getMessage()];
        }

        return [
            'ok' => true,
            'mensaje' => 'Transferencia registrada ('.count($payloadLineas['articulos_id']).' artículos).',
            'codigo' => $codigoBase,
        ];
    }

    /**
     * @param  list<array{articulo_id: int, cantidad: float}>  $lineas
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
            $articuloId = (int) ($linea['articulo_id'] ?? 0);
            $cantidad = (float) ($linea['cantidad'] ?? 0);
            if ($articuloId <= 0 || $cantidad <= 0) {
                continue;
            }
            if (! isset($saldoPorArticulo[$articuloId])) {
                throw new \InvalidArgumentException('Artículo sin saldo en el depósito de salida.');
            }
            if ($cantidad > $saldoPorArticulo[$articuloId] + 0.000001) {
                throw new \InvalidArgumentException('La cantidad supera el saldo disponible.');
            }
        }
    }

    /**
     * @param  list<array{articulo_id: int, cantidad: float}>  $lineas
     */
    private function armarPayloadLineas(array $lineas): array
    {
        $articulosId = [];
        $cantidades = [];
        $items = [];
        $i = 0;

        foreach ($lineas as $linea) {
            $articuloId = (int) ($linea['articulo_id'] ?? 0);
            $cantidad = (float) ($linea['cantidad'] ?? 0);
            if ($articuloId <= 0 || $cantidad <= 0) {
                continue;
            }
            $i++;
            $articulosId[] = $articuloId;
            $cantidades[] = $cantidad;
            $items[] = $i;
        }

        if ($articulosId === []) {
            throw new \InvalidArgumentException('No hay líneas válidas para transferir.');
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
            'precios' => array_fill(0, $n, 0),
            'listasprecios_id' => array_fill(0, $n, null),
            'incluyeimpuestos' => array_fill(0, $n, '0'),
            'monedas_id' => array_fill(0, $n, null),
            'descuentos' => array_fill(0, $n, 0),
            'loteids' => array_fill(0, $n, 0),
            'medidas' => [],
        ];
    }

    /**
     * @return array{id: int, codigo: string}
     */
    private function validarTipoTransferencia(?Tipotransaccion $tipo): void
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
            'tipotransaccion_id' => $tipotransaccionId,
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

}
