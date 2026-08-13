<?php

namespace App\Support\Compras;

use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Fila del index unificado: OP de `pagoproveedor` o OPP de Ingresos/Egresos.
 */
final class PagoproveedorListadoFila
{
    public const ORIGEN_PAGOPROVEEDOR = 'pagoproveedor';

    public const ORIGEN_IE_OPP = 'ie_opp';

    public string $nombreempresa;

    public function __construct(
        public readonly string $origen,
        public readonly int $id,
        public readonly ?CarbonInterface $fecha,
        public readonly string $etiqueta,
        public readonly string $nombreEmpresa,
        public readonly string $nombreProveedor,
        public readonly float $monto,
        public readonly string $monedaAbreviatura,
        public readonly string $estado,
        public readonly string $detalle,
        public readonly ?int $solicitudpagoId = null,
    ) {
        $this->nombreempresa = $this->nombreEmpresa;
    }

    public function esIeOpp(): bool
    {
        return $this->origen === self::ORIGEN_IE_OPP;
    }

    public function etiquetaComprobante(): string
    {
        return $this->etiqueta;
    }

    /**
     * Compatibilidad con vistas/export que usan relaciones Eloquent.
     */
    public function __get(string $name): mixed
    {
        return match ($name) {
            'empresas' => (object) ['nombre' => $this->nombreEmpresa],
            'proveedores' => (object) ['nombre' => $this->nombreProveedor],
            'monedas' => (object) ['abreviatura' => $this->monedaAbreviatura],
            'monto' => $this->monto,
            'estado' => $this->estado,
            'detalle' => $this->detalle,
            'fecha' => $this->fecha,
            'id' => $this->id,
            'nombreempresa' => $this->nombreempresa,
            default => null,
        };
    }

    public function __isset(string $name): bool
    {
        return in_array($name, [
            'empresas', 'proveedores', 'monedas', 'monto', 'estado', 'detalle', 'fecha', 'id', 'nombreempresa',
        ], true);
    }

    public static function desdeUnionRow(object $row): self
    {
        $fecha = null;
        if (! empty($row->fecha)) {
            $fecha = $row->fecha instanceof CarbonInterface
                ? $row->fecha
                : Carbon::parse((string) $row->fecha)->startOfDay();
        }

        $origen = (string) $row->origen;
        $etiqueta = $origen === self::ORIGEN_IE_OPP
            ? 'OPP '.(string) ($row->numerotransaccion ?? '')
            : sprintf(
                '%s %s%04d-%s',
                (string) ($row->tipocomprobante ?: 'OP'),
                (string) ($row->letra ?? ''),
                (int) ($row->sucursal ?? 0),
                (string) ($row->numerotransaccion ?? '')
            );

        return new self(
            origen: $origen,
            id: (int) $row->pk_id,
            fecha: $fecha,
            etiqueta: trim($etiqueta),
            nombreEmpresa: (string) ($row->nombreempresa ?? ''),
            nombreProveedor: (string) ($row->nombreproveedor ?? ''),
            monto: (float) ($row->monto ?? 0),
            monedaAbreviatura: (string) ($row->moneda_abrev ?? ''),
            estado: (string) ($row->estado ?? ''),
            detalle: (string) ($row->detalle ?? ''),
            solicitudpagoId: isset($row->solicitudpago_id) && (int) $row->solicitudpago_id > 0
                ? (int) $row->solicitudpago_id
                : null,
        );
    }
}
