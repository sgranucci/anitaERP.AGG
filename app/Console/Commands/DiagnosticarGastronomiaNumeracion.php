<?php

namespace App\Console\Commands;

use App\Models\Ventas\ConfiguracionPuntoventaGastronomia;
use App\Services\Ventas\Gastronomia\GastronomiaEmisionDiagnosticoService;
use App\Support\Ventas\GastronomiaIdentificadorPc;
use Illuminate\Console\Command;

class DiagnosticarGastronomiaNumeracion extends Command
{
    protected $signature = 'gastronomia:diagnostico-numeracion
                            {--identificador-pc= : Identificador PC del PV gastronomía (default: config/env)}';

    protected $description = 'Mide latencia Anita vs ERP para el último número de factura (gastronomía CAEA)';

    public function handle(GastronomiaEmisionDiagnosticoService $diagnostico): int
    {
        $pc = trim((string) ($this->option('identificador-pc') ?: GastronomiaIdentificadorPc::resolver()));
        $cfg = ConfiguracionPuntoventaGastronomia::query()
            ->where('identificador_pc', $pc)
            ->first();

        if (! $cfg) {
            $this->error('Sin configuración PV gastronomía para identificador_pc: '.$pc);

            return self::FAILURE;
        }

        try {
            $m = $diagnostico->medirNumeracion($cfg);
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('PV gastronomía · identificador_pc='.$pc);
        $this->line('Punto venta Anita: '.$m['puntoventa_codigo'].' (id '.$m['puntoventa_id'].') · modo '.$m['modofacturacion']);
        $this->newLine();
        $this->table(
            ['Consulta', 'ms'],
            collect($m['latencias_ms'] ?? [])->map(fn ($ms, $k) => [$k, $ms])->values()->all(),
        );
        $this->newLine();
        $this->line('Último Anita: '.$m['ultimo_numero_anita'].' → siguiente '.($m['siguiente_anita'] ?? '?'));
        $this->line('Último ERP:   '.$m['ultimo_numero_erp'].' → siguiente '.($m['siguiente_erp'] ?? '?'));
        $this->line('Diferencia Anita − ERP: '.($m['diferencia_anita_vs_erp'] ?? 0));
        $this->line($m['nota'] ?? '');

        return self::SUCCESS;
    }
}
