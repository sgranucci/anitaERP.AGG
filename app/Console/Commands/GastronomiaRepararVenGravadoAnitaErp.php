<?php

namespace App\Console\Commands;

use App\ApiAnita;
use App\Models\Configuracion\Empresa;
use App\Services\Ventas\Gastronomia\GastronomiaChequeoVentasAnitaErpService;
use Illuminate\Console\Command;

class GastronomiaRepararVenGravadoAnitaErp extends Command
{
    protected $signature = 'gastronomia:reparar-ven-gravado-anita-erp
                            {--empresa=2 : empresa_id ERP}
                            {--fecha-desde= : Y-m-d jornada inicial}
                            {--fecha-hasta= : Y-m-d jornada final}
                            {--tolerancia=0.02 : Tolerancia en pesos}';

    protected $description = 'Actualiza ven_monto, ven_gravado, ven_impuesto1 y ven_exento en Anita desde montos ERP de cabecera';

    public function handle(GastronomiaChequeoVentasAnitaErpService $chequeoService): int
    {
        $empresaId = (int) $this->option('empresa');
        $empresa = Empresa::query()->find($empresaId);
        if (! $empresa) {
            $this->error("Empresa {$empresaId} inexistente.");

            return self::FAILURE;
        }

        $fechaDesde = trim((string) ($this->option('fecha-desde') ?? ''));
        $fechaHasta = trim((string) ($this->option('fecha-hasta') ?? ''));
        if ($fechaDesde === '' || $fechaHasta === '') {
            $this->error('Indique --fecha-desde y --fecha-hasta (Y-m-d).');

            return self::FAILURE;
        }

        $tolerancia = max(0.0, (float) $this->option('tolerancia'));

        $this->line('Bridge: '.ApiAnita::urlBridge());
        $this->line(sprintf(
            'Reparar ven_gravado | %s (id %d) | jornada %s → %s',
            $empresa->nombre ?? '',
            $empresaId,
            $fechaDesde,
            $fechaHasta,
        ));

        $ventas = $chequeoService->listarVentasGastronomiaEmpresaRangoJornada($empresaId, $fechaDesde, $fechaHasta);
        if ($ventas->isEmpty()) {
            $this->comment('Sin ventas gastronomía en el rango.');

            return self::SUCCESS;
        }

        $resultado = $chequeoService->repararVenGravadoEnAnitaPorVentasErp($ventas, $tolerancia);

        $this->table(
            ['Concepto', 'Valor'],
            [
                ['Ventas revisadas', (string) $resultado['revisadas']],
                ['Cabeceras Anita actualizadas', (string) $resultado['actualizadas']],
                ['Errores', (string) count($resultado['errores'])],
            ],
        );

        if (($resultado['errores'] ?? []) !== []) {
            $this->warn('Errores (primeras 10)');
            foreach (array_slice($resultado['errores'], 0, 10) as $err) {
                $this->line(sprintf(
                    '  %s (#%s): %s',
                    $err['codigo'] ?? '',
                    $err['venta_id'] ?? '',
                    $err['mensaje'] ?? '',
                ));
            }

            return self::FAILURE;
        }

        $this->info($resultado['actualizadas'] > 0
            ? 'Montos de cabecera (monto / gravado / IVA / exento) actualizados en Anita.'
            : 'No había cabeceras con montos desfasados.');

        return self::SUCCESS;
    }
}
