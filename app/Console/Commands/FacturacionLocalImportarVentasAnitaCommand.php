<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\ApiAnita;
use App\Models\Seguridad\Usuario;
use App\Services\Ventas\FacturacionLocal\FacturacionLocalVentaAnitaImportService;
use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;

class FacturacionLocalImportarVentasAnitaCommand extends Command
{
    protected $signature = 'facturacion-local:importar-ventas-anita
                            {--desde=2026-09-01 : Fecha inicial Y-m-d}
                            {--hasta=2026-09-30 : Fecha final Y-m-d}
                            {--usuario= : usuario_id para las altas}
                            {--dry-run : Solo lista las que faltan}
                            {--ejecutar : Graba en anitaERP. No escribe Anita ni asientos}
                            {--solo= : Solo estas claves tipo|letra|sucursal|numero, separadas por coma}';

    protected $description = 'Importa cabeceras de venta de locales que están en Anita y faltan en anitaERP (sin asiento ni stock)';

    public function handle(FacturacionLocalVentaAnitaImportService $service): int
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            $this->error('Solo aplica a Calzados Ferli.');

            return self::FAILURE;
        }
        if ((bool) $this->option('ejecutar') && (bool) $this->option('dry-run')) {
            $this->error('No combine --ejecutar con --dry-run.');

            return self::FAILURE;
        }
        $ejecutar = (bool) $this->option('ejecutar');
        $usuarioId = (int) ($this->option('usuario') ?: Usuario::query()->orderBy('id')->value('id') ?? 1);
        if ($usuarioId <= 0 || ! Auth::loginUsingId($usuarioId)) {
            $this->error('Usuario inválido.');

            return self::FAILURE;
        }

        $desde = str_replace('-', '', trim((string) $this->option('desde')));
        $hasta = str_replace('-', '', trim((string) $this->option('hasta')));
        $this->line('Bridge: '.ApiAnita::urlBridge());
        $this->line(sprintf(
            'Locales PV 17,21,23,25,26,27 | %s → %s | %s',
            $desde,
            $hasta,
            $ejecutar ? 'EJECUTAR' : 'DRY-RUN'
        ));

        try {
            $solo = trim((string) $this->option('solo'));
            $soloClaves = $solo === '' ? null : array_values(array_filter(array_map('trim', explode(',', $solo))));
            $r = $service->importarFaltantes($desde, $hasta, ! $ejecutar, $soloClaves);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->table(['Concepto', 'Valor'], [
            ['Cabeceras Anita', (string) $r['anita']],
            ['Faltantes en ERP', (string) $r['faltantes']],
            ['Creadas', (string) $r['creadas']],
            ['Errores', (string) count($r['errores'])],
        ]);
        foreach ($r['detalle'] as $linea) {
            $this->line('  '.$linea);
        }
        foreach ($r['errores'] as $error) {
            $this->error('  '.$error);
        }

        return $r['errores'] === [] ? self::SUCCESS : self::FAILURE;
    }
}
