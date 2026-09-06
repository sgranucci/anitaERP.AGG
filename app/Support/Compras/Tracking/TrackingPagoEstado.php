<?php

namespace App\Support\Compras\Tracking;

/**
 * Estado de pago de un comprobante, con el origen del dato.
 *
 * La cuenta corriente del ERP sólo cubre los comprobantes nativos (unos cientos
 * sobre veinte mil): el resto se importó sin cuenta corriente y su deuda vive en
 * `promov` del Anita. El tracking necesita las dos fuentes para que la búsqueda
 * «sin pagar» no devuelva sólo lo nuevo.
 */
final class TrackingPagoEstado
{
    public const PAGADO = 'PAGADO';

    public const PARCIAL = 'PARCIAL';

    public const SIN_PAGAR = 'SIN_PAGAR';

    /** Ni el ERP ni el Anita tienen cuenta corriente para el comprobante. */
    public const SIN_DATO = 'SIN_DATO';

    public const ORIGEN_ERP = 'erp';

    public const ORIGEN_ANITA = 'anita';

    /** Tolerancia de redondeo para considerar un saldo cancelado. */
    private const EPSILON = 0.01;

    private function __construct(
        public readonly string $estado,
        public readonly string $origen,
        public readonly float $monto,
        public readonly float $pagado,
        public readonly float $saldo,
        public readonly ?string $fechaPago = null,
        /** Etiqueta de la orden de pago que canceló el comprobante. */
        public readonly ?string $opReferencia = null,
        /** Cuántas OP intervinieron: un comprobante en cuotas lleva varias. */
        public readonly int $opCantidad = 0,
        /** Id de `pagoproveedor`. Sólo existe para lo nativo del ERP. */
        public readonly ?int $opId = null,
    ) {}

    /**
     * Se compara en valor absoluto porque las notas de crédito llevan monto y
     * saldo negativos: un saldo de -1.000 es tan pendiente como uno de +1.000,
     * y comparar con signo las marcaría a todas como sin pagar.
     */
    public static function desdeMontos(
        string $origen,
        float $monto,
        float $saldo,
        ?string $fechaPago = null,
        ?string $opReferencia = null,
        int $opCantidad = 0,
        ?int $opId = null,
    ): self {
        $monto = round($monto, 4);
        $saldo = round($saldo, 4);
        $pagado = round($monto - $saldo, 4);

        $estado = match (true) {
            abs($saldo) < self::EPSILON => self::PAGADO,
            abs($pagado) > self::EPSILON => self::PARCIAL,
            default => self::SIN_PAGAR,
        };

        $opReferencia = trim((string) $opReferencia) !== '' ? trim((string) $opReferencia) : null;

        return new self(
            $estado, $origen, $monto, $pagado, $saldo, $fechaPago,
            $opReferencia, $opCantidad, $opId,
        );
    }

    public static function sinDato(): self
    {
        return new self(self::SIN_DATO, '', 0.0, 0.0, 0.0);
    }

    /**
     * Copia con la orden de pago agregada.
     *
     * El estado se arma primero con los montos y recién después se sabe qué OP
     * lo canceló: en lo importado del Anita los montos salen de `promov` y la
     * OP de `aplmovp`, que son dos consultas distintas al puente.
     */
    public function conOrdenPago(?string $referencia, int $cantidad, ?int $id = null): self
    {
        $referencia = trim((string) $referencia) !== '' ? trim((string) $referencia) : null;

        return new self(
            $this->estado, $this->origen, $this->monto, $this->pagado, $this->saldo,
            $this->fechaPago, $referencia, $cantidad, $id,
        );
    }

    /** Estados que la búsqueda «sin pagar» considera deuda viva. */
    public static function conDeuda(): array
    {
        return [self::SIN_PAGAR, self::PARCIAL];
    }

    public static function etiqueta(?string $estado): string
    {
        return match (strtoupper(trim((string) $estado))) {
            self::PAGADO => 'Pagado',
            self::PARCIAL => 'Pago parcial',
            self::SIN_PAGAR => 'Sin pagar',
            self::SIN_DATO => 'Sin datos',
            default => '—',
        };
    }

    /**
     * Una nota de crédito con saldo no está «sin pagar»: está sin aplicar.
     * El signo del monto distingue los dos casos.
     */
    public function etiquetaSegunSigno(): string
    {
        if ($this->monto >= 0) {
            return self::etiqueta($this->estado);
        }

        return match ($this->estado) {
            self::SIN_PAGAR => 'Sin aplicar',
            self::PARCIAL => 'Aplicada en parte',
            default => self::etiqueta($this->estado),
        };
    }

    public static function badge(?string $estado): string
    {
        return match (strtoupper(trim((string) $estado))) {
            self::PAGADO => 'badge badge-success',
            self::PARCIAL => 'badge badge-warning',
            self::SIN_PAGAR => 'badge badge-danger',
            default => 'badge badge-light',
        };
    }
}
