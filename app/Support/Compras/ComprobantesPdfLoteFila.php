<?php

declare(strict_types=1);

namespace App\Support\Compras;

/**
 * Una fila del listado de PDFs (factura, OC, COM, OPP u OPA).
 */
final class ComprobantesPdfLoteFila
{
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
        public readonly ?string $pdfUrl,
    ) {}
}
