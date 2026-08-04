<?php

namespace App\Support\Stock;

use App\Models\Stock\Depmae;
use App\Models\Stock\MovimientoStock;
use App\Models\Stock\Transferencia_Mercaderia;
use Illuminate\Support\Collection;

/**
 * Fila del listado unificado (movimiento suelto o transferencia).
 */
final class MovimientoStockListadoFila
{
    public function __construct(
        public readonly string $filaTipo,
        public readonly int $pkId,
        public readonly ?string $fecha,
        public readonly ?MovimientoStock $movimiento,
        public readonly ?Transferencia_Mercaderia $transferencia,
        public readonly float $totalCantidad,
        public readonly int $itemsCount,
        public readonly string $codigoListado,
        public readonly string $tipoNombre,
        public readonly string $leyendaListado,
        public readonly string $loteListado,
        public readonly string $nombreEmpresa,
        public readonly ?string $depositoCodigo,
        public readonly ?string $depositoNombre,
        public readonly ?int $depositoId,
        public readonly ?string $depositoOrigenCodigo,
        public readonly ?string $depositoOrigenNombre,
        public readonly ?int $depositoOrigenId,
        public readonly ?string $depositoDestinoCodigo,
        public readonly ?string $depositoDestinoNombre,
        public readonly ?int $depositoDestinoId,
        public readonly ?string $bienUsoOrigenEtiqueta,
        public readonly ?string $bienUsoDestinoEtiqueta,
        public readonly ?string $marcaNombre,
        public readonly ?string $estadoMovimiento,
        public readonly ?string $estadoTransferencia,
        public readonly ?int $movSalidaId,
        public readonly ?int $movEntradaId,
        public readonly ?string $usuarioNombre,
        public readonly ?float $costoProducto = null,
        public readonly ?float $costoProductoTotal = null,
        public readonly ?string $costoProductoOrigen = null,
    ) {}

    public function conCostoProducto(?float $unitario, ?float $total, ?string $origen): self
    {
        return new self(
            filaTipo: $this->filaTipo,
            pkId: $this->pkId,
            fecha: $this->fecha,
            movimiento: $this->movimiento,
            transferencia: $this->transferencia,
            totalCantidad: $this->totalCantidad,
            itemsCount: $this->itemsCount,
            codigoListado: $this->codigoListado,
            tipoNombre: $this->tipoNombre,
            leyendaListado: $this->leyendaListado,
            loteListado: $this->loteListado,
            nombreEmpresa: $this->nombreEmpresa,
            depositoCodigo: $this->depositoCodigo,
            depositoNombre: $this->depositoNombre,
            depositoId: $this->depositoId,
            depositoOrigenCodigo: $this->depositoOrigenCodigo,
            depositoOrigenNombre: $this->depositoOrigenNombre,
            depositoOrigenId: $this->depositoOrigenId,
            depositoDestinoCodigo: $this->depositoDestinoCodigo,
            depositoDestinoNombre: $this->depositoDestinoNombre,
            depositoDestinoId: $this->depositoDestinoId,
            bienUsoOrigenEtiqueta: $this->bienUsoOrigenEtiqueta,
            bienUsoDestinoEtiqueta: $this->bienUsoDestinoEtiqueta,
            marcaNombre: $this->marcaNombre,
            estadoMovimiento: $this->estadoMovimiento,
            estadoTransferencia: $this->estadoTransferencia,
            movSalidaId: $this->movSalidaId,
            movEntradaId: $this->movEntradaId,
            usuarioNombre: $this->usuarioNombre,
            costoProducto: $unitario,
            costoProductoTotal: $total,
            costoProductoOrigen: $origen,
        );
    }

    public function esTransferencia(): bool
    {
        return $this->filaTipo === 'transferencia';
    }

    public function etiquetaEstadoTransferencia(): ?string
    {
        if ($this->estadoTransferencia === null || $this->estadoTransferencia === '') {
            return null;
        }

        return TransferenciaMercaderiaEstados::etiqueta($this->estadoTransferencia);
    }

    public function etiquetaEstadoListado(): string
    {
        if ($this->esTransferencia()) {
            return $this->etiquetaEstadoTransferencia() ?? ($this->estadoTransferencia ?? '—');
        }

        $enum = (new MovimientoStock)->estadoEnum();

        return $enum[$this->estadoMovimiento ?? ''] ?? ($this->estadoMovimiento ?? '—');
    }

    public function etiquetaDeposito(): string
    {
        if ($this->esTransferencia()) {
            return '—';
        }

        return Depmae::etiquetaDesdePartes(
            (string) ($this->depositoCodigo ?? ''),
            (string) ($this->depositoNombre ?? ''),
            (int) ($this->depositoId ?? 0),
        );
    }

