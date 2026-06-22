<?php

namespace App\Services\Compras;

use App\Support\Compras\PrecargaProveedor\FacturaPdfIa\FacturaProveedorNombreArchivoParserSupport;
use App\Support\Stock\RecepcionProveedorOcr\RecepcionProveedorOcrTextoExtractor;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Indexa PDFs de facturas (Facturas_scan, precargas API, scan legacy Anita) para few-shot en Ollama.
 */
final class FacturaProveedorCorpusAprendizajeService
{
    public function __construct(
        private RecepcionProveedorOcrTextoExtractor $textoExtractor,
        private FacturaProveedorNombreArchivoParserSupport $nombreArchivoParser,
    ) {}

    /**
     * @param  array{
     *   incluir_ocr?: bool,
     *   fuente?: string,
     *   limite?: int,
     *   scan_desde?: ?int,
     *   incremental?: bool,
     *   solo_con_precarga?: bool,
     * }  $opciones
     * @return array{proveedores: int, muestras: int, nuevas: int, ruta_cache: string, cursor_scan: ?int}
     */
    public function reconstruirCorpus(array $opciones = []): array
    {
        $incluirOcr = (bool) ($opciones['incluir_ocr'] ?? true);
        $fuente = (string) ($opciones['fuente'] ?? 'todo');
        $limite = max(1, (int) ($opciones['limite'] ?? 500));
        $incremental = (bool) ($opciones['incremental'] ?? true);
        $soloConPrecarga = (bool) ($opciones['solo_con_precarga'] ?? false);

        $corpus = $incremental ? ($this->leerCorpus() ?? $this->corpusVacio()) : $this->corpusVacio();
        $indices = $this->indiceMuestrasExistentes($corpus);
        $precargasPorRuta = $this->indicePrecargasPorRuta();
        $precargasPorDocu = $this->indicePrecargasPorDocuId($precargasPorRuta);

        $candidatos = [];
        if (in_array($fuente, ['todo', 'precargas', 'facturas'], true)) {
            $candidatos = array_merge($candidatos, $this->candidatosFacturasScan($precargasPorRuta));
        }
        if (in_array($fuente, ['todo', 'precargas'], true)) {
            $candidatos = array_merge($candidatos, $this->candidatosDesdePrecargas($precargasPorRuta));
        }
        if (in_array($fuente, ['todo', 'scan'], true)) {
            $scanDesde = $opciones['scan_desde'] ?? null;
            $candidatos = array_merge(
                $candidatos,
                $this->candidatosScanLegacyDesc($scanDesde, $limite, $precargasPorDocu, $soloConPrecarga)
            );
        }

        $candidatos = $this->deduplicarCandidatos($candidatos);
        if ($fuente === 'scan') {
            $candidatos = array_slice($candidatos, 0, $limite);
        } elseif ($limite < count($candidatos)) {
            usort($candidatos, fn ($a, $b) => ($b['prioridad'] ?? 0) <=> ($a['prioridad'] ?? 0));
            $candidatos = array_slice($candidatos, 0, $limite);
        }

        $nuevas = 0;
        $ultimoDocu = null;
        $maxPorCuit = (int) config('comprobante_proveedor_pdf_ia.corpus.max_muestras_por_cuit', 5);

        foreach ($candidatos as $item) {
            $clave = $item['clave'] ?? $item['absoluta'];
            if (isset($indices[$clave])) {
                continue;
            }

            $metaArchivo = $this->nombreArchivoParser->parsear($item['nombre'], $item['relativa'] ?? null);
            $precarga = $item['precarga'] ?? null;
            $cuit = $this->resolverCuitMuestra($metaArchivo, $precarga, $item);

            if ($soloConPrecarga && ($precarga === null || ($precarga['lineas'] ?? []) === [])) {
                continue;
            }

            if ($this->conteoMuestrasCuit($corpus, $cuit) >= $maxPorCuit) {
                continue;
            }

            $muestra = $this->armarMuestra($item, $metaArchivo, $precarga, $incluirOcr);
            $corpus['proveedores'][$cuit]['cuit'] = $cuit;
            $corpus['proveedores'][$cuit]['muestras'][] = $muestra;
            $indices[$clave] = true;
            $nuevas++;

            if (isset($item['docu_id'])) {
                $ultimoDocu = (int) $item['docu_id'];
            }
        }

        $corpus['generado_en'] = now()->toIso8601String();
        $corpus['fuentes'] = array_values(array_unique(array_merge(
            $corpus['fuentes'] ?? [],
            [$fuente]
        )));
        if ($ultimoDocu !== null) {
            $corpus['cursor_scan_legacy'] = $ultimoDocu - 1;
        }

        $muestras = $this->contarMuestras($corpus);
        $rutaCache = $this->rutaCorpus();
        File::ensureDirectoryExists(dirname($rutaCache));
        file_put_contents($rutaCache, json_encode($corpus, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        Log::channel($this->logChannel())->info('pdf_ia.corpus_reconstruido', [
            'proveedores' => count($corpus['proveedores']),
            'muestras' => $muestras,
            'nuevas' => $nuevas,
            'fuente' => $fuente,
        ]);

        return [
            'proveedores' => count($corpus['proveedores']),
            'muestras' => $muestras,
            'nuevas' => $nuevas,
            'ruta_cache' => $rutaCache,
            'cursor_scan' => $corpus['cursor_scan_legacy'] ?? null,
        ];
    }

    /**
     * @return ?array<string, mixed>
     */
    public function leerCorpus(): ?array
    {
        $ruta = $this->rutaCorpus();
        if (! is_readable($ruta)) {
            return null;
        }

        $data = json_decode((string) file_get_contents($ruta), true);

        return is_array($data) ? $data : null;
    }

    /**
     * Ejemplos de un proveedor para el prompt Ollama (máx. N, prioriza con líneas de precarga).
     *
     * @return list<array<string, mixed>>
     */
    public function ejemplosParaCuit(?string $cuit, int $max = 2): array
    {
        $corpus = $this->leerCorpus();
        if ($corpus === null || $cuit === null || $cuit === '') {
            return [];
        }

        $cuit = $this->normalizarCuit($cuit);
        $bucket = $corpus['proveedores'][$cuit]['muestras'] ?? [];

        usort($bucket, function ($a, $b) {
            $pa = ! empty($a['lineas_concepto']) ? 1 : 0;
            $pb = ! empty($b['lineas_concepto']) ? 1 : 0;
            if ($pa !== $pb) {
                return $pb <=> $pa;
            }

            return ($b['precarga_id'] ?? 0) <=> ($a['precarga_id'] ?? 0);
        });

        return array_slice($bucket, 0, $max);
    }

    /** @return array<string, mixed> */
    private function corpusVacio(): array
    {
        $base = rtrim((string) config('precarga_comprobante.facturas_scan_base', '/Facturas_scan'), '/');

        return [
            'generado_en' => now()->toIso8601String(),
            'base_scan' => $base.'/comprobantes',
            'scan_legacy' => (string) config('comprobante_proveedor_pdf_ia.corpus.scan_legacy_dir', '/scan/compras/documentos'),
            'proveedores' => [],
            'fuentes' => [],
        ];
    }

    /**
     * @param  array<string, array<string, mixed>>  $precargasPorRuta
     * @return list<array<string, mixed>>
     */
    private function candidatosFacturasScan(array $precargasPorRuta): array
    {
        $base = rtrim((string) config('precarga_comprobante.facturas_scan_base', '/Facturas_scan'), '/');
        $comprobantesDir = $base.'/comprobantes';
        if (! is_dir($comprobantesDir)) {
            return [];
        }

        $salida = [];
        foreach ($this->iterarPdfsDirectorio($comprobantesDir) as $item) {
            $rutaStorage = 'storage:/comprobantes/'.$item['relativa'];
            $precarga = $precargasPorRuta[$rutaStorage] ?? $precargasPorRuta[$item['relativa']] ?? null;
            $item['fuente'] = 'facturas_scan';
            $item['clave'] = 'facturas:'.$item['relativa'];
            $item['prioridad'] = $precarga ? 100 : 10;
            $item['precarga'] = $precarga;
            $salida[] = $item;
        }

        return $salida;
    }

    /**
     * PDFs referenciados en precarga que existan en disco (Facturas_scan o scan legacy).
     *
     * @param  array<string, array<string, mixed>>  $precargasPorRuta
     * @return list<array<string, mixed>>
     */
    private function candidatosDesdePrecargas(array $precargasPorRuta): array
    {
        $salida = [];
        foreach ($precargasPorRuta as $ruta => $precarga) {
            $absoluta = $this->resolverRutaAbsolutaPrecarga($ruta);
            if ($absoluta === null || ! is_readable($absoluta)) {
                continue;
            }

            $nombre = basename($absoluta);
            $salida[] = [
                'nombre' => $nombre,
                'relativa' => $ruta,
                'absoluta' => $absoluta,
                'fuente' => 'precarga',
                'clave' => 'precarga:'.($precarga['id'] ?? $ruta),
                'prioridad' => 200,
                'precarga' => $precarga,
                'docu_id' => $this->extraerDocuId($ruta),
            ];
        }

        return $salida;
    }

    /**
     * Scan Anita legacy: docu_XXXXXXXX.pdf de atrás hacia adelante (sin listar 300k archivos).
     *
     * @param  array<int, array<string, mixed>>  $precargasPorDocu
     * @return list<array<string, mixed>>
     */
    private function candidatosScanLegacyDesc(
        ?int $desde,
        int $limite,
        array $precargasPorDocu,
        bool $soloConPrecarga
    ): array {
        $dir = rtrim((string) config('comprobante_proveedor_pdf_ia.corpus.scan_legacy_dir', '/scan/compras/documentos'), '/');
        if (! is_dir($dir)) {
            return [];
        }

        $corpus = $this->leerCorpus();
        $cursor = $desde ?? ($corpus['cursor_scan_legacy'] ?? null) ?? (int) config('comprobante_proveedor_pdf_ia.corpus.scan_legacy_max_docu', 362500);

        $salida = [];
        $intentos = 0;
        $maxIntentos = $limite * 20;

        for ($id = $cursor; $id > 0 && count($salida) < $limite && $intentos < $maxIntentos; $id--, $intentos++) {
            $nombre = sprintf('docu_%010d.pdf', $id);
            $absoluta = $dir.'/'.$nombre;
            if (! is_file($absoluta)) {
                continue;
            }

            $precarga = $precargasPorDocu[$id] ?? null;
            if ($soloConPrecarga && $precarga === null) {
                continue;
            }

            $salida[] = [
                'nombre' => $nombre,
                'relativa' => $nombre,
                'absoluta' => $absoluta,
                'fuente' => 'scan_legacy',
                'clave' => 'scan:'.$nombre,
                'prioridad' => $precarga ? 150 : 5,
                'precarga' => $precarga,
                'docu_id' => $id,
            ];
        }

        return $salida;
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array<string, mixed>  $metaArchivo
     * @param  ?array<string, mixed>  $precarga
     */
    private function armarMuestra(array $item, array $metaArchivo, ?array $precarga, bool $incluirOcr): array
    {
        $muestra = [
            'archivo' => $item['nombre'],
            'ruta_relativa' => $item['relativa'] ?? $item['nombre'],
            'fuente' => $item['fuente'] ?? 'desconocida',
            'periodo' => $metaArchivo['periodo'],
            'tipo_archivo_nombre' => $metaArchivo['tipo_archivo'],
            'letra' => $metaArchivo['letra'],
            'sucursal' => $metaArchivo['sucursal'],
            'numero_factura' => $metaArchivo['numero_factura'],
            'docu_id' => $item['docu_id'] ?? $this->extraerDocuId($item['nombre']),
            'precarga_id' => $precarga['id'] ?? null,
            'numero_oc' => $precarga['numero_oc'] ?? null,
            'tipo_resuelto_api' => $precarga['tipo_abreviatura'] ?? null,
            'total' => $precarga['total'] ?? null,
            'lineas_concepto' => $precarga['lineas'] ?? [],
        ];

        if ($incluirOcr) {
            try {
                $texto = $this->textoExtractor->extraer($item['absoluta'], 'application/pdf');
                $muestra['ocr_chars'] = mb_strlen($texto);
                $muestra['ocr_pie'] = $this->recortarPieFactura($texto);
                $muestra['ocr_cabecera'] = mb_substr(preg_replace('/\s+/', ' ', $texto) ?? '', 0, 600);
                if (empty($metaArchivo['cuit_proveedor'])) {
                    $cuitOcr = $this->extraerCuitDesdeTexto($texto);
                    if ($cuitOcr) {
                        $muestra['cuit_ocr'] = $cuitOcr;
                    }
                }
            } catch (\Throwable $e) {
                $muestra['ocr_error'] = $e->getMessage();
            }
        }

        return $muestra;
    }

    /**
     * @param  array<string, mixed>  $metaArchivo
     * @param  ?array<string, mixed>  $precarga
     */
    private function resolverCuitMuestra(array $metaArchivo, ?array $precarga, array $item): string
    {
        if (! empty($metaArchivo['cuit_proveedor'])) {
            return $this->normalizarCuit((string) $metaArchivo['cuit_proveedor']);
        }

        if (! empty($precarga['cuit_proveedor'])) {
            return $this->normalizarCuit((string) $precarga['cuit_proveedor']);
        }

        return 'docu:'.($item['docu_id'] ?? $this->extraerDocuId($item['nombre']) ?? 'sin-cuit');
    }

    private function resolverRutaAbsolutaPrecarga(string $ruta): ?string
    {
        $ruta = str_replace('\\', '/', trim($ruta));

        if (str_starts_with($ruta, 'storage:/comprobantes/')) {
            $rel = substr($ruta, strlen('storage:/comprobantes/'));
            $base = rtrim((string) config('precarga_comprobante.facturas_scan_base', '/Facturas_scan'), '/');

            return $base.'/comprobantes/'.$rel;
        }

        if (preg_match('/docu_(\d+)\.pdf/i', $ruta, $m)) {
            $dir = rtrim((string) config('comprobante_proveedor_pdf_ia.corpus.scan_legacy_dir', '/scan/compras/documentos'), '/');
            $abs = $dir.'/'.sprintf('docu_%010d.pdf', (int) $m[1]);

            return is_file($abs) ? $abs : null;
        }

        if (preg_match('#/Facturas_scan/(.+)$#i', $ruta, $m)) {
            $candidata = '/Facturas_scan/'.$m[1];

            return is_file($candidata) ? $candidata : null;
        }

        if (is_file($ruta)) {
            return $ruta;
        }

        return null;
    }

    private function extraerDocuId(string $texto): ?int
    {
        if (preg_match('/docu_0*(\d+)\.pdf/i', $texto, $m)) {
            return (int) $m[1];
        }

        return null;
    }

    private function extraerCuitDesdeTexto(string $texto): ?string
    {
        if (preg_match('/\b(\d{2}-\d{8}-\d)\b/', $texto, $m)) {
            return $m[1];
        }

        return null;
    }

    /** @param  array<string, mixed>  $corpus */
    private function indiceMuestrasExistentes(array $corpus): array
    {
        $indice = [];
        foreach ($corpus['proveedores'] ?? [] as $bucket) {
            foreach ($bucket['muestras'] ?? [] as $m) {
                $clave = ($m['fuente'] ?? '').':'.($m['ruta_relativa'] ?? $m['archivo'] ?? '');
                $indice[$clave] = true;
                if (! empty($m['archivo'])) {
                    $indice['scan:'.$m['archivo']] = true;
                    $indice['facturas:'.($m['ruta_relativa'] ?? '')] = true;
                }
            }
        }

        return $indice;
    }

    /** @param  array<string, mixed>  $corpus */
    private function conteoMuestrasCuit(array $corpus, string $cuit): int
    {
        return count($corpus['proveedores'][$cuit]['muestras'] ?? []);
    }

    /** @param  array<string, mixed>  $corpus */
    private function contarMuestras(array $corpus): int
    {
        $n = 0;
        foreach ($corpus['proveedores'] ?? [] as $bucket) {
            $n += count($bucket['muestras'] ?? []);
        }

        return $n;
    }

    /**
     * @param  list<array<string, mixed>>  $candidatos
     * @return list<array<string, mixed>>
     */
    private function deduplicarCandidatos(array $candidatos): array
    {
        $vistos = [];
        $out = [];
        foreach ($candidatos as $c) {
            $k = $c['clave'] ?? $c['absoluta'];
            if (isset($vistos[$k])) {
                continue;
            }
            $vistos[$k] = true;
            $out[] = $c;
        }

        return $out;
    }

    /** @return \Generator<int, array{nombre: string, relativa: string, absoluta: string}> */
    private function iterarPdfsDirectorio(string $dir): \Generator
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (! $file->isFile() || strtolower($file->getExtension()) !== 'pdf') {
                continue;
            }
            $absoluta = $file->getPathname();
            yield [
                'nombre' => $file->getFilename(),
                'relativa' => ltrim(str_replace($dir.'/', '', $absoluta), '/'),
                'absoluta' => $absoluta,
            ];
        }
    }

    /** @return array<string, array<string, mixed>> */
    private function indicePrecargasPorRuta(): array
    {
        $indice = [];

        $filas = DB::table('precarga_comprobante_proveedor as p')
            ->leftJoin('tipotransaccion_compra as tt', 'tt.id', '=', 'p.tipotransaccion_compra_id')
            ->leftJoin('proveedor as pr', 'pr.id', '=', 'p.proveedor_id')
            ->select([
                'p.id',
                'p.numeroordencompra',
                'p.rutaalmacenamiento',
                'p.total',
                'tt.abreviatura as tipo_abreviatura',
                'pr.nroinscripcion as cuit_proveedor',
            ])
            ->whereNotNull('p.rutaalmacenamiento')
            ->orderByDesc('p.id')
            ->get();

        foreach ($filas as $fila) {
            $conceptos = DB::table('precarga_comprobante_proveedor_concepto as pc')
                ->join('concepto_ivacompra as c', 'c.id', '=', 'pc.concepto_ivacompra_id')
                ->where('pc.precarga_comprobante_proveedor_id', $fila->id)
                ->select(['c.nombre', 'c.nombre_ia', 'c.codigo', 'pc.monto', 'c.tipoconcepto'])
                ->get()
                ->map(fn ($c) => [
                    'codigo' => $c->codigo,
                    'nombre' => $c->nombre,
                    'nombre_ia' => $c->nombre_ia,
                    'tipoconcepto' => $c->tipoconcepto,
                    'monto' => (float) $c->monto,
                ])
                ->all();

            $entrada = [
                'id' => (int) $fila->id,
                'numero_oc' => $fila->numeroordencompra,
                'tipo_abreviatura' => $fila->tipo_abreviatura,
                'total' => (float) $fila->total,
                'lineas' => $conceptos,
                'cuit_proveedor' => $fila->cuit_proveedor,
            ];

            $ruta = str_replace('\\', '/', (string) $fila->rutaalmacenamiento);
            $indice[$ruta] = $entrada;
            if (str_starts_with($ruta, 'storage:/comprobantes/')) {
                $indice[substr($ruta, strlen('storage:/comprobantes/'))] = $entrada;
            }
            if (preg_match('#/comprobantes/(.+)$#i', $ruta, $m)) {
                $indice[$m[1]] = $entrada;
            }
        }

        return $indice;
    }

    /**
     * @param  array<string, array<string, mixed>>  $precargasPorRuta
     * @return array<int, array<string, mixed>>
     */
    private function indicePrecargasPorDocuId(array $precargasPorRuta): array
    {
        $porDocu = [];
        foreach ($precargasPorRuta as $ruta => $entrada) {
            $id = $this->extraerDocuId($ruta);
            if ($id !== null) {
                $porDocu[$id] = $entrada;
            }
        }

        return $porDocu;
    }

    private function recortarPieFactura(string $texto): string
    {
        $lineas = preg_split('/\R/u', $texto) ?: [];
        $pie = array_slice($lineas, max(0, count($lineas) - 35));

        return mb_substr(implode("\n", $pie), 0, 2500);
    }

    private function normalizarCuit(string $cuit): string
    {
        $d = preg_replace('/\D/', '', $cuit) ?? '';
        if (strlen($d) !== 11) {
            return $cuit;
        }

        return substr($d, 0, 2).'-'.substr($d, 2, 8).'-'.substr($d, 10, 1);
    }

    private function rutaCorpus(): string
    {
        return Storage::disk('local')->path(
            (string) config('comprobante_proveedor_pdf_ia.corpus.cache_path', 'compras/factura_pdf_ia/corpus.json')
        );
    }

    private function logChannel(): string
    {
        return (string) config('comprobante_proveedor_pdf_ia.log_channel', 'precarga_proveedor_api');
    }
}
