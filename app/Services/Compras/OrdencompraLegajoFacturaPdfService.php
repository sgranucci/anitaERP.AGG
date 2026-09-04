<?php

namespace App\Services\Compras;

use App\Models\Compras\Ordencompra;
use App\Models\Compras\Precarga_Comprobante_Proveedor;
use App\Models\Compras\Proveedor;
use App\Repositories\Compras\Precarga_Comprobante_ProveedorRepositoryInterface;
use App\Models\Compras\Tipotransaccion_Compra;
use App\Support\Compras\ComprobanteProveedorArchivoPathSupport;
use App\Support\Compras\ComprobanteProveedorUnicidadSupport;
use App\Support\Compras\OrdencompraEnvioCuentasAPagarGateSupport;
use App\Support\Compras\OrdencompraLegajoFacturaArcaSupport;
use App\Support\Compras\PrecargaComprobanteOrigenEntrada;
use App\Support\Compras\PrecargaFacturaScanPathResolver;
use Illuminate\Http\UploadedFile;
use RuntimeException;

/**
 * Adjunta PDF de factura al legajo (OC) creando o actualizando una precarga mínima.
 */
class OrdencompraLegajoFacturaPdfService
{
    public function __construct(
        private Precarga_Comprobante_ProveedorRepositoryInterface $precargaRepository,
        private PrecargaFacturaScanPathResolver $scanPathResolver = new PrecargaFacturaScanPathResolver(),
    ) {}

    /**
     * Asigna PDF + datos ARCA (tipo/letra/sucursal/número/fecha) al legajo.
     * Reemplaza el circuito scanfactura + documentos del Anita web viejo.
     */
    public function asignarPdfConDatosArca(
        Ordencompra $oc,
        UploadedFile $pdf,
        string $tipoArca,
        string $letra,
        int $sucursal,
        int $numerocomprobante,
        string $fechafactura,
    ): Precarga_Comprobante_Proveedor {
        $this->assertPdfValido($pdf);

        $letra = OrdencompraLegajoFacturaArcaSupport::normalizarLetra($letra);
        $codigoAfip = OrdencompraLegajoFacturaArcaSupport::codigoArcaPad($tipoArca, $letra);
        if (OrdencompraLegajoFacturaArcaSupport::codigoArcaEfectivo($tipoArca, $letra) <= 0 || $letra === '') {
            throw new RuntimeException('Indique tipo ARCA y letra de la factura.');
        }
        if ($sucursal < 0 || $numerocomprobante <= 0) {
            throw new RuntimeException('Sucursal y número de factura son obligatorios.');
        }
        $fechaYmd = $this->normalizarFecha($fechafactura);

        $proveedorId = (int) ($oc->proveedor_id ?? 0);
        $empresaId = (int) ($oc->empresa_id ?? 0);
        if ($proveedorId <= 0 || $empresaId <= 0) {
            throw new RuntimeException('La orden de compra debe tener empresa y proveedor para asignar la factura.');
        }

        $proveedor = Proveedor::query()->find($proveedorId);
        if (! $proveedor) {
            throw new RuntimeException('Proveedor de la orden de compra inexistente.');
        }

        $tipoId = OrdencompraEnvioCuentasAPagarGateSupport::tipotransaccionCompraIdPorCodigoAfip($codigoAfip);
        if ($tipoId <= 0) {
            throw new RuntimeException(
                'No hay tipo de transacción de compra con código ARCA '.$codigoAfip.'. Cárguelo en tipos de transacción de compra.'
            );
        }

        $cuit = ComprobanteProveedorUnicidadSupport::resolverCuitDigitos($proveedorId, null);
        $dup = ComprobanteProveedorUnicidadSupport::findDuplicadoPrecarga(
            $empresaId,
            $tipoId,
            $letra,
            $sucursal,
            $numerocomprobante,
            $cuit,
        );
        $existenteLegajo = OrdencompraEnvioCuentasAPagarGateSupport::precargaDelLegajoSinPdf($oc)
            ?? OrdencompraEnvioCuentasAPagarGateSupport::resolverPrecargaConPdf($oc);

        if ($dup) {
            $ocDup = trim((string) ($dup->numeroordencompra ?? ''));
            $ocEsta = trim((string) $oc->numeroordencompra);
            if ($ocDup !== '' && $ocEsta !== '' && $ocDup !== $ocEsta) {
                throw new RuntimeException(
                    'Ya existe una precarga con esa factura (OC '.$ocDup.'). No se puede asignar al legajo actual.'
                );
            }
        }

        $abrev = (string) (Tipotransaccion_Compra::query()->whereKey($tipoId)->value('abreviatura') ?? 'FAC');
        $ruta = $this->guardarPdfCanonico($pdf, $proveedor, $fechaYmd, $abrev, $letra, $sucursal, $numerocomprobante);

        $payload = [
            'empresa_id' => $empresaId,
            'proveedor_id' => $proveedorId,
            'tipotransaccion_compra_id' => $tipoId,
            'letra' => $letra,
            'sucursal' => $sucursal,
            'numerocomprobante' => $numerocomprobante,
            'fechafactura' => $fechaYmd,
            'numeroordencompra' => (string) $oc->numeroordencompra,
            'rutaalmacenamiento' => $ruta,
            'estado' => 'PENDIENTE',
            'pararevisar' => 1,
            'origen_entrada' => PrecargaComprobanteOrigenEntrada::LEGAJO,
            'cotizacion' => 1,
        ];

        $destino = $dup ?: $existenteLegajo;
        if ($destino) {
            $payload['estado'] = $destino->estado ?: 'PENDIENTE';
            $this->precargaRepository->update($payload, $destino->id);

            return Precarga_Comprobante_Proveedor::query()->findOrFail($destino->id);
        }

        $payload['subtotal'] = 0;
        $payload['total'] = 0;

        return $this->precargaRepository->create($payload);
    }

