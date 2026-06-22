<?php

namespace App\Support\Stock;

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
        public readonly ?string $depositoNombre,
        public readonly ?string $depositoOrigenNombre,
        public readonly ?string $depositoDestinoNombre,
        public readonly ?string $bienUsoOrigenEtiqueta,
        public readonly ?string $bienUsoDestinoEtiqueta,
        public readonly ?string $marcaNombre,
        public readonly ?string $estadoMovimiento,
        public readonly ?string $estadoTransferencia,
        public readonly ?int $movSalidaId,
        public readonly ?int $movEntradaId,
        public readonly ?string $usuarioNombre,
    ) {}

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

    public function etiquetaOrigen(): string
    {
        if ($this->depositoOrigenNombre) {
            return $this->depositoOrigenNombre;
        }
        $etq = trim((string) ($this->bienUsoOrigenEtiqueta ?? ''));

        return $etq !== '' ? $etq : '—';
    }

    public function etiquetaDestino(): string
    {
        if ($this->depositoDestinoNombre) {
            return $this->depositoDestinoNombre;
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
            depositoNombre: $raw->deposito_nombre ?? null,
            depositoOrigenNombre: $raw->deposito_origen_nombre ?? null,
            depositoDestinoNombre: $raw->deposito_destino_nombre ?? null,
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
