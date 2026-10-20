<?php

namespace App\Jobs\Compras;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Crea la estructura Facturas_scan en el NAS (proceso www-data vía queue).
 */
class PrepararFacturasScanNasJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 60;

    public function __construct(
        public string $basePath,
        public string $markerPath,
    ) {}

    public function handle(): void
    {
        $uid = function_exists('posix_geteuid') ? (int) posix_geteuid() : -1;
        $user = function_exists('posix_getpwuid') ? ((posix_getpwuid($uid)['name'] ?? '?')) : '?';
        $lines = ["uid={$uid} user={$user}", "base={$this->basePath}"];

        $dirs = [
            $this->basePath,
            $this->basePath.'/comprobantes',
            $this->basePath.'/comprobantes-revisar',
            $this->basePath.'/entrada_ia/entrada',
            $this->basePath.'/entrada_ia/procesando',
            $this->basePath.'/entrada_ia/ok',
            $this->basePath.'/entrada_ia/error',
        ];

        foreach ($dirs as $dir) {
            $ok = is_dir($dir) || @mkdir($dir, 0775, true) || is_dir($dir);
            $lines[] = ($ok ? 'OK' : 'FAIL')." mkdir {$dir}";
            if (! $ok) {
                @file_put_contents($this->markerPath, implode("\n", $lines));
                throw new RuntimeException('No se pudo crear '.$dir);
            }
        }

        $probe = $this->basePath.'/comprobantes/.probe_write';
        $written = @file_put_contents($probe, 'ok');
        $lines[] = ($written !== false ? 'OK' : 'FAIL').' write '.$probe;
        if ($written !== false) {
            @unlink($probe);
        }

        @file_put_contents($this->markerPath, implode("\n", $lines));
        Log::info('facturas_scan.nas_preparado', [
            'base' => $this->basePath,
            'user' => $user,
        ]);
    }
}
