<?php

namespace App\Support\Compras;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class PrecargaFacturaScanPathResolver
{
    private const SUBDIR_COMPROBANTES = 'comprobantes';

    private const SUBDIR_COMPROBANTES_REVISAR = 'comprobantes-revisar';

    private ?string $basePathOverride = null;

    public function withBasePath(string $basePath): self
    {
        $copy = clone $this;
        $copy->basePathOverride = $basePath;

        return $copy;
    }

    public function basePath(): string
    {
        if ($this->basePathOverride !== null) {
            return $this->normalizeSlashes($this->basePathOverride);
        }

        return $this->normalizeSlashes((string) config('precarga_comprobante.facturas_scan_base', ''));
    }

    public function comprobantesBasePath(): string
    {
        return $this->subdirBasePath(self::SUBDIR_COMPROBANTES);
    }

    public function comprobantesRevisarBasePath(): string
    {
        return $this->subdirBasePath(self::SUBDIR_COMPROBANTES_REVISAR);
    }

    /**
     * Resuelve la ruta absoluta del PDF escaneado bajo {montaje}/comprobantes
     * o {montaje}/comprobantes-revisar, o null.
     */
    public function resolve(?string $rutaAlmacenamiento): ?string
    {
        $rutaAlmacenamiento = trim((string) $rutaAlmacenamiento);
        if ($rutaAlmacenamiento === '' || str_contains($rutaAlmacenamiento, '..')) {
            return null;
        }

        $allowedBases = $this->allowedBases();
        if ($allowedBases === []) {
            return null;
        }

        foreach ($this->candidateAbsolutePaths($rutaAlmacenamiento) as $candidate) {
            foreach ($allowedBases as $base) {
                if ($this->isValidPdfAt($candidate, $base)) {
                    return $candidate;
                }
            }
        }

        $basename = basename($this->normalizeSlashes($rutaAlmacenamiento));
        foreach ($allowedBases as $base) {
            $found = $this->findByBasenameInBase($basename, $base);
            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }

    public function tieneRutaRelativa(?string $rutaAlmacenamiento): bool
    {
        $rutaAlmacenamiento = trim((string) $rutaAlmacenamiento);

        return $rutaAlmacenamiento !== '' && ! str_contains($rutaAlmacenamiento, '..');
    }

    private function subdirBasePath(string $subdir): string
    {
        $base = rtrim($this->basePath(), '/');
        if ($base === '') {
            return '';
        }

        return $base.'/'.$subdir;
    }

    /**
     * @return list<string>
     */
    private function allowedBases(): array
    {
        $bases = [];
        foreach ([self::SUBDIR_COMPROBANTES, self::SUBDIR_COMPROBANTES_REVISAR] as $subdir) {
            $path = $this->subdirBasePath($subdir);
            if ($path !== '' && is_dir($path)) {
                $bases[] = $path;
            }
        }

        return $bases;
    }

    /**
     * @return list<string>
     */
    private function candidateAbsolutePaths(string $rutaAlmacenamiento): array
    {
        $ruta = $this->normalizeSlashes($rutaAlmacenamiento);
        $candidates = [];

        foreach ([self::SUBDIR_COMPROBANTES_REVISAR, self::SUBDIR_COMPROBANTES] as $subdir) {
            $relative = $this->relativeUnderSubdir($ruta, $subdir);
            if ($relative === null) {
                continue;
            }

            $base = $this->subdirBasePath($subdir);
            if ($base !== '') {
                $candidates[] = $this->normalizeSlashes($base.'/'.$relative);
            }
        }

        $legacy = $this->legacyRelativeUnderComprobantes($ruta);
        if ($legacy !== null) {
            $base = $this->comprobantesBasePath();
            if ($base !== '') {
                $candidates[] = $this->normalizeSlashes($base.'/'.$legacy);
            }
        }

        return array_values(array_unique($candidates));
    }

    /**
     * Extrae la ruta relativa dentro de un subdirectorio del montaje.
     */
    private function relativeUnderSubdir(string $ruta, string $subdir): ?string
    {
        if (preg_match('#(?:^|/)'.preg_quote($subdir, '#').'/(.+)$#i', $ruta, $m)) {
            return $m[1];
        }

        $mountBase = rtrim($this->basePath(), '/');
        if ($mountBase !== '' && preg_match(
            '#^'.preg_quote($mountBase, '#').'/'.preg_quote($subdir, '#').'/(.+)$#i',
            $ruta,
            $m
        )) {
            return $m[1];
        }

        return null;
    }

    /**
     * Alias históricos que se normalizan a comprobantes/.
     */
    private function legacyRelativeUnderComprobantes(string $ruta): ?string
    {
        if (preg_match('#^storage:/facturas/(.+)$#i', $ruta, $m)) {
            return $m[1];
        }

        if (preg_match('#(?:^|/)facturas/(.+\.pdf)$#i', $ruta, $m)) {
            return $m[1];
        }

        return null;
    }

    private function findByBasenameInBase(string $basename, string $base): ?string
    {
        if ($basename === '' || ! $this->isPdfFile($basename)) {
            return null;
        }

        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::LEAVES_ONLY
            );

            foreach ($iterator as $file) {
                if (! $file->isFile() || strcasecmp($file->getFilename(), $basename) !== 0) {
                    continue;
                }

                $path = $file->getPathname();
                if ($this->isValidPdfAt($path, $base)) {
                    return $path;
                }
            }
        } catch (\Throwable) {
            return null;
        }

        return null;
    }

    private function isValidPdfAt(string $path, string $base): bool
    {
        return $this->isUnderBase($path, $base)
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
