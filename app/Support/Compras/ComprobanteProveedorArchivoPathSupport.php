<?php

namespace App\Support\Compras;

use App\Models\Compras\Comprobante_Proveedor;
use App\Models\Compras\Proveedor;
use Carbon\Carbon;

/**
 * Rutas bajo el montaje de facturas escaneadas: {base}/comprobantes/{CUIT}/{Y-m}/{archivo}.pdf
 * Misma convención que la precarga del agente IA (PrecargaFacturaScanPathResolver).
 */
final class ComprobanteProveedorArchivoPathSupport
{
    public function __construct(
        private PrecargaFacturaScanPathResolver $scanPathResolver = new PrecargaFacturaScanPathResolver(),
    ) {}

    /**
     * CUIT con guiones para carpeta (ej. 30-65781386-5).
     */
    public static function cuitCarpeta(?string $nroInscripcion): string
    {
        $digits = preg_replace('/\D/', '', trim((string) $nroInscripcion)) ?? '';

        if (strlen($digits) === 11) {
            return substr($digits, 0, 2).'-'.substr($digits, 2, 8).'-'.substr($digits, 10, 1);
        }

        $limpio = trim((string) $nroInscripcion);

        return $limpio !== '' ? $limpio : 'sin-cuit';
    }

    /**
     * Nombre de archivo canónico: FGA-A-00003-00946427.pdf
     */
    public static function nombrePdfComprobante(
        string $tipoAbreviatura,
        string $letra,
        int $sucursal,
        int $numerocomprobante,
    ): string {
        $tipo = strtoupper(trim($tipoAbreviatura));
        $letra = strtoupper(substr(trim($letra), 0, 1));

        return sprintf(
            '%s-%s-%s-%s.pdf',
            $tipo,
            $letra,
            str_pad((string) $sucursal, 5, '0', STR_PAD_LEFT),
            str_pad((string) $numerocomprobante, 8, '0', STR_PAD_LEFT),
        );
    }

    /**
     * Ruta relativa dentro de comprobantes: {CUIT}/{Y-m}/{nombre}.pdf
     */
    public function relativePathDesdeComprobante(Comprobante_Proveedor $comprobante): string
    {
        $comprobante->loadMissing(['proveedores', 'tipotransaccion_compras']);

        $cuitDoc = $comprobante->proveedores?->nroinscripcion ?? $comprobante->proveedor_documento_eventual;
        $cuit = self::cuitCarpeta($cuitDoc);
        $fecha = $comprobante->fechacomprobante
            ? Carbon::parse($comprobante->fechacomprobante)
            : now();
        $ym = $fecha->format('Y-m');
        $nombre = self::nombrePdfComprobante(
            (string) ($comprobante->tipotransaccion_compras?->abreviatura ?? 'FAC'),
            (string) $comprobante->letra,
            (int) $comprobante->sucursal,
            (int) $comprobante->numerocomprobante,
        );

        return $cuit.'/'.$ym.'/'.$nombre;
    }

    /**
     * Referencia del PDF original de precarga (ruta_externa ORIGEN_IA o rutaalmacenamiento).
     */
    public static function referenciaPdfPrecarga(Comprobante_Proveedor $comprobante): ?string
    {
        $comprobante->loadMissing(['comprobante_proveedor_archivos', 'precarga_comprobante_proveedores']);

        $ruta = $comprobante->comprobante_proveedor_archivos
            ->firstWhere('tipo', ComprobanteProveedorArchivoTipos::ORIGEN_IA)
            ?->ruta_externa;

        if (! filled($ruta) && $comprobante->precarga_comprobante_proveedores) {
            $ruta = $comprobante->precarga_comprobante_proveedores->rutaalmacenamiento;
        }

        $ruta = trim((string) $ruta);

        return $ruta !== '' ? $ruta : null;
    }

    /**
     * Valor para rutaalmacenamiento / ruta_externa (compatible con precarga).
     */
    public function storageReferenceDesdeComprobante(Comprobante_Proveedor $comprobante): string
    {
        return 'storage:/comprobantes/'.$this->relativePathDesdeComprobante($comprobante);
    }

    public function absolutePathDesdeRelative(string $relative): ?string
    {
        return $this->scanPathResolver->resolve('storage:/comprobantes/'.ltrim($relative, '/'));
    }

    public function absolutePathDesdeComprobante(Comprobante_Proveedor $comprobante): ?string
    {
        return $this->scanPathResolver->resolve($this->storageReferenceDesdeComprobante($comprobante));
    }

    public function absolutePathDesdeStorageReference(?string $rutaAlmacenamiento): ?string
    {
        return $this->scanPathResolver->resolve($rutaAlmacenamiento);
    }

    public function directorioAbsolutoParaComprobante(Comprobante_Proveedor $comprobante): string
    {
        $relative = $this->relativePathDesdeComprobante($comprobante);
        $dir = dirname($relative);

        return rtrim($this->scanPathResolver->comprobantesBasePath(), '/').'/'.$dir;
    }

    public function proveedorCuitCarpeta(Proveedor $proveedor): string
    {
        return self::cuitCarpeta($proveedor->nroinscripcion);
    }
}
