<?php

namespace App\Console\Commands;

use App\Models\Configuracion\Empresa;
use App\Services\Contable\CierreRendicionBingoService;
use Illuminate\Console\Command;
use InvalidArgumentException;
use Throwable;

class BingoAnularCierreRango extends Command
{
    protected $signature = 'bingo:anular-cierre-rango
                            {--empresa= : empresa_id (repetible o 1,2,3)}
                            {--desde= : Jornada desde (Y-m-d)}
                            {--hasta= : Jornada hasta (Y-m-d)}
                            {--dry-run : Solo lista los cierres, no anula}
                            {--confirmar : Ejecuta la anulación (borra asientos ERP y ctamov)}';

    protected $description = 'Anula cierres contables de bingo por rango: baja física de asientos ERP y ctamov';

    public function handle(CierreRendicionBingoService $service): int
    {
        $empresaIds = $this->parseEmpresaIds();
        $desde = trim((string) $this->option('desde'));
        $hasta = trim((string) $this->option('hasta'));
        $dryRun = (bool) $this->option('dry-run');
        $confirmar = (bool) $this->option('confirmar');

        if ($empresaIds === [] || $desde === '' || $hasta === '') {
            $this->error('Indique --empresa=1,2,3 --desde=YYYY-MM-DD --hasta=YYYY-MM-DD');

            return self::FAILURE;
        }

        if (! $dryRun && ! $confirmar) {
            $this->error('Para anular use --confirmar (o --dry-run para listar).');

            return self::FAILURE;
        }

        $huboError = false;
        foreach ($empresaIds as $empresaId) {
            $nombre = (string) (Empresa::query()->whereKey($empresaId)->value('nombre') ?? ('#'.$empresaId));
            $this->newLine();
            $this->info('Empresa '.$empresaId.' — '.$nombre.' — '.$desde.' a '.$hasta);

            try {
                $preview = $service->previewAnularCierreRango($empresaId, $desde, $hasta);
            } catch (InvalidArgumentException $e) {
                $this->warn($e->getMessage());
                continue;
            } catch (Throwable $e) {
                $this->error($e->getMessage());
                $huboError = true;
                continue;
            }

            $this->line(sprintf(
                '  Cierres: %d jornada(s), %d rendición(es), recaudación %s',
                (int) ($preview['cantidad_grupos'] ?? 0),
                (int) ($preview['cantidad'] ?? 0),
                number_format((float) ($preview['total_cobrado'] ?? 0), 2, ',', '.'),
            ));
            foreach ($preview['grupos'] ?? [] as $grupo) {
                $this->line(sprintf(
                    '  · %s  rend=%d  asiento=%s  $%s',
                    (string) ($grupo['fecha_dia_fmt'] ?? $grupo['fecha_dia'] ?? ''),
                    (int) ($grupo['cantidad_rendiciones'] ?? 0),
                    (string) ($grupo['asiento_numero'] ?? '—'),
                    number_format((float) ($grupo['total_cobrado'] ?? 0), 2, ',', '.'),
                ));
            }

            if ($dryRun || (int) ($preview['cantidad_grupos'] ?? 0) <= 0) {
                continue;
            }

            try {
                $resultado = $service->anularCierreRango($empresaId, $desde, $hasta);
            } catch (Throwable $e) {
                $this->error('  Falló la anulación: '.$e->getMessage());
                $huboError = true;
                continue;
            }

            $this->info(sprintf(
                '  Anuladas: %d  omitidas: %d  errores: %d',
                count($resultado['ok'] ?? []),
                count($resultado['omitidos'] ?? []),
                count($resultado['errores'] ?? []),
            ));
            foreach ($resultado['ok'] ?? [] as $fila) {
                $this->line(sprintf(
                    '    OK %s asientos %s',
                    (string) ($fila['fecha_dia'] ?? ''),
                    implode(',', $fila['numeros_asiento'] ?? []),
                ));
            }
            foreach ($resultado['errores'] ?? [] as $fila) {
                $this->error('    ERR '.($fila['grupo_clave'] ?? '?').': '.($fila['mensaje'] ?? ''));
                $huboError = true;
            }
        }

        return $huboError ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return list<int>
     */
    private function parseEmpresaIds(): array
    {
        $raw = $this->option('empresa');
        $partes = is_array($raw) ? $raw : [trim((string) $raw)];
        $ids = [];
        foreach ($partes as $parte) {
            foreach (preg_split('/[,\s]+/', trim((string) $parte)) ?: [] as $token) {
                $id = (int) $token;
                if ($id > 0) {
                    $ids[] = $id;
                }
            }
        }

        return array_values(array_unique($ids));
    }
}
