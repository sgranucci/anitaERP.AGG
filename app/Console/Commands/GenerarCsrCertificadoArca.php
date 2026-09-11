<?php

namespace App\Console\Commands;

use App\Services\Arca\ArcaCertificadoCsrService;
use Exception;
use Illuminate\Console\Command;

class GenerarCsrCertificadoArca extends Command
{
    protected $signature = 'arca:generar-csr
                            {--servicio= : wsfe,mtxca,wsremcarne,padron,wscdc,wsapoc | factura | remito | factura-remito | all}
                            {--empresa_id= : Solo esta empresa_id (WSFE/MTXCA/remito)}
                            {--alias= : Override del CN (por defecto el alias del certificado vigente)}
                            {--reutilizar-clave : Firma el CSR con la privada.key actual (no genera clave nueva)}
                            {--instalar : Instala cert.crt de la última renovación sobre producción (solo ese --servicio)}
                            {--replicar= : Con --instalar, copiar el mismo cert a otros ids (ej. wscdc,padron). Por default no replica}
                            {--dir= : Carpeta de renovación (solo con --instalar)}
                            {--force : Permite reinstalar / no abortar si el destino de renovación existe}
                            {--ejecutar : Escribe archivos. Sin este flag solo lista / simula}';

    protected $description = 'Genera el CSR de renovación ARCA copiando alias y CUIT del certificado vigente (no pisa producción hasta --instalar)';

    public function handle(ArcaCertificadoCsrService $svc): int
    {
        try {
            $servicios = $svc->parsearServicios($this->option('servicio') !== null ? (string) $this->option('servicio') : null);
        } catch (Exception $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $empresaOpt = $this->option('empresa_id');
        $empresaId = ($empresaOpt !== null && $empresaOpt !== '') ? (int) $empresaOpt : null;

        $inventario = $svc->filtrar($svc->inventariar(), $servicios, $empresaId);
        if ($inventario === []) {
            $this->warn('No hay certificados configurados para el filtro indicado.');

            return self::SUCCESS;
        }

        $this->table(
            ['Id', 'Servicio', 'Empresa', 'Alias', 'CUIT', 'Vence', 'Días', 'Archivo'],
            array_map(fn (array $f) => [
                $f['id'],
                $f['etiqueta'],
                $this->empresaCelda($f),
                $f['alias'] ?? '—',
                $f['cuit'] ?? '—',
                $f['valid_to'] ?? '—',
                $f['dias_restantes'] === null ? '—' : (string) $f['dias_restantes'],
                $this->rutaCorta((string) $f['cert_path']).($f['error'] ? ' ['.$f['error'].']' : ''),
            ], $inventario)
        );

        $instalar = (bool) $this->option('instalar');
        $ejecutar = (bool) $this->option('ejecutar');

        if ($instalar && (bool) $this->option('reutilizar-clave')) {
            $this->error('No combine --instalar con --reutilizar-clave.');

            return self::FAILURE;
        }

        if ($ejecutar && $servicios === null) {
            $this->error('Indique --servicio= (wsfe, mtxca, wsremcarne, padron, wscdc o wsapoc). Cada webservice se procesa por separado.');

            return self::FAILURE;
        }

        if (! $ejecutar) {
            if ($instalar) {
                $this->comment('Dry-run: no se reemplazan certificados. Para instalar: agregue --ejecutar');
            } else {
                $this->comment('Dry-run: no se escribió ningún CSR. Para generar uno: php artisan arca:generar-csr --servicio=wsapoc --ejecutar');
            }
            $this->line('Por default solo se toca el --servicio indicado. Para copiar el mismo cert a otros: --replicar=wscdc,padron');

            return self::SUCCESS;
        }

        if ($instalar) {
            return $this->instalar($svc, $inventario);
        }

        return $this->generar($svc, $inventario);
    }

    /**
     * @param  list<array<string, mixed>>  $inventario
     */
    private function generar(ArcaCertificadoCsrService $svc, array $inventario): int
    {
        $alias = $this->option('alias');
        $alias = is_string($alias) && trim($alias) !== '' ? trim($alias) : null;
        $reutilizar = (bool) $this->option('reutilizar-clave');
        $force = (bool) $this->option('force');

        $aGenerar = $inventario;
        $ok = 0;
        $fail = 0;
        foreach ($aGenerar as $f) {
            try {
                $r = $svc->generar($f, $reutilizar, $alias, $force);
                $this->info("CSR {$r['id']} alias={$r['alias']} CUIT={$r['cuit']}");
                $this->line('  '.$r['csr_path']);
                $this->line('  '.$r['key_path']);
                $this->line('  subject: '.$r['subject']);
                $ok++;
            } catch (Exception $e) {
                $this->error($f['id'].': '.$e->getMessage());
                $fail++;
            }
        }

        $this->info("Listo: {$ok} CSR, {$fail} con error.");
        if ($ok > 0) {
            $this->line('Suba cada pedido.csr en ARCA con el mismo alias. Cuando tenga el .crt, guárdelo como cert.crt en esa carpeta y corra:');
            $this->line('  php artisan arca:generar-csr --instalar --servicio=wsapoc --ejecutar');
        }

        return $fail > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @param  list<array<string, mixed>>  $inventario
     */
    private function instalar(ArcaCertificadoCsrService $svc, array $inventario): int
    {
        $dir = $this->option('dir');
        $dir = is_string($dir) && trim($dir) !== '' ? trim($dir) : null;
        $force = (bool) $this->option('force');
        $replicarOpt = $this->option('replicar');
        $replicarIds = [];
        if (is_string($replicarOpt) && trim($replicarOpt) !== '') {
            $replicarIds = array_values(array_filter(preg_split('/[,\s]+/', trim($replicarOpt)) ?: []));
        }

        $aInstalar = $inventario;
        $ok = 0;
        $fail = 0;
        foreach ($aInstalar as $f) {
            try {
                $r = $svc->instalar($f, $dir, $force, $replicarIds);
                $this->info("Instalado {$f['id']} alias=".($f['alias'] ?? ''));
                $this->line('  backup: '.$r['backup_dir']);
                foreach ($r['instalados'] as $inst) {
                    $this->line('  cert: '.$inst['cert']);
                }
                if ($r['ta_borrados'] !== []) {
                    $this->line('  TA borrados: '.count($r['ta_borrados']));
                }
                $ok++;
            } catch (Exception $e) {
                $this->error($f['id'].': '.$e->getMessage());
                $fail++;
            }
        }

        $this->info("Instalación: {$ok} ok, {$fail} con error.");

        return $fail > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $f
     */
    private function empresaCelda(array $f): string
    {
        if (empty($f['empresa_id'])) {
            return '—';
        }
        $nombre = (string) ($f['empresa_nombre'] ?? '');
        $carpeta = (string) ($f['carpeta'] ?? '');

        return $f['empresa_id'].($nombre !== '' ? ' '.$nombre : '').($carpeta !== '' ? ' ['.$carpeta.']' : '');
    }

    private function rutaCorta(string $path): string
    {
        $base = storage_path('app/arca/');
        if (str_starts_with($path, $base)) {
            return 'storage/app/arca/'.substr($path, strlen($base));
        }

        return $path;
    }
}
