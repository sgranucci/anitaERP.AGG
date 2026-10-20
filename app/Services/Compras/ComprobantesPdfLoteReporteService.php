<?php

declare(strict_types=1);

namespace App\Services\Compras;

use App\Models\Compras\Comprobante_Proveedor;
use App\Models\Compras\Ordencompra;
use App\Models\Compras\Pagoproveedor;
use App\Models\Stock\Recepcion_Proveedor;
use App\Services\Compras\Tracking\TrackingPdfResolverService;
use App\Services\Stock\RecepcionProveedorPdfService;
use App\Support\Compras\ComprobantesPdfLoteFila;
use App\Support\Compras\ComprobantesPdfLoteFiltros;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Collection;
use RuntimeException;
use Throwable;
use ZipArchive;

class ComprobantesPdfLoteReporteService
{
    public function __construct(
        private readonly TrackingPdfResolverService $pdfResolver,
        private readonly OrdencompraPdfService $ordencompraPdf,
        private readonly RecepcionProveedorPdfService $recepcionPdf,
        private readonly PagoproveedorComprobantePdfService $pagoPdf,
    ) {}

    /**
     * @param  array<string, mixed>  $filtros
     */
    public function paginar(array $filtros, int $perPage, int $page): LengthAwarePaginator
    {
        $todas = $this->recolectar($filtros);
        $items = $todas->slice(($page - 1) * $perPage, $perPage)->values();
        $this->completarPdfFacturas($items);

        return new Paginator($items, $todas->count(), $perPage, $page, [
            'path' => Paginator::resolveCurrentPath(),
        ]);
    }

