<?php

namespace App\Support\Compras;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class PrecargaFacturaScanPathResolver
{
    private const SUBDIR_COMPROBANTES = 'comprobantes';

    public function basePath(): string
    {
        return $this->normalizeSlashes((string) config('precarga_comprobante.facturas_scan_base', ''));
    }

    public function comprobantesBasePath(): string
    {
        $base = rtrim($this->basePath(), '/');
        if ($base === '') {
            return '';
        }

        return $base.'/'.self::SUBDIR_COMPROBANTES;
    }

    /**
     * Resuelve la ruta absoluta del PDF escaneado bajo {montaje}/comprobantes o null.
     */
    public function resolve(?string $rutaAlmacenamiento): ?string
    {
        $rutaAlmacenamiento = trim((string) $rutaAlmacenamiento);
        if ($rutaAlmacenamiento === '' || str_contains($rutaAlmacenamiento, '..')) {
            return null;
        }

        $comprobantesBase = $this->comprobantesBasePath();
        if ($comprobantesBase === '' || ! is_dir($comprobantesBase)) {
            return null;
        }

        $relative = $this->relativeUnderComprobantes($rutaAlmacenamiento);
        if ($relative !== null) {
            $candidate = $this->normalizeSlashes($comprobantesBase.'/'.$relative);
            if ($this->isValidPdfAt($candidate, $comprobantesBase)) {
                return $candidate;
            }
        }

        return $this->findByBasenameInComprobantes(basename($this->normalizeSlashes($rutaAlmacenamiento)), $comprobantesBase);
    }

    public function tieneRutaRelativa(?string $rutaAlmacenamiento): bool
    {
        $rutaAlmacenamiento = trim((string) $rutaAlmacenamiento);

        return $rutaAlmacenamiento !== '' && ! str_contains($rutaAlmacenamiento, '..');
    }

    /**
     * Extrae la ruta relativa dentro de comprobantes (CUIT/subcarpeta/archivo.pdf).
     */
    private function relativeUnderComprobantes(string $rutaAlmacenamiento): ?string
    {
        $ruta = $this->normalizeSlashes($rutaAlmacenamiento);

        if (preg_match('#comprobantes/(.+)$#i', $ruta, $m)) {
            return $m[1];
        }

        if (preg_match('#^storage:/facturas/(.+)$#i', $ruta, $m)) {
            return $m[1];
        }

        if (preg_match('#(?:^|/)facturas/(.+\.pdf)$#i', $ruta, $m)) {
            return $m[1];
        }

        $mountBase = rtrim($this->basePath(), '/');
        if ($mountBase !== '' && preg_match('#^'.preg_quote($mountBase, '#').'/'.preg_quote(self::SUBDIR_COMPROBANTES, '#').'/(.+)$#i', $ruta, $m)) {
            return $m[1];
        }

        return null;
    }

    private function findByBasenameInComprobantes(string $basename, string $comprobantesBase): ?string
    {
        if ($basename === '' || ! $this->isPdfFile($basename)) {
            return null;
        }

        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($comprobantesBase, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::LEAVES_ONLY
            );

            foreach ($iterator as $file) {
                if (! $file->isFile() || strcasecmp($file->getFilename(), $basename) !== 0) {
                    continue;
                }

                $path = $file->getPathname();
                if ($this->isValidPdfAt($path, $comprobantesBase)) {
                    return $path;
                }
            }
        } catch (\Throwable) {
            return null;
        }

        return null;
    }

    private function isValidPdfAt(string $path, string $comprobantesBase): bool
    {
        return $this->isUnderBase($path, $comprobantesBase)
            && is_file($path)
            && is_readable($path)
            && $this->isPdfFile($path);
    }

    private function normalizeSlashes(string $path): string
    {
        $path = str_replace('\\', '/', $path);

        return preg_replace('#/+#', '/', $path) ?? $path;
    }

    private function isUnderBase(string $path, string $base): bool
    {
        $pathNorm = strtolower(rtrim($this->normalizeSlashes($path), '/'));
        $baseNorm = strtolower(rtrim($this->normalizeSlashes($base), '/'));

        if ($baseNorm === '') {
            return false;
        }

        return $pathNorm === $baseNorm || str_starts_with($pathNorm, $baseNorm.'/');
    }

    private function isPdfFile(string $path): bool
    {
        return str_ends_with(strtolower($path), '.pdf');
    }
}
