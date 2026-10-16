<?php

declare(strict_types=1);

namespace App\Services\Compras;

use App\Models\Compras\Comprobante_Proveedor;
use App\Services\Compras\Tracking\TrackingPdfResolverService;
use App\Support\Compras\ComprobantesPdfLoteFiltros;
use App\Support\Compras\Tracking\TrackingPdfReferencia;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use RuntimeException;
use ZipArchive;

class ComprobantesPdfLoteReporteService
{
    public function __construct(
        private readonly TrackingPdfResolverService $pdfResolver,
    ) {}

    /**
     * @param  array<string, mixed>  $filtros
     */
    public function paginar(array $filtros, int $perPage, int $page): LengthAwarePaginator
    {
        return $this->query($filtros)
            ->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<int, TrackingPdfReferencia>
     */
    public function resolverPdfs(iterable $comprobantes): array
    {
        return $this->pdfResolver->resolverLote($comprobantes);
    }

    /**
     * Arma un ZIP con los PDF encontrados (ERP, precarga IA, Facturas_scan y escaneo Anita).
     *
     * @param  array<string, mixed>  $filtros
     * @return array{ruta: string, incluidos: int, faltantes: int}
     */
    public function armarZip(array $filtros): array
    {
        if (! ComprobantesPdfLoteFiltros::tieneCriteriosAplicados($filtros)) {
            throw new RuntimeException('Faltan empresa, proveedores o fechas para armar el ZIP.');
        }

        $comprobantes = $this->query($filtros)->get();
        $referencias = $this->pdfResolver->resolverLote($comprobantes);

        $dir = storage_path('app/tmp');
        if (! is_dir($dir) && ! mkdir($dir, 0775, true) && ! is_dir($dir)) {
            throw new RuntimeException('No se pudo crear el directorio temporal del ZIP.');
        }

        $ruta = $dir.'/comprobantes_pdf_'.date('Ymd_His').'_'.uniqid('', true).'.zip';
        $zip = new ZipArchive;
        if ($zip->open($ruta, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('No se pudo crear el archivo ZIP.');
        }

        $usados = [];
        $incluidos = 0;
        $faltantes = [];

        foreach ($comprobantes as $comprobante) {
            $ref = $referencias[(int) $comprobante->id] ?? null;
            $path = $ref?->ruta;
            if ($path === null || ! is_readable($path)) {
                $faltantes[] = $this->lineaListado($comprobante).' — sin PDF';

                continue;
            }

            $nombre = $this->nombrePdf($comprobante, $usados);
            $zip->addFile($path, $nombre);
            $zip->setCompressionName($nombre, ZipArchive::CM_STORE);
            $incluidos++;
        }

        if ($faltantes !== []) {
            $zip->addFromString(
                'sin_pdf.txt',
                "Comprobantes del lote sin PDF en el ERP, Facturas_scan ni el escaneo histórico.\n\n"
                .implode("\n", $faltantes)."\n"
            );
        }

        if ($incluidos === 0 && $faltantes === []) {
            $zip->addFromString('vacio.txt', "No hay comprobantes para los filtros indicados.\n");
        }

        $zip->close();

        return [
            'ruta' => $ruta,
            'incluidos' => $incluidos,
            'faltantes' => count($faltantes),
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return Builder<Comprobante_Proveedor>
     */
    private function query(array $filtros): Builder
    {
        return Comprobante_Proveedor::query()
            ->with([
                'proveedores',
                'tipotransaccion_compras',
                'empresas',
            ])
            ->where('empresa_id', (int) $filtros['empresa_id'])
            ->whereIn('proveedor_id', $filtros['proveedor_ids'])
            ->whereDate('fechacomprobante', '>=', (string) $filtros['fecha_desde'])
            ->whereDate('fechacomprobante', '<=', (string) $filtros['fecha_hasta'])
            ->orderBy('fechacomprobante')
            ->orderBy('proveedor_id')
            ->orderBy('numerocomprobante')
            ->orderBy('id');
    }

    /**
     * @param  array<string, true>  $usados
     */
    private function nombrePdf(Comprobante_Proveedor $comprobante, array &$usados): string
    {
        $comprobante->loadMissing(['proveedores', 'tipotransaccion_compras']);
        $codigo = $this->slug((string) ($comprobante->proveedores->codigo ?? 'prov'));
        $tipo = $this->slug((string) ($comprobante->tipotransaccion_compras->abreviatura
            ?? $comprobante->tipotransaccion_compras->nombre
            ?? 'comp'));
        $letra = $this->slug((string) ($comprobante->letra ?? ''));
        $suc = str_pad((string) ((int) ($comprobante->sucursal ?? 0)), 4, '0', STR_PAD_LEFT);
        $nro = str_pad((string) ((int) ($comprobante->numerocomprobante ?? 0)), 8, '0', STR_PAD_LEFT);
        $base = $codigo.'_'.$tipo.($letra !== '' ? '_'.$letra : '').'_'.$suc.'-'.$nro;
        $nombre = $base.'.pdf';
        if (isset($usados[$nombre])) {
            $nombre = $base.'_'.$comprobante->id.'.pdf';
        }
        $usados[$nombre] = true;

        return $nombre;
    }

    private function lineaListado(Comprobante_Proveedor $comprobante): string
    {
        $comprobante->loadMissing(['proveedores', 'tipotransaccion_compras']);
        $fecha = $comprobante->fechacomprobante?->format('d/m/Y') ?? '';
        $prov = trim((string) ($comprobante->proveedores->codigo ?? '').' '.(string) ($comprobante->proveedores->nombre ?? ''));
        $tipo = (string) ($comprobante->tipotransaccion_compras->abreviatura ?? '');
        $nro = trim((string) ($comprobante->letra ?? '').' '
            .str_pad((string) ((int) ($comprobante->sucursal ?? 0)), 4, '0', STR_PAD_LEFT).'-'
            .str_pad((string) ((int) ($comprobante->numerocomprobante ?? 0)), 8, '0', STR_PAD_LEFT));

        return trim($fecha.' '.$prov.' '.$tipo.' '.$nro);
    }

    private function slug(string $texto): string
    {
        $texto = preg_replace('/[^A-Za-z0-9_-]+/', '-', $texto) ?? '';
        $texto = trim($texto, '-');

        return $texto !== '' ? $texto : 'x';
    }
}
