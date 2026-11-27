<?php

declare(strict_types=1);

namespace App\Support\Compras;

/**
 * Una fila del listado de PDFs (factura, OC, COM, OPP u OPA).
 *
 * `origen` = 'anita' para facturas que el ERP no tiene y se leyeron de la tabla compra de Anita
 * (tipo 'anita'); `anita` trae los datos para buscar el PDF escaneado.
 */
final class ComprobantesPdfLoteFila
{
    public const ORIGEN_ERP = 'erp';

    public const ORIGEN_ANITA = 'anita';

    /**
     * @param  array<string, mixed>  $anita
     */
    public function __construct(
        public readonly string $clave,
        public readonly string $tipo,
        public readonly int $id,
        public readonly string $fecha,
        public readonly string $fechaIso,
        public readonly string $proveedorCodigo,
        public readonly string $proveedor,
        public readonly string $tipoEtiqueta,
        public readonly string $numero,
        public readonly float $total,
        public bool $tienePdf,
        public string $pdfEtiqueta,
        public readonly ?string $editarUrl,
        public ?string $pdfUrl,
        public readonly string $servicioCliente = '',
        public readonly string $origen = self::ORIGEN_ERP,
        public array $anita = [],
    ) {}

    public function esAnita(): bool
    {
        return $this->origen === self::ORIGEN_ANITA;
    }
}
