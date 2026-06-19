<?php

namespace App\Console\Commands;

use App\ApiAnita;
use App\Models\Ventas\Puntoventa;
use App\Services\Ventas\Gastronomia\GastronomiaChequeoVentasAnitaErpService;
use Illuminate\Console\Command;

class GastronomiaChequearVentasAnitaErp extends Command
{
    protected $signature = 'gastronomia:chequear-ventas-anita-erp
                            {--fecha=2026-06-01 : Fecha de jornada Y-m-d}
                            {--puntoventa=00003 : Código PV CAE}
                            {--empresa=1 : empresa_id del PV}
                            {--tolerancia=0.02 : Tolerancia en pesos}
                            {--todas : Incluir comprobantes OK (por defecto solo diferencias y faltantes)}
                            {--limite=0 : Máximo de filas en detalle (0 = sin límite)}
                            {--export= : Ruta CSV opcional para exportar detalle}';

    protected $description = 'Concilia factura por factura ERP vs Anita (total, gravado, IVA, exento) por PV y fecha de jornada';

    public function handle(GastronomiaChequeoVentasAnitaErpService $service): int
    {
        $puntoventa = $this->resolverPuntoventa();
        if ($puntoventa === null) {
            return self::FAILURE;
        }

        $fecha = trim((string) $this->option('fecha'));
        $tolerancia = max(0.0, (float) $this->option('tolerancia'));
        $soloDiferencias = ! (bool) $this->option('todas');
        $limite = max(0, (int) $this->option('limite'));

        $this->line('Bridge: '.ApiAnita::urlBridge());
        $this->line(sprintf(
            'PV %s (#%d) | fecha jornada %s | tolerancia %s',
            $puntoventa->codigo,
            $puntoventa->id,
            $fecha,
            number_format($tolerancia, 2, '.', ''),
        ));

        try {
            $resultado = $service->chequear(
                (int) $puntoventa->id,
                $fecha,
                $tolerancia,
                $soloDiferencias,
                $limite,
            );
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $res = $resultado['resumen'];
        $this->newLine();
        $this->info('Resumen');
        $this->table(
            ['Concepto', 'Valor'],
            [
                ['Ventas ERP (gastronomía)', (string) ($res['ventas_erp'] ?? 0)],
                ['Cabeceras Anita', (string) ($res['cabeceras_anita'] ?? 0)],
                ['OK', (string) ($res['conteo']['ok'] ?? 0)],
                ['Con diferencia de importes', (string) ($res['conteo']['diferencia'] ?? 0)],
                ['Solo en ERP', (string) ($res['conteo']['solo_erp'] ?? 0)],
                ['Solo en Anita', (string) ($res['conteo']['solo_anita'] ?? 0)],
                ['Excl. Anita legacy (resvta)', (string) ($res['conteo']['excluido_resvta_legacy'] ?? 0)],
                ['Excl. estacionamiento', (string) ($res['conteo']['excluido_estacionamiento'] ?? 0)],
                ['Filtro Anita', (string) ($res['filtro_anita'] ?? '')],
            ],
        );

        $this->table(
            ['', 'Total', 'Gravado', 'IVA', 'Exento'],
            [
                ['ERP', ...$this->fmtFila($res['totales_erp'] ?? [])],
                ['Anita (signo ERP)', ...$this->fmtFila($res['totales_anita_signo_erp'] ?? [])],
                ['Anita (bruto Informix)', ...$this->fmtFila($res['totales_anita_bruto'] ?? [])],
                ['Delta ERP − Anita', ...$this->fmtFila($res['delta_totales'] ?? [], true)],
            ],
        );

        $filas = $resultado['filas'];
        if ($filas === []) {
            $this->info('Sin diferencias ni faltantes en el detalle (use --todas para listar OK).');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->comment('Detalle'.($soloDiferencias ? ' (solo diferencias / faltantes)' : ''));

        $tabla = [];
        foreach ($filas as $fila) {
            $erp = $fila['erp'] ?? null;
            $anita = $fila['anita'] ?? null;
            $tabla[] = [
                $fila['estado'] ?? '',
                $fila['codigo_erp'] ?? ($fila['tipo'].' '.$fila['numero']),
                $fila['numero'] ?? '',
                $erp !== null ? number_format((float) ($erp['total'] ?? 0), 2, '.', '') : '—',
                $anita !== null ? number_format((float) ($anita['total'] ?? 0), 2, '.', '') : '—',
                $erp !== null ? number_format((float) ($erp['gravado'] ?? 0), 2, '.', '') : '—',
                $anita !== null ? number_format((float) ($anita['gravado'] ?? 0), 2, '.', '') : '—',
                $erp !== null ? number_format((float) ($erp['iva'] ?? 0), 2, '.', '') : '—',
                $anita !== null ? number_format((float) ($anita['iva'] ?? 0), 2, '.', '') : '—',
                implode('; ', array_values($fila['diferencias'] ?? [])),
            ];
        }

        $this->table(
            ['Estado', 'Comprobante', 'Nro', 'Tot ERP', 'Tot Anita', 'Grav ERP', 'Grav Anita', 'IVA ERP', 'IVA Anita', 'Obs.'],
            $tabla,
        );

        $export = trim((string) ($this->option('export') ?? ''));
        if ($export !== '') {
            $this->exportarCsv($export, $filas);
            $this->info('Exportado: '.$export);
        }

        return self::SUCCESS;
    }

    private function resolverPuntoventa(): ?Puntoventa
    {
        $codigo = trim((string) $this->option('puntoventa'));
        $empresaId = (int) $this->option('empresa');

        $puntoventa = Puntoventa::query()
            ->where('codigo', $codigo)
            ->whereHas('empresas', fn ($q) => $q->where('empresa_id', $empresaId))
            ->first();

        if ($puntoventa === null) {
            $this->error('No se encontró PV '.$codigo.' para empresa '.$empresaId.'.');

            return null;
        }

        return $puntoventa;
    }

    /**
     * @param  array<string, float|int>  $montos
     * @return list<string>
     */
    private function fmtFila(array $montos, bool $signed = false): array
    {
        $fmt = static function (mixed $v) use ($signed): string {
            $n = round((float) $v, 2);

            return $signed && $n > 0 ? '+'.$n : (string) $n;
        };

        return [
            $fmt($montos['total'] ?? 0),
            $fmt($montos['gravado'] ?? 0),
            $fmt($montos['iva'] ?? 0),
            $fmt($montos['exento'] ?? 0),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     */
    private function exportarCsv(string $path, array $filas): void
    {
        $handle = fopen($path, 'w');
        if ($handle === false) {
            throw new \RuntimeException('No se pudo escribir '.$path);
        }

        fputcsv($handle, [
            'estado', 'codigo_erp', 'tipo', 'numero', 'venta_id',
            'erp_total', 'erp_gravado', 'erp_iva', 'erp_exento',
            'anita_total', 'anita_gravado', 'anita_iva', 'anita_exento',
            'observaciones',
        ], ';');

        foreach ($filas as $fila) {
            $erp = $fila['erp'] ?? [];
            $anita = $fila['anita'] ?? [];
            fputcsv($handle, [
                $fila['estado'] ?? '',
                $fila['codigo_erp'] ?? '',
                $fila['tipo'] ?? '',
                $fila['numero'] ?? '',
                $fila['venta_id'] ?? '',
                $erp['total'] ?? '',
                $erp['gravado'] ?? '',
                $erp['iva'] ?? '',
                $erp['exento'] ?? '',
                $anita['total'] ?? '',
                $anita['gravado'] ?? '',
                $anita['iva'] ?? '',
                $anita['exento'] ?? '',
                implode(' | ', array_values($fila['diferencias'] ?? [])),
            ], ';');
        }

        fclose($handle);
    }
}
