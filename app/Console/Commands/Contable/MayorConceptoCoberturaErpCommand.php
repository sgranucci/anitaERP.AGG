<?php

namespace App\Console\Commands\Contable;

use App\Support\Contable\MayorConcepto\MayorConceptoCoberturaErpSupport;
use Illuminate\Console\Command;

class MayorConceptoCoberturaErpCommand extends Command
{
    protected $signature = 'contable:cobertura-erp
                            {--empresa=1,2,3 : IDs de empresa separados por coma}
                            {--desde=2025-01-01 : Fecha ISO desde}
                            {--hasta= : Fecha ISO hasta (default: hoy)}
                            {--solo-problemas : Muestra únicamente los meses que no están APTO}
                            {--json= : Ruta donde volcar el resultado completo}';

    protected $description = 'Mide mes a mes si el ERP tiene datos suficientes para el Mayor por concepto sin leer de Anita';

    public function handle(): int
    {
        $empresaIds = array_map('intval', array_filter(explode(',', (string) $this->option('empresa'))));
        $desde = (string) $this->option('desde');
        $hasta = (string) ($this->option('hasta') ?: date('Y-m-d'));

        $filas = MayorConceptoCoberturaErpSupport::medir($empresaIds, $desde, $hasta);

        if ($filas === []) {
            $this->warn('Sin datos en el rango pedido.');

            return self::SUCCESS;
        }

        $soloProblemas = (bool) $this->option('solo-problemas');
        $tabla = [];
        foreach ($filas as $fila) {
            if ($soloProblemas && $fila['veredicto'] === MayorConceptoCoberturaErpSupport::APTO) {
                continue;
            }

            $tabla[] = [
                $fila['empresa_id'],
                $fila['periodo'],
                $fila['asientos'],
                $fila['renglones'],
                $fila['pagos'],
                $fila['comprobantes'],
                $fila['cc_aplicaciones'],
                $fila['veredicto'],
            ];
        }

        $this->table(
            ['Emp', 'Período', 'Asientos', 'Rengl.', 'Pagos', 'Facturas', 'Aplic.CC', 'Veredicto'],
            $tabla,
        );

        $resumen = [];
        foreach ($filas as $fila) {
            $resumen[$fila['veredicto']] = ($resumen[$fila['veredicto']] ?? 0) + 1;
        }
        foreach ($resumen as $veredicto => $cantidad) {
            $linea = sprintf('%-10s %d mes(es)', $veredicto, $cantidad);
            $veredicto === MayorConceptoCoberturaErpSupport::APTO
                ? $this->info($linea)
                : $this->warn($linea);
        }

        $motivos = [];
        foreach ($filas as $fila) {
            foreach ($fila['faltantes'] as $faltante) {
                $motivos[$faltante] = ($motivos[$faltante] ?? 0) + 1;
            }
        }
        if ($motivos !== []) {
            $this->newLine();
            $this->comment('Por qué no está apto:');
            arsort($motivos);
            foreach ($motivos as $motivo => $cantidad) {
                $this->line(sprintf('  %-4d mes(es)  %s', $cantidad, $motivo));
            }
        }

        $json = (string) $this->option('json');
        if ($json !== '') {
            file_put_contents($json, json_encode($filas, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $this->info('Detalle completo en '.$json);
        }

        return self::SUCCESS;
    }
}
