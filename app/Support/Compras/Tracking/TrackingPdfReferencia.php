<?php

namespace App\Support\Compras\Tracking;

/**
 * Resultado de resolver el PDF de un comprobante: de dónde sale y si se puede leer.
 */
final class TrackingPdfReferencia
{
    /** Adjunto del comprobante (tabla comprobante_proveedor_archivo). */
    public const ORIGEN_ADJUNTO = 'adjunto';

    /** PDF de la precarga IA en el montaje Facturas_scan. */
    public const ORIGEN_PRECARGA = 'precarga';

    /** PDF por convención de nombre bajo Facturas_scan/comprobantes. */
    public const ORIGEN_CONVENCION = 'convencion';

    /** Escaneo histórico del Anita (base_admin.scanfactura → /scan/compras/documentos). */
    public const ORIGEN_ANITA = 'anita';

    private function __construct(
        public readonly string $origen,
        public readonly ?string $ruta,
        public readonly ?int $documentoId = null,
        public readonly ?int $archivoId = null,
        /** Fecha de escaneo en el Anita: la única fecha de carga real de lo importado. */
        public readonly ?string $fechaScan = null,
    ) {}

    public static function adjunto(string $ruta, int $archivoId): self
    {
        return new self(self::ORIGEN_ADJUNTO, $ruta, null, $archivoId);
    }

    public static function precarga(string $ruta): self
    {
        return new self(self::ORIGEN_PRECARGA, $ruta);
    }

    public static function convencion(string $ruta): self
    {
        return new self(self::ORIGEN_CONVENCION, $ruta);
    }

    public static function anita(string $ruta, int $documentoId, ?string $fechaScan = null): self
    {
        return new self(self::ORIGEN_ANITA, $ruta, $documentoId, null, $fechaScan);
    }

    public function etiqueta(): string
    {
        return match ($this->origen) {
            self::ORIGEN_ADJUNTO => 'Adjunto',
            self::ORIGEN_PRECARGA => 'Precarga IA',
            self::ORIGEN_CONVENCION => 'Facturas_scan',
            self::ORIGEN_ANITA => 'Escaneo Anita',
            default => $this->origen,
        };
    }
}
