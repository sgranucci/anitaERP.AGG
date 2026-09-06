<?php

namespace App\Support\Compras\Tracking;

use App\Services\Compras\Tracking\TrackingIndiceSyncService;
use App\Support\Compras\ComprobanteProveedorEstados;
use App\Support\Compras\ComprobanteProveedorInternoTipos;
use Carbon\Carbon;

/**
 * Presentación de una fila del tracking.
 *
 * La grilla, el PDF y el Excel muestran los mismos datos derivados (familia,
 * número armado, estado contable, estado de pago, origen del PDF). Concentrarlos
 * acá evita que las tres vistas se desincronicen y deja la lógica testeable.
 */
final class TrackingFacturaFila
{
    private function __construct(
        private readonly object $fila,
    ) {}

    public static function de(object $fila): self
    {
        return new self($fila);
    }

    public function id(): int
    {
        return (int) ($this->fila->id ?? 0);
    }

    public function familia(): string
    {
        return TrackingComprobanteFamilia::desde(
            $this->fila->codigoafiptipotransaccion_compra ?? null,
            $this->fila->abreviaturatipotransaccion_compra ?? null,
        );
    }

    public function familiaEtiqueta(): string
    {
        return TrackingComprobanteFamilia::etiqueta($this->familia());
    }

    /**
     * Abreviatura real del tipo (FIB, CGA, DIS…), que es lo que el usuario
     * reconoce del sistema anterior; la familia es sólo el agrupador.
     */
    public function tipoAbreviatura(): string
    {
        return trim((string) ($this->fila->abreviaturatipotransaccion_compra ?? ''));
    }

    public function tipoNombre(): string
    {
        return trim((string) ($this->fila->nombretipotransaccion_compra ?? ''));
    }

    /**
     * Número fiscal con el formato de la AFIP: letra + sucursal + número.
     */
    public function numero(): string
    {
        $letra = trim((string) ($this->fila->letra ?? ''));

        return trim(sprintf(
            '%s %04d-%08d',
            $letra,
            (int) ($this->fila->sucursal ?? 0),
            (int) ($this->fila->numerocomprobante ?? 0),
        ));
    }

    public function empresa(): string
    {
        return trim((string) ($this->fila->nombreempresa ?? ''));
    }

    public function proveedor(): string
    {
        return trim((string) ($this->fila->nombreproveedor ?? ''));
    }

    public function cuit(): string
    {
        return trim((string) ($this->fila->cuitproveedor ?? ''));
    }

    public function total(): float
    {
        return (float) ($this->fila->total ?? 0);
    }

    public function fechaComprobante(): string
    {
        return $this->formatearFecha($this->fila->fechacomprobante ?? null);
    }

    public function fechaCarga(): string
    {
        return $this->formatearFecha($this->fila->fechacarga_efectiva ?? null);
    }

    public function fechaPago(): string
    {
        return $this->formatearFecha($this->fila->pago_fecha ?? null);
    }

    /**
     * Fecha del asiento, que es cuándo se contabilizó realmente.
     *
     * No coincide con la del comprobante: en el histórico importado el asiento
     * se armó días después en 14.390 de 15.234 casos.
     */
    public function fechaContabilizacion(): string
    {
        return $this->formatearFecha($this->fila->fechacontabilizacion ?? null);
    }

    public function numeroAsiento(): int
    {
        return (int) ($this->fila->numeroasiento ?? 0);
    }

    public function numeroOrdencompra(): string
    {
        return trim((string) ($this->fila->numeroordencompra ?? ''));
    }

    public function ordencompraId(): int
    {
        return (int) ($this->fila->ordencompra_id ?? 0);
    }

    /** Etiqueta de la orden de pago que canceló el comprobante. */
    public function ordenPago(): string
    {
        return trim((string) ($this->fila->pago_op_referencia ?? ''));
    }

    /**
     * Cuántas OP de más hay además de la que se muestra.
     *
     * Un comprobante en cuotas se cancela con varias y en la grilla entra una
     * sola: el resto se anuncia como «+2» para que el dato no quede escondido.
     */
    public function ordenesPagoExtra(): int
    {
        return max(0, (int) ($this->fila->pago_op_cantidad ?? 0) - 1);
    }

    /** Id de `pagoproveedor`, o 0 si la OP vino importada del Anita. */
    public function ordenPagoId(): int
    {
        return (int) ($this->fila->pago_op_id ?? 0);
    }

    /**
     * De dónde salió la fecha de carga.
     *
     * Importa mostrarlo: en el histórico importado el alta en el ERP es la
     * fecha de la migración, no la de carga, y el usuario tiene que poder
     * distinguir un dato real de un relleno.
     */
    public function fechaCargaOrigen(): string
    {
        $origen = trim((string) ($this->fila->fechacarga_origen ?? ''));

        return TrackingIndiceSyncService::etiquetasOrigenFecha()[$origen] ?? '';
    }

    public function contabilizado(): bool
    {
        return (string) ($this->fila->estado ?? '') === ComprobanteProveedorEstados::CONTABILIZADO
            && (int) ($this->fila->asiento_id ?? 0) > 0;
    }

    public function anulado(): bool
    {
        return (string) ($this->fila->estado ?? '') === ComprobanteProveedorEstados::ANULADO;
    }