    public function etiquetaOrigen(): string
    {
        if (! $this->esTransferencia()) {
            return '—';
        }

        if ($this->transferencia?->depositoOrigen) {
            return $this->transferencia->depositoOrigen->etiqueta();
        }

        if ($this->transferencia?->bienUsoOrigen) {
            return TransferenciaBienUsoSupport::etiquetaBien($this->transferencia->bienUsoOrigen);
        }

        $etqDeposito = Depmae::etiquetaDesdePartes(
            (string) ($this->depositoOrigenCodigo ?? ''),
            (string) ($this->depositoOrigenNombre ?? ''),
            (int) ($this->depositoOrigenId ?? 0),
        );
        if ($etqDeposito !== '—') {
            return $etqDeposito;
        }

        $etq = trim((string) ($this->bienUsoOrigenEtiqueta ?? ''));

        return $etq !== '' ? $etq : '—';
    }

    public function etiquetaDestino(): string
    {
        if (! $this->esTransferencia()) {
            return $this->etiquetaDeposito();
        }

        if ($this->transferencia?->depositoDestino) {
            return $this->transferencia->depositoDestino->etiqueta();
        }

        if ($this->transferencia?->bienUsoDestino) {
            return TransferenciaBienUsoSupport::etiquetaBien($this->transferencia->bienUsoDestino);
        }

        $etqDeposito = Depmae::etiquetaDesdePartes(
            (string) ($this->depositoDestinoCodigo ?? ''),
            (string) ($this->depositoDestinoNombre ?? ''),
            (int) ($this->depositoDestinoId ?? 0),
        );
        if ($etqDeposito !== '—') {
            return $etqDeposito;
        }

        $etq = trim((string) ($this->bienUsoDestinoEtiqueta ?? ''));

        return $etq !== '' ? $etq : '—';
    }

    /**
     * @param  Collection<int, MovimientoStock>  $movimientos
     * @param  Collection<int, Transferencia_Mercaderia>  $transferencias
     */
    public static function desdeRaw(object $raw, Collection $movimientos, Collection $transferencias): self
    {
        $filaTipo = (string) ($raw->fila_tipo ?? 'movimiento');
        $transferencia = null;
        $movimiento = null;

        if ($filaTipo === 'transferencia') {
            $transferencia = $transferencias->get((int) ($raw->transferencia_id ?? 0));
        } else {
            $movimiento = $movimientos->get((int) ($raw->movimientostock_id ?? 0));
        }

        return new self(
            filaTipo: $filaTipo,
            pkId: (int) ($raw->pk_id ?? 0),
            fecha: $raw->fecha ? (string) $raw->fecha : null,
            movimiento: $movimiento,
            transferencia: $transferencia,
            totalCantidad: (float) ($raw->total_cantidad ?? 0),
            itemsCount: (int) ($raw->items_count ?? 0),
            codigoListado: (string) ($raw->codigo_listado ?? ''),
            tipoNombre: (string) ($raw->tipo_nombre ?? ''),
            leyendaListado: (string) ($raw->leyenda_listado ?? ''),
            loteListado: (string) ($raw->lote_listado ?? ''),
            nombreEmpresa: (string) ($raw->nombreempresa ?? ''),
            depositoCodigo: $raw->deposito_codigo ?? null,
            depositoNombre: $raw->deposito_nombre ?? null,
            depositoId: isset($raw->deposito_id_listado) ? (int) $raw->deposito_id_listado : null,
            depositoOrigenCodigo: $raw->deposito_origen_codigo ?? null,
            depositoOrigenNombre: $raw->deposito_origen_nombre ?? null,
            depositoOrigenId: isset($raw->deposito_origen_id) ? (int) $raw->deposito_origen_id : null,
            depositoDestinoCodigo: $raw->deposito_destino_codigo ?? null,
            depositoDestinoNombre: $raw->deposito_destino_nombre ?? null,
            depositoDestinoId: isset($raw->deposito_destino_id) ? (int) $raw->deposito_destino_id : null,
            bienUsoOrigenEtiqueta: $raw->bien_uso_origen_etiqueta ?? null,
            bienUsoDestinoEtiqueta: $raw->bien_uso_destino_etiqueta ?? null,
            marcaNombre: $raw->marca_nombre ?? null,
            estadoMovimiento: $raw->estado_movimiento ?? null,
            estadoTransferencia: $raw->estado_transferencia ?? null,
            movSalidaId: isset($raw->mov_salida_id) ? (int) $raw->mov_salida_id : null,
            movEntradaId: isset($raw->mov_entrada_id) ? (int) $raw->mov_entrada_id : null,
            usuarioNombre: $raw->usuario_nombre ?? null,
        );
    }
}
