<?php

namespace App\Console\Commands\Caja;

use App\Models\Ventas\Cliente_Cuentacorriente_Aplicacion;
use App\Services\Caja\CobranzaPagoDeMasCoaService;
use Illuminate\Console\Command;
use Throwable;

class ConvertirSobranteCobranzaCoaCommand extends Command
{
    protected $signature = 'caja:convertir-sobrante-cobranza-coa
                            {--ejecutar : Crea las COA y les pasa el renglón. Sin este flag solo informa}';

    protected $description = 'Pasa a COA los pagos de más grabados como renglón de una cobranza COB sin factura';

    public function handle(CobranzaPagoDeMasCoaService $service): int
    {
        $ejecutar = (bool) $this->option('ejecutar');
        $filas = $service->sobrantesLegacy();

        $this->line(sprintf(
            'Sobrantes de cobranza grabados como COB: %d · %s',
            $filas->count(),
            $ejecutar ? 'CREA las COA' : 'solo informe, no graba'
        ));

        if ($filas->isEmpty()) {
            return self::SUCCESS;
        }

        $tabla = [];
        foreach ($filas as $fila) {
            $aplicado = (float) Cliente_Cuentacorriente_Aplicacion::query()
                ->where('cliente_cuentacorriente_id', $fila->id)
                ->sum('total');
            $tabla[] = [
                $fila->id,
                substr((string) $fila->fecha, 0, 10),
                $fila->cliente_id,
                (string) ($fila->cobranzas->numerotransaccion ?? ''),
                number_format((float) $fila->total, 2, ',', '.'),
                number_format($aplicado, 2, ',', '.'),
            ];
        }
        $this->table(['Renglón CC', 'Fecha', 'Cliente', 'Cobranza', 'Sobrante', 'Aplicado'], $tabla);

        if (! $ejecutar) {
            $this->comment('No se grabó nada. Para persistir: php artisan caja:convertir-sobrante-cobranza-coa --ejecutar');

            return self::SUCCESS;
        }

        $creadas = 0;
        foreach ($filas as $fila) {
            try {
                $coa = $service->convertirSobranteLegacy($fila);
                $creadas++;
                $this->line('Renglón '.$fila->id.' → '.$coa->detalle);
            } catch (Throwable $e) {
                $this->error('Renglón '.$fila->id.': '.$e->getMessage());
            }
        }
        $this->info('COA creadas: '.$creadas.' de '.$filas->count());
        $this->warn('La corrida por consola no deja fila en audits.');

        return $creadas === $filas->count() ? self::SUCCESS : self::FAILURE;
    }
}