    public function adjuntarPdfAlLegajo(Ordencompra $oc, UploadedFile $pdf): Precarga_Comprobante_Proveedor
    {
        $ext = strtolower((string) $pdf->getClientOriginalExtension());
        $mime = strtolower((string) $pdf->getMimeType());
        if (! $pdf->isValid() || ($ext !== 'pdf' && ! str_contains($mime, 'pdf'))) {
            throw new RuntimeException('Debe adjuntar un archivo PDF válido de la factura.');
        }

        $proveedorId = (int) ($oc->proveedor_id ?? 0);
        $empresaId = (int) ($oc->empresa_id ?? 0);
        if ($proveedorId <= 0 || $empresaId <= 0) {
            throw new RuntimeException('La orden de compra debe tener empresa y proveedor para adjuntar la factura.');
        }

        $proveedor = Proveedor::query()->find($proveedorId);
        if (! $proveedor) {
            throw new RuntimeException('Proveedor de la orden de compra inexistente.');
        }

        $ruta = $this->guardarPdfLegajo($pdf, $oc, $proveedor);

        $existente = OrdencompraEnvioCuentasAPagarGateSupport::precargaDelLegajoSinPdf($oc)
            ?? OrdencompraEnvioCuentasAPagarGateSupport::resolverPrecargaConPdf($oc);

        if ($existente) {
            $this->precargaRepository->update([
                'empresa_id' => $empresaId,
                'proveedor_id' => $proveedorId,
                'tipotransaccion_compra_id' => (int) ($existente->tipotransaccion_compra_id
                    ?: OrdencompraEnvioCuentasAPagarGateSupport::tipotransaccionCompraIdParaOrdencompra($oc)),
                'letra' => (string) ($existente->letra ?: 'A'),
                'sucursal' => (int) ($existente->sucursal ?? 0),
                'numerocomprobante' => (int) ($existente->numerocomprobante ?? 0),
                'numeroordencompra' => (string) $oc->numeroordencompra,
                'rutaalmacenamiento' => $ruta,
                'estado' => $existente->estado ?: 'PENDIENTE',
                'pararevisar' => 1,
            ], $existente->id);

            return Precarga_Comprobante_Proveedor::query()->findOrFail($existente->id);
        }

        $tipoId = OrdencompraEnvioCuentasAPagarGateSupport::tipotransaccionCompraIdParaOrdencompra($oc);
        if ($tipoId <= 0) {
            throw new RuntimeException('No hay tipo de transacción de compra configurado para la precarga.');
        }

        $numeroProvisorio = $this->numeroProvisorioUnico($empresaId, $tipoId, $proveedorId);

        return $this->precargaRepository->create([
            'empresa_id' => $empresaId,
            'proveedor_id' => $proveedorId,
            'tipotransaccion_compra_id' => $tipoId,
            'letra' => 'A',
            'sucursal' => 0,
            'numerocomprobante' => $numeroProvisorio,
            'fechafactura' => now()->format('Y-m-d'),
            'numeroordencompra' => (string) $oc->numeroordencompra,
            'rutaalmacenamiento' => $ruta,
            'subtotal' => 0,
            'total' => 0,
            'estado' => 'PENDIENTE',
            'origen_entrada' => PrecargaComprobanteOrigenEntrada::MANUAL,
            'pararevisar' => 1,
            'cotizacion' => 1,
        ]);
    }