    /**
     * Arma un ZIP con los PDF del lote. Si hay claves, solo esas filas (ya filtradas por empresa, proveedores y fechas).
     *
     * @param  array<string, mixed>  $filtros
     * @param  list<string>  $claves
     * @return array{ruta: string, incluidos: int, faltantes: int}
     */
    public function armarZip(array $filtros, array $claves = []): array
    {
        if (! ComprobantesPdfLoteFiltros::tieneCriteriosAplicados($filtros)) {
            throw new RuntimeException('Faltan empresa, proveedores, fechas o tipos para armar el ZIP.');
        }

        $filas = $this->recolectar($filtros);
        if ($claves !== []) {
            $permitidas = array_flip($claves);
            $filas = $filas->filter(fn (ComprobantesPdfLoteFila $fila) => isset($permitidas[$fila->clave]))->values();
        }

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

        $facIds = $filas->where('tipo', 'fac')->pluck('id')->map(fn ($id) => (int) $id)->all();
        $facturas = $facIds === []
            ? collect()
            : $this->queryFacturas($filtros)->whereIn('id', $facIds)->get()->keyBy('id');
        $referencias = $facturas->isEmpty() ? [] : $this->pdfResolver->resolverLote($facturas);

        foreach ($filas as $fila) {
            try {
                $agregado = false;
                if ($fila->tipo === 'fac') {
                    $agregado = $this->agregarFactura($zip, $fila, $facturas->get($fila->id), $referencias, $usados, $faltantes);
                } elseif ($fila->tipo === 'oc') {
                    $agregado = $this->agregarGenerado($zip, $fila, $this->ordencompraPdf->generarArchivo($fila->id), $usados);
                } elseif ($fila->tipo === 'com') {
                    $agregado = $this->agregarCom($zip, $fila, $usados);
                } elseif ($fila->tipo === 'opp' || $fila->tipo === 'opa') {
                    $agregado = $this->agregarGenerado($zip, $fila, $this->pagoPdf->generarArchivo($fila->id), $usados);
                }
            } catch (Throwable $e) {
                $faltantes[] = $this->lineaFila($fila).' — no se pudo armar el PDF';
                $agregado = false;
            }
            if ($agregado) {
                $incluidos++;
            }
        }

        if ($faltantes !== []) {
            $zip->addFromString(
                'sin_pdf.txt',
                "Documentos del lote sin PDF.\n\n".implode("\n", $faltantes)."\n"
            );
        }

        if ($incluidos === 0 && $faltantes === []) {
            $zip->addFromString('vacio.txt', "No hay documentos para los filtros indicados.\n");
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
     * @return Collection<int, ComprobantesPdfLoteFila>
     */
    private function recolectar(array $filtros): Collection
    {
        $tipos = array_flip($filtros['tipos'] ?? []);
        $filas = collect();

        if (isset($tipos['fac'])) {
            foreach ($this->queryFacturas($filtros)->get() as $comprobante) {
                $filas->push($this->filaFactura($comprobante));
            }
        }
        if (isset($tipos['oc'])) {
            foreach ($this->queryOrdenes($filtros)->get() as $orden) {
                $filas->push($this->filaOrden($orden));
            }
        }
        if (isset($tipos['com'])) {
            foreach ($this->queryRecepciones($filtros)->get() as $recepcion) {
                $filas->push($this->filaRecepcion($recepcion));
            }
        }
        if (isset($tipos['opp']) || isset($tipos['opa'])) {
            $tiposPago = array_values(array_intersect(['opp', 'opa'], array_keys($tipos)));
            foreach ($this->queryPagos($filtros, $tiposPago)->get() as $pago) {
                $filas->push($this->filaPago($pago));
            }
        }

        return $filas->sortBy([
            ['fechaIso', 'asc'],
            ['proveedor', 'asc'],
            ['tipo', 'asc'],
            ['numero', 'asc'],
            ['id', 'asc'],
        ])->values();
    }

    /**
     * @param  Collection<int, ComprobantesPdfLoteFila>  $filas
     */
    private function completarPdfFacturas(Collection $filas): void
    {
        $ids = $filas->where('tipo', 'fac')->pluck('id')->map(fn ($id) => (int) $id)->all();
        if ($ids === []) {
            return;
        }

        $models = Comprobante_Proveedor::query()->whereIn('id', $ids)->get();
        $refs = $this->pdfResolver->resolverLote($models);
        foreach ($filas as $fila) {
            if ($fila->tipo !== 'fac') {
                continue;
            }
            $ref = $refs[$fila->id] ?? null;
            $fila->tienePdf = $ref !== null && filled($ref->ruta);
            $fila->pdfEtiqueta = $fila->tienePdf ? $ref->etiqueta() : 'Sin PDF';
        }
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return Builder<Comprobante_Proveedor>
     */
    private function queryFacturas(array $filtros): Builder
    {
        return Comprobante_Proveedor::query()
            ->with(['proveedores', 'tipotransaccion_compras', 'empresas'])
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
     * @param  array<string, mixed>  $filtros
     * @return Builder<Ordencompra>
     */
    private function queryOrdenes(array $filtros): Builder
    {
        return Ordencompra::query()
            ->with(['proveedores'])
            ->select('ordencompra.*')
            ->selectSub(function ($q): void {
                $q->from('ordencompra_articulo')
                    ->selectRaw('COALESCE(SUM(cantidad * precio), 0)')
                    ->whereColumn('ordencompra_articulo.ordencompra_id', 'ordencompra.id');
            }, 'total_lista')
            ->where('empresa_id', (int) $filtros['empresa_id'])
            ->whereIn('proveedor_id', $filtros['proveedor_ids'])
            ->whereDate('fecha', '>=', (string) $filtros['fecha_desde'])
            ->whereDate('fecha', '<=', (string) $filtros['fecha_hasta'])
            ->orderBy('fecha')
            ->orderBy('id');
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return Builder<Recepcion_Proveedor>
     */
    private function queryRecepciones(array $filtros): Builder
    {
        return Recepcion_Proveedor::query()
            ->with(['proveedores'])
            ->select('recepcion_proveedor.*')
            ->selectSub(function ($q): void {
                $q->from('recepcion_proveedor_articulo')
                    ->selectRaw('COALESCE(SUM(cantidad * precio), 0)')
                    ->whereColumn('recepcion_proveedor_articulo.recepcion_proveedor_id', 'recepcion_proveedor.id');
            }, 'total_lista')
            ->where('empresa_id', (int) $filtros['empresa_id'])
            ->whereIn('proveedor_id', $filtros['proveedor_ids'])
            ->where('tipo', Recepcion_Proveedor::TIPO_RECEPCION)
            ->whereDate('fecha', '>=', (string) $filtros['fecha_desde'])
            ->whereDate('fecha', '<=', (string) $filtros['fecha_hasta'])
            ->orderBy('fecha')
            ->orderBy('id');
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @param  list<string>  $tiposPago
     * @return Builder<Pagoproveedor>
     */
    private function queryPagos(array $filtros, array $tiposPago): Builder
    {
        $abrev = array_map('strtoupper', $tiposPago);

        return Pagoproveedor::query()
            ->with(['proveedores'])
            ->where('empresa_id', (int) $filtros['empresa_id'])
            ->whereIn('proveedor_id', $filtros['proveedor_ids'])
            ->whereIn('tipocomprobante', $abrev)
            ->whereDate('fecha', '>=', (string) $filtros['fecha_desde'])
            ->whereDate('fecha', '<=', (string) $filtros['fecha_hasta'])
            ->orderBy('fecha')
            ->orderBy('id');
    }

    private function filaFactura(Comprobante_Proveedor $comprobante): ComprobantesPdfLoteFila
    {
        [$codigo, $proveedor] = $this->proveedorTexto($comprobante);
        [$fecha, $iso] = $this->fechaTexto($comprobante->fechacomprobante);
        $suc = str_pad((string) ((int) ($comprobante->sucursal ?? 0)), 4, '0', STR_PAD_LEFT);
        $nro = str_pad((string) ((int) ($comprobante->numerocomprobante ?? 0)), 8, '0', STR_PAD_LEFT);
        $tipo = (string) ($comprobante->tipotransaccion_compras->abreviatura
            ?? $comprobante->tipotransaccion_compras->nombre
            ?? 'FAC');

        return new ComprobantesPdfLoteFila(
            clave: 'fac:'.$comprobante->id,
            tipo: 'fac',
            id: (int) $comprobante->id,
            fecha: $fecha,
            fechaIso: $iso,
            proveedorCodigo: $codigo,
            proveedor: $proveedor,
            tipoEtiqueta: $tipo,
            numero: trim((string) ($comprobante->letra ?? '').' '.$suc.'-'.$nro),
            total: (float) ($comprobante->total ?? 0),
            tienePdf: false,
            pdfEtiqueta: 'Sin PDF',
            editarUrl: route('editar_comprobante_proveedor', ['id' => $comprobante->id]),
            pdfUrl: route('comprobante_proveedor_factura_pdf', ['id' => $comprobante->id, 'inline' => 1]),
        );
    }

    private function filaOrden(Ordencompra $orden): ComprobantesPdfLoteFila
    {
        [$codigo, $proveedor] = $this->proveedorTexto($orden);
        [$fecha, $iso] = $this->fechaTexto($orden->fecha);

        return new ComprobantesPdfLoteFila(
            clave: 'oc:'.$orden->id,
            tipo: 'oc',
            id: (int) $orden->id,
            fecha: $fecha,
            fechaIso: $iso,
            proveedorCodigo: $codigo,
            proveedor: $proveedor,
            tipoEtiqueta: 'OC',
            numero: (string) ($orden->numeroordencompra ?? $orden->id),
            total: (float) ($orden->total_lista ?? 0),
            tienePdf: true,
            pdfEtiqueta: 'Se arma al descargar',
            editarUrl: route('editar_ordencompra', ['id' => $orden->id]),
            pdfUrl: route('imprimir_pdf_ordencompra', ['id' => $orden->id]),
        );
    }

    private function filaRecepcion(Recepcion_Proveedor $recepcion): ComprobantesPdfLoteFila
    {
        [$codigo, $proveedor] = $this->proveedorTexto($recepcion);
        [$fecha, $iso] = $this->fechaTexto($recepcion->fecha);

        return new ComprobantesPdfLoteFila(
            clave: 'com:'.$recepcion->id,
            tipo: 'com',
            id: (int) $recepcion->id,
            fecha: $fecha,
            fechaIso: $iso,
            proveedorCodigo: $codigo,
            proveedor: $proveedor,
            tipoEtiqueta: 'COM',
            numero: (string) ($recepcion->numerorecepcion ?? $recepcion->id),
            total: (float) ($recepcion->total_lista ?? 0),
            tienePdf: true,
            pdfEtiqueta: 'Se arma al descargar',
            editarUrl: route('editar_recepcion_proveedor', ['id' => $recepcion->id]),
            pdfUrl: route('recepcion_proveedor_com_pdf', ['id' => $recepcion->id, 'inline' => 1]),
        );
    }

    private function filaPago(Pagoproveedor $pago): ComprobantesPdfLoteFila
    {
        [$codigo, $proveedor] = $this->proveedorTexto($pago);
        [$fecha, $iso] = $this->fechaTexto($pago->fecha);
        $tipo = strtolower((string) ($pago->tipocomprobante ?? 'opp'));
        if (! isset(ComprobantesPdfLoteFiltros::TIPOS[$tipo])) {
            $tipo = 'opp';
        }

        return new ComprobantesPdfLoteFila(
            clave: $tipo.':'.$pago->id,
            tipo: $tipo,
            id: (int) $pago->id,
            fecha: $fecha,
            fechaIso: $iso,
            proveedorCodigo: $codigo,
            proveedor: $proveedor,
            tipoEtiqueta: strtoupper($tipo),
            numero: trim((string) ($pago->letra ?? '').' '
                .str_pad((string) ((int) ($pago->sucursal ?? 0)), 4, '0', STR_PAD_LEFT).'-'
                .(string) ($pago->numerotransaccion ?? '')),
            total: (float) ($pago->monto ?? 0),
            tienePdf: true,
            pdfEtiqueta: 'Se arma al descargar',
            editarUrl: route('editar_pagoproveedor', ['id' => $pago->id]),
            pdfUrl: route('imprimir_pagoproveedor', ['id' => $pago->id]),
        );
    }

    /**
     * @param  array<int, mixed>  $referencias
     * @param  array<string, true>  $usados
     * @param  list<string>  $faltantes
     */
    private function agregarFactura(ZipArchive $zip, ComprobantesPdfLoteFila $fila, mixed $comprobante, array $referencias, array &$usados, array &$faltantes): bool
    {
        if (! $comprobante instanceof Comprobante_Proveedor) {
            $faltantes[] = $this->lineaFila($fila).' — sin PDF';

            return false;
        }
        $ref = $referencias[(int) $comprobante->id] ?? null;
        $path = $ref?->ruta;
        if ($path === null || ! is_readable($path)) {
            $faltantes[] = $this->lineaFila($fila).' — sin PDF';

            return false;
        }

        $nombre = $this->nombreFactura($comprobante, $usados);
        $zip->addFile($path, $nombre);
        $zip->setCompressionName($nombre, ZipArchive::CM_STORE);

        return true;
    }

    /**
     * @param  array{ruta: string, nombre: string}  $archivo
     * @param  array<string, true>  $usados
     */
    private function agregarGenerado(ZipArchive $zip, ComprobantesPdfLoteFila $fila, array $archivo, array &$usados): bool
    {
        $path = $archivo['ruta'] ?? '';
        if ($path === '' || ! is_readable($path)) {
            throw new RuntimeException('PDF no legible');
        }
        $nombre = $this->nombreUnico(
            strtoupper($fila->tipo).'_'.$this->slug($fila->proveedorCodigo).'_'.$this->slug($fila->numero).'.pdf',
            $usados
        );
        $zip->addFile($path, $nombre);
        $zip->setCompressionName($nombre, ZipArchive::CM_STORE);

        return true;
    }

    /**
     * @param  array<string, true>  $usados
     */
    private function agregarCom(ZipArchive $zip, ComprobantesPdfLoteFila $fila, array &$usados): bool
    {
        $doc = $this->recepcionPdf->generarComPdf($fila->id);
        $nombre = $this->nombreUnico(
            'COM_'.$this->slug($fila->proveedorCodigo).'_'.$this->slug($fila->numero).'.pdf',
            $usados
        );
        $zip->addFromString($nombre, $doc['bytes']);
        $zip->setCompressionName($nombre, ZipArchive::CM_STORE);

        return true;
    }

    /**
     * @param  array<string, true>  $usados
     */
    private function nombreFactura(Comprobante_Proveedor $comprobante, array &$usados): string
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

        return $this->nombreUnico($base.'.pdf', $usados, $base.'_'.$comprobante->id.'.pdf');
    }

    /**
     * @param  array<string, true>  $usados
     */
    private function nombreUnico(string $nombre, array &$usados, ?string $alternativa = null): string
    {
        if (isset($usados[$nombre])) {
            $nombre = $alternativa ?? preg_replace('/\.pdf$/i', '', $nombre).'_'.count($usados).'.pdf';
        }
        $usados[$nombre] = true;

        return $nombre;
    }

    private function lineaFila(ComprobantesPdfLoteFila $fila): string
    {
        return trim($fila->fecha.' '.$fila->proveedor.' '.$fila->tipoEtiqueta.' '.$fila->numero);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function proveedorTexto(object $modelo): array
    {
        $codigo = (string) ($modelo->proveedores->codigo ?? '');
        $nombre = (string) ($modelo->proveedores->nombre ?? '');

        return [$codigo, trim($codigo.' '.$nombre)];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function fechaTexto(mixed $fecha): array
    {
        if ($fecha instanceof \DateTimeInterface) {
            return [$fecha->format('d/m/Y'), $fecha->format('Y-m-d')];
        }
        $texto = trim((string) $fecha);
        if ($texto === '') {
            return ['', ''];
        }
        try {
            $c = Carbon::parse($texto);

            return [$c->format('d/m/Y'), $c->format('Y-m-d')];
        } catch (Throwable) {
            return [$texto, substr($texto, 0, 10)];
        }
    }

    private function slug(string $texto): string
    {
        $texto = preg_replace('/[^A-Za-z0-9_-]+/', '-', $texto) ?? '';
        $texto = trim($texto, '-');

        return $texto !== '' ? $texto : 'x';
    }
}