    /**
     * @return array{clase: string, etiqueta: string}
     */
    public function estadoContable(): array
    {
        if ($this->anulado()) {
            return ['clase' => 'tf-neutro', 'etiqueta' => 'Anulado'];
        }

        if ($this->contabilizado()) {
            return ['clase' => 'tf-info', 'etiqueta' => 'Contabilizado'];
        }

        return ['clase' => 'tf-pendiente', 'etiqueta' => 'Sin contabilizar'];
    }

    /**
     * @return array{clase: string, etiqueta: string}
     */
    public function estadoPago(): array
    {
        $estado = trim((string) ($this->fila->pago_estado ?? ''));
        if ($estado === '') {
            return ['clase' => 'tf-neutro', 'etiqueta' => 'Sin resolver'];
        }

        $monto = (float) ($this->fila->pago_monto ?? $this->total());
        $presentacion = TrackingPagoEstado::desdeMontos(
            (string) ($this->fila->pago_origen ?? ''),
            $monto,
            (float) ($this->fila->pago_saldo ?? 0),
        );

        return [
            'clase' => match ($estado) {
                TrackingPagoEstado::PAGADO => 'tf-ok',
                TrackingPagoEstado::PARCIAL => 'tf-pendiente',
                TrackingPagoEstado::SIN_PAGAR => 'tf-alerta',
                default => 'tf-neutro',
            },
            'etiqueta' => $presentacion->etiquetaSegunSigno(),
        ];
    }

    /**
     * Antigüedad de la deuda, sólo cuando el comprobante tiene saldo pendiente.
     *
     * Prefiere el vencimiento; si no hay uno usable, cae a la fecha del
     * comprobante. Días negativos = a vencer.
     *
     * @return array{dias: int, tramo: string, etiqueta: string, clase: string, origen: string}|null
     */
    public function antiguedadDeuda(): ?array
    {
        $estado = trim((string) ($this->fila->pago_estado ?? ''));
        if (! in_array($estado, TrackingPagoEstado::conDeuda(), true)) {
            return null;
        }

        [$fecha, $origen] = TrackingAntiguedadDeuda::fechaBase(
            isset($this->fila->fechavencimiento) ? (string) $this->fila->fechavencimiento : null,
            isset($this->fila->fechacomprobante) ? (string) $this->fila->fechacomprobante : null,
        );
        $dias = TrackingAntiguedadDeuda::dias($fecha);
        $tramo = TrackingAntiguedadDeuda::tramo($dias);
        if ($dias === null || $tramo === null || $origen === null) {
            return null;
        }

        return [
            'dias' => $dias,
            'tramo' => $tramo,
            'etiqueta' => TrackingAntiguedadDeuda::etiqueta($tramo),
            'clase' => TrackingAntiguedadDeuda::clasePill($tramo),
            'origen' => $origen,
        ];
    }

    public function saldo(): float
    {
        return (float) ($this->fila->pago_saldo ?? 0);
    }

    public function tienePdf(): bool
    {
        return (bool) ($this->fila->pdf_disponible ?? false);
    }

    /**
     * FIN/CIN no se escanean: el ERP les arma un PDF interno con logo.
     * La grilla muestra el botón de PDF aunque el índice diga «sin PDF».
     */
    public function generaPdfInterno(): bool
    {
        return ComprobanteProveedorInternoTipos::esInterno($this->tipoAbreviatura());
    }

    public function puedeVerPdf(): bool
    {
        return $this->tienePdf() || $this->generaPdfInterno();
    }

    public function pdfOrigen(): string
    {
        if (! $this->tienePdf() && $this->generaPdfInterno()) {
            return 'Interno ERP';
        }

        $origen = trim((string) ($this->fila->pdf_origen ?? ''));
        $etiquetas = [
            TrackingPdfReferencia::ORIGEN_ADJUNTO => 'Adjunto',
            TrackingPdfReferencia::ORIGEN_PRECARGA => 'Precarga',
            TrackingPdfReferencia::ORIGEN_CONVENCION => 'Facturas_scan',
            TrackingPdfReferencia::ORIGEN_ANITA => 'Escaneo Anita',
        ];

        return $etiquetas[$origen] ?? ($origen !== '' ? $origen : '');
    }

    /**
     * Un comprobante nunca indexado no es lo mismo que uno indexado sin PDF:
     * el primero está pendiente de resolver, el segundo es un faltante real.
     */
    public function indexado(): bool
    {
        return ($this->fila->sincronizado_at ?? null) !== null;
    }

    public function anitaNroInterno(): int
    {
        return (int) ($this->fila->anita_nro_interno ?? 0);
    }

    /**
     * La fecha cero de MySQL ('0000-00-00') no es NULL y Carbon la parsea sin
     * quejarse: se llega a mostrar «30/11/-0001» en la grilla. Cualquier año
     * anterior al arranque del ERP es basura de datos, no una fecha.
     */
    private function formatearFecha(mixed $fecha): string
    {
        if ($fecha === null || $fecha === '') {
            return '';
        }

        try {
            $carbon = Carbon::parse($fecha);
        } catch (\Throwable) {
            return '';
        }

        return $carbon->year >= 2000 ? $carbon->format('d/m/Y') : '';
    }
}