    private function guardarPdfLegajo(UploadedFile $pdf, Ordencompra $oc, Proveedor $proveedor): string
    {
        $comprobantesBase = $this->scanPathResolver->comprobantesBasePath();
        if ($comprobantesBase === '') {
            throw new RuntimeException('Montaje Facturas_scan no configurado (PRECARGA_FACTURAS_SCAN_BASE).');
        }

        $cuit = ComprobanteProveedorArchivoPathSupport::cuitCarpeta($proveedor->nroinscripcion);
        $ym = now()->format('Y-m');
        $nombre = sprintf(
            'LEG-OC-%s-%s.pdf',
            preg_replace('/\D/', '', (string) $oc->numeroordencompra) ?: (string) $oc->id,
            now()->format('YmdHis'),
        );
        $relative = $cuit.'/'.$ym.'/'.$nombre;
        $destDir = rtrim($comprobantesBase, '/').'/'.$cuit.'/'.$ym;

        if (! is_dir($destDir) && ! @mkdir($destDir, 0775, true) && ! is_dir($destDir)) {
            throw new RuntimeException('No se pudo crear el directorio en Facturas_scan.');
        }

        $pdf->move($destDir, $nombre);
        if (! is_readable($destDir.'/'.$nombre)) {
            throw new RuntimeException('No se pudo grabar el PDF en Facturas_scan.');
        }

        return 'storage:/comprobantes/'.$relative;
    }

    /**
     * Copia un PDF local (p. ej. scan Anita) al montaje de precargas: {CUIT}/{Y-m}/{tipo-letra-suc-nro}.pdf
     */
    public function copiarPdfLocalAAlmacenPrecarga(
        string $origenAbsoluto,
        Proveedor $proveedor,
        string $fechaYmd,
        string $tipoAbreviatura,
        string $letra,
        int $sucursal,
        int $numerocomprobante,
    ): string {
        $origenAbsoluto = trim($origenAbsoluto);
        if ($origenAbsoluto === '' || ! is_readable($origenAbsoluto) || ! is_file($origenAbsoluto)) {
            throw new RuntimeException('No se encontró el PDF de origen para copiar a precargas.');
        }

        $comprobantesBase = $this->scanPathResolver->comprobantesBasePath();
        if ($comprobantesBase === '') {
            throw new RuntimeException('Montaje Facturas_scan no configurado (PRECARGA_FACTURAS_SCAN_BASE).');
        }

        $cuit = ComprobanteProveedorArchivoPathSupport::cuitCarpeta($proveedor->nroinscripcion);
        $ym = preg_match('/^\d{4}-\d{2}/', $fechaYmd)
            ? substr($fechaYmd, 0, 7)
            : now()->format('Y-m');
        $nombre = ComprobanteProveedorArchivoPathSupport::nombrePdfComprobante(
            $tipoAbreviatura !== '' ? $tipoAbreviatura : 'FAC',
            $letra,
            $sucursal,
            $numerocomprobante,
        );
        $relative = $cuit.'/'.$ym.'/'.$nombre;
        $destDir = rtrim($comprobantesBase, '/').'/'.$cuit.'/'.$ym;
        $dest = $destDir.'/'.$nombre;

        if (! is_dir($destDir) && ! @mkdir($destDir, 0775, true) && ! is_dir($destDir)) {
            throw new RuntimeException('No se pudo crear el directorio en Facturas_scan.');
        }

        if (! is_readable($dest)) {
            if (! @copy($origenAbsoluto, $dest) || ! is_readable($dest)) {
                throw new RuntimeException('No se pudo copiar el PDF del scan a la carpeta de precargas.');
            }
            @chmod($dest, 0664);
        }

        return 'storage:/comprobantes/'.$relative;
    }

