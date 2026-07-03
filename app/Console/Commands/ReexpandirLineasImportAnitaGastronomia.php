<?php

namespace App\Console\Commands;

use App\Services\Ventas\Gastronomia\GastronomiaImportAnitaReexpandirEmisionService;
use App\Support\Ventas\GastronomiaAnitaImport\GastronomiaAnitaImportCacheSupport;
use Illuminate\Console\Command;

class ReexpandirLineasImportAnitaGastronomia extends Command
{
    protected $signature = 'gastronomia:reexpandir-lineas-import-anita
                            {--empresa=1 : empresa_id}
                            {--fecha-desde= : Jornada inicial Y-m-d (obligatorio salvo --verificar-cache)}
                            {--fecha-hasta= : Jornada final Y-m-d (default = fecha-desde)}
                            {--cache-sufijo=desc40legacy : Sufijo del directorio cache local}
                            {--codigos-descuento=40 : Códigos descuento gastronomía (CSV)}
                            {--limite=0 : Máximo de facturas a procesar (0 = todas)}
                            {--dry-run : Simular sin grabar}
                            {--verificar-cache : Solo informa cobertura stkmov en cache vs candidatas}';

    protected $description = 'Reemplaza líneas ficticias import_anita (PF100026) por renglones stkmov del cache local Anita';

    public function handle(
        GastronomiaImportAnitaReexpandirEmisionService $service,
        GastronomiaAnitaImportCacheSupport $cacheSupport,
    ): int {
        $empresaId = (int) $this->option('empresa');
        $fechaDesde = trim((string) ($this->option('fecha-desde') ?? ''));
        $fechaHasta = trim((string) ($this->option('fecha-hasta') ?? ''));
        $fechaHasta = $fechaHasta !== '' ? $fechaHasta : $fechaDesde;
        $sufijoCache = trim((string) ($this->option('cache-sufijo') ?? 'desc40legacy'));
        $codigosRaw = trim((string) ($this->option('codigos-descuento') ?? '40'));
        $codigos = array_values(array_filter(array_map('trim', preg_split('/[\s,;]+/', $codigosRaw) ?: [])));
        $limite = max(0, (int) $this->option('limite'));
        $dryRun = (bool) $this->option('dry-run');
        $soloVerificar = (bool) $this->option('verificar-cache');

        if ($fechaDesde === '') {
            $this->error('Indique --fecha-desde=Y-m-d');

            return self::FAILURE;
        }

        if (! $cacheSupport->cacheCompleta($empresaId, $fechaDesde, $fechaHasta, $sufijoCache)) {
            $this->error(sprintf(
                'Cache incompleta: empresa %d %s..%s sufijo=%s. Ejecute gastronomia:precargar-cache-import-anita --forzar antes de reprocesar.',
                $empresaId,
                $fechaDesde,
                $fechaHasta,
                $sufijoCache !== '' ? $sufijoCache : '(vacío)',
            ));

            return self::FAILURE;
        }

        $manifest = $cacheSupport->leerManifest($empresaId, $fechaDesde, $fechaHasta, $sufijoCache);
        $stkmovFilas = (int) (($manifest['counts']['stkmov'] ?? 0));
        $this->info(sprintf(
            'Empresa %d | %s → %s | cache: %s | stkmov: %d filas',
            $empresaId,
            $fechaDesde,
            $fechaHasta,
            $manifest['directorio'] ?? '—',
            $stkmovFilas,
        ));

        if ($stkmovFilas === 0) {
            $this->warn('stkmov.json vacío: reproceso local imposible. Precargue cache vía bridge (gastronomia:precargar-cache-import-anita --forzar).');
            if ($empresaId !== 1) {
                return self::FAILURE;
            }
        }

        if ($soloVerificar) {
            $cov = $service->verificarCoberturaCache($empresaId, $fechaDesde, $fechaHasta, $codigos, $sufijoCache);
            $this->table(['Métrica', 'Valor'], [
                ['Candidatas (línea ficticia import)', (string) $cov['candidatas']],
                ['Con stkmov en cache', (string) $cov['cubiertas_cache']],
                ['Sin stkmov en cache', (string) $cov['faltantes_cache']],
            ]);

            return $cov['faltantes_cache'] > 0 ? self::FAILURE : self::SUCCESS;
        }

        if ($dryRun) {
            $this->comment('Modo dry-run: no se graban cambios.');
        }

        try {
            $ret = $service->reexpandir(
                $empresaId,
                $fechaDesde,
                $fechaHasta,
                $codigos,
                $sufijoCache,
                $dryRun,
                $limite,
            );
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->table(['Métrica', 'Valor'], [
            ['Candidatas', (string) $ret['candidatas']],
            ['Reexpandidas', (string) $ret['reexpandidas'].($dryRun ? ' (simuladas)' : '')],
            ['Sin stkmov cache', (string) $ret['sin_stkmov_cache']],
            ['Renglones creados', (string) $ret['renglones_creados']],
            ['Errores', (string) count($ret['errores'])],
        ]);

        foreach (array_slice($ret['errores'], 0, 30) as $err) {
            $this->warn($err);
        }
        if (count($ret['errores']) > 30) {
            $this->warn('… '.(count($ret['errores']) - 30).' errores más');
        }

        return $ret['errores'] !== [] ? self::FAILURE : self::SUCCESS;
    }
}
