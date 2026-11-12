<?php

namespace App\Console\Commands;

use App\Models\Caja\Cuentacaja;
use App\Services\Contable\ConciliacionBancariaAplicarComparacionChequesService;
use Illuminate\Console\Command;

class ConciliacionBancariaAplicarComparacionChequesCommand extends Command
{
    protected $signature = 'contable:conciliacion-bancaria-aplicar-comparacion-cheques
                            {ruta : Excel comparación Florencia}
                            {--empresa=1 : ID empresa}
                            {--codigo-cuentacaja=127 : Código Anita}
                            {--cuentacaja= : ID cuentacaja}
                            {--aplicar : Persistir altas/bajas (sin esto es dry-run)}';

    protected $description = 'Aplica leyenda Florencia: amarillo=alta ERP, rojo=baja ERP (posicion_bancaria_cheque)';

    public function handle(ConciliacionBancariaAplicarComparacionChequesService $service): int
    {
        $ruta = (string) $this->argument('ruta');
        $empresaId = (int) $this->option('empresa');
        $cuentacajaId = (int) ($this->option('cuentacaja') ?: 0);
        $codigo = trim((string) $this->option('codigo-cuentacaja'));
        $aplicar = (bool) $this->option('aplicar');

        if ($cuentacajaId <= 0) {
            $cuentacajaId = (int) (Cuentacaja::query()
                ->paraEmpresa($empresaId)
                ->where('codigo', ltrim($codigo, '0'))
                ->value('id') ?? 0);
        }
        if ($cuentacajaId <= 0) {
            $this->error('No se resolvió la cuenta de caja.');

            return self::FAILURE;
        }

        $this->info(($aplicar ? 'APLICAR' : 'DRY-RUN').' · empresa '.$empresaId.' · cuentacaja '.$cuentacajaId);

        try {
            $r = $service->aplicar($ruta, $empresaId, $cuentacajaId, $aplicar);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->table(['Concepto', 'Valor'], [
            ['Archivo', $r['archivo']],
            ['Verdes Anita / ERP', $r['verdes_anita_n'].' / '.$r['verdes_erp_n']],
            ['Amarillos (faltan ERP)', $r['amarillos_n']],
            ['Rojos (sobran ERP)', $r['rojos_n']],
            ['Altas nuevas', $r['altas']],
            ['Reactivados', $r['reactivados']],
            ['Actualizados', $r['actualizados']],
            ['Bajas', $r['bajas']],
            ['Rojos sin stock ERP', $r['rojos_sin_stock']],
        ]);

        if ($r['altas_numeros'] !== []) {
            $this->line('Altas/reactiva (muestra): '.implode(', ', array_slice($r['altas_numeros'], 0, 25)));
        }
        if ($r['bajas_numeros'] !== []) {
            $this->line('Bajas (muestra): '.implode(', ', array_slice($r['bajas_numeros'], 0, 25)));
        }

        return self::SUCCESS;
    }
}