    private function assertPdfValido(UploadedFile $pdf): void
    {
        $ext = strtolower((string) $pdf->getClientOriginalExtension());
        $mime = strtolower((string) $pdf->getMimeType());
        if (! $pdf->isValid() || ($ext !== 'pdf' && ! str_contains($mime, 'pdf'))) {
            throw new RuntimeException('Debe adjuntar un archivo PDF válido de la factura.');
        }
    }

    private function normalizarFecha(string $fecha): string
    {
        $fecha = trim($fecha);
        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $fecha)) {
            return substr($fecha, 0, 10);
        }
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $fecha, $m)) {
            return $m[3].'-'.$m[2].'-'.$m[1];
        }

        throw new RuntimeException('La fecha de la factura no es válida.');
    }

    private function guardarPdfCanonico(
        UploadedFile $pdf,
        Proveedor $proveedor,
        string $fechaYmd,
        string $tipoAbreviatura,
        string $letra,
        int $sucursal,
        int $numerocomprobante,
    ): string {
        $comprobantesBase = $this->scanPathResolver->comprobantesBasePath();
        if ($comprobantesBase === '') {
            throw new RuntimeException('Montaje Facturas_scan no configurado (PRECARGA_FACTURAS_SCAN_BASE).');
        }

        $cuit = ComprobanteProveedorArchivoPathSupport::cuitCarpeta($proveedor->nroinscripcion);
        $ym = substr($fechaYmd, 0, 7);
        $nombre = ComprobanteProveedorArchivoPathSupport::nombrePdfComprobante(
            $tipoAbreviatura !== '' ? $tipoAbreviatura : 'FAC',
            $letra,
            $sucursal,
            $numerocomprobante,
        );
        $relative = $cuit.'/'.$ym.'/'.$nombre;
        $destDir = rtrim($comprobantesBase, '/').'/'.$cuit.'/'.$ym;

        if (! is_dir($destDir) && ! @mkdir($destDir, 0775, true) && ! is_dir($destDir)) {
            throw new RuntimeException('No se pudo crear el directorio en Facturas_scan.');
        }

        $dest = $destDir.'/'.$nombre;
        if (is_file($dest)) {
            @unlink($dest);
        }
        $pdf->move($destDir, $nombre);
        if (! is_readable($destDir.'/'.$nombre)) {
            throw new RuntimeException('No se pudo grabar el PDF en Facturas_scan.');
        }

        return 'storage:/comprobantes/'.$relative;
    }

    private function numeroProvisorioUnico(int $empresaId, int $tipoId, int $proveedorId): int
    {
        $cuit = ComprobanteProveedorUnicidadSupport::resolverCuitDigitos($proveedorId, null);
        for ($i = 0; $i < 20; $i++) {
            $nro = ((int) now()->format('ymdHis') * 10 + $i) % 2000000000;
            if ($nro <= 0) {
                $nro = random_int(1, 1999999999);
            }
            $dup = ComprobanteProveedorUnicidadSupport::findDuplicadoPrecarga(
                $empresaId,
                $tipoId,
                'A',
                0,
                $nro,
                $cuit,
            );
            if ($dup === null) {
                return $nro;
            }
        }

        throw new RuntimeException('No se pudo generar un número provisorio único para la precarga del legajo.');
    }
}
