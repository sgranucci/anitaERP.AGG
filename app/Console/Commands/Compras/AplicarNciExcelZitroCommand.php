<?php

declare(strict_types=1);

namespace App\Console\Commands\Compras;

use App\Models\Compras\Proveedor_Cuentacorriente;
use App\Repositories\Compras\Proveedor_CuentacorrienteRepositoryInterface;
use App\Services\Compras\ProveedorSaldarDeudaInternaSinAsientoService;
use App\Support\Compras\ProveedorCuentacorrienteGrillaSupport;
use Illuminate\Console\Command;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;

class AplicarNciExcelZitroCommand extends Command
{
    protected $signature = 'compras:aplicar-nci-excel-zitro
                            {excel : Ruta al xlsx (solapas Biyemas y Kandiko)}
                            {--codigo=3968 : Código proveedor}
                            {--fecha= : Fecha Y-m-d (default hoy)}
                            {--usuario=1 : usuario_id}
                            {--sync-anita : Tras ERP, escribe compra/promov y aplmovp en Anita}
                            {--ejecutar : Graba. Sin este flag solo informa}';

    protected $description = 'NCI interna sobre cuotas coloreadas del Excel Zitro BSA (Biyemas+Kandiko), opcional sync Anita';

    public function handle(
        ProveedorSaldarDeudaInternaSinAsientoService $service,
        Proveedor_CuentacorrienteRepositoryInterface $repo,
    ): int {
        $path = (string) $this->argument('excel');
        if (! is_file($path)) {
            $this->error('No existe el Excel: '.$path);

            return self::FAILURE;
        }

        $codigo = trim((string) $this->option('codigo'));
        $fecha = trim((string) ($this->option('fecha') ?: date('Y-m-d')));
        $usuarioId = max(1, (int) $this->option('usuario'));
        $syncAnita = (bool) $this->option('sync-anita');
        $ejecutar = (bool) $this->option('ejecutar');

        $this->line(sprintf(
            'Excel %s · proveedor %s · fecha %s · %s%s',
            $path,
            $codigo,
            $fecha,
            $ejecutar ? 'GRABA' : 'dry-run',
            $syncAnita ? ' + sync Anita' : ''
        ));

        try {
            $seleccion = $this->leerExcel($path);
            $matched = $this->matchearErp($seleccion, $codigo, $repo);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        foreach ([1 => 'Biyemas', 2 => 'Kandiko'] as $empId => $nombre) {
            $filas = array_values(array_filter($matched, static fn (array $m): bool => $m['empresa_id'] === $empId));
            $totPes = 0.0;
            $totDol = 0.0;
            foreach ($filas as $f) {
                if ((int) $f['moneda_id'] === 1) {
                    $totPes += $f['saldo_erp'];
                } else {
                    $totDol += $f['saldo_erp'];
                }
            }
            $this->info(sprintf(
                '%s: %d cuotas · PES %s · DOL %s',
                $nombre,
                count($filas),
                number_format($totPes, 2, ',', '.'),
                number_format($totDol, 2, ',', '.')
            ));
        }

        if ($matched === []) {
            $this->error('No se matcheó ninguna fila del Excel con la CC del ERP.');

            return self::FAILURE;
        }

        foreach ([1, 2] as $empresaId) {
            $filas = array_values(array_filter(
                $matched,
                static fn (array $m): bool => $m['empresa_id'] === $empresaId
            ));
            if ($filas === []) {
                continue;
            }
            $deudas = array_map(static fn (array $m): array => [
                'cc_id' => (int) $m['cc_id'],
                'monto' => (float) $m['saldo_erp'],
            ], $filas);

            try {
                $res = $service->aplicarDeudasSeleccionadas(
                    $codigo,
                    $empresaId,
                    $deudas,
                    $fecha,
                    $ejecutar,
                    $syncAnita,
                    $usuarioId,
                );
            } catch (\Throwable $e) {
                $this->error('Empresa '.$empresaId.': '.$e->getMessage());

                return self::FAILURE;
            }

            $this->newLine();
            $this->table(['Concepto', 'Valor'], [
                ['Empresa', (string) $res['empresa_id']],
                ['Seleccionadas', (string) $res['seleccionadas']],
                ['NCI a crear', (string) $res['nci_a_crear']],
                ['Totales por moneda', json_encode($res['totales_nci_por_moneda'])],
                ['Comprobantes', implode(', ', $res['comprobantes_creados'] ?: ['—'])],
                ['Apps', (string) ($res['aplicaciones_creadas'] ?? 0)],
                ['Anita', $syncAnita
                    ? (($res['anita_ok'] ?? null) === true ? 'OK' : (($res['anita_error'] ?? 'pendiente')))
                    : 'omitido'],
            ]);
            foreach ($res['nci'] as $nci) {
                $this->line(sprintf(
                    '  NCI mon=%d cot=%s total=%s cuotas=%d',
                    $nci['moneda_id'],
                    $nci['cotizacion'],
                    number_format((float) $nci['total'], 2, ',', '.'),
                    $nci['cuotas']
                ));
            }
        }

        if (! $ejecutar) {
            $this->warn('Dry-run OK. Para grabar: --ejecutar [--sync-anita]');
        } else {
            $this->info('Listo.');
        }

        return self::SUCCESS;
    }

    /**
     * @return list<array{empresa_id:int,comp:string,mon:string,saldo:float,vto:string}>
     */
    private function leerExcel(string $path): array
    {
        $ss = IOFactory::load($path);
        $out = [];
        foreach ($ss->getWorksheetIterator() as $ws) {
            $title = $ws->getTitle();
            $empresaId = str_contains(strtolower($title), 'kandiko') ? 2 : 1;
            $maxR = $ws->getHighestDataRow();
            $maxCi = Coordinate::columnIndexFromString($ws->getHighestDataColumn());
            for ($r = 7; $r <= $maxR; $r++) {
                $comp = $ws->getCell('F'.$r)->getCalculatedValue();
                $mon = $ws->getCell('G'.$r)->getCalculatedValue();
                if (! is_string($comp) || $comp === '' || str_contains($comp, 'Total')) {
                    continue;
                }
                $color = $this->rowColor($ws, $r, $maxCi);
                if ($color === null || in_array($color, ['85C1E9', 'F9E79F'], true)) {
                    continue;
                }
                $isKandiko = $empresaId === 2;
                $saldo = (float) ($isKandiko
                    ? $ws->getCell('K'.$r)->getCalculatedValue()
                    : $ws->getCell('J'.$r)->getCalculatedValue());
                $out[] = [
                    'empresa_id' => $empresaId,
                    'comp' => $comp,
                    'mon' => (string) $mon,
                    'saldo' => $saldo,
                    'vto' => (string) $ws->getCell('E'.$r)->getCalculatedValue(),
                ];
            }
        }

        return $out;
    }

    private function rowColor($ws, int $r, int $maxCi): ?string
    {
        for ($c = 3; $c <= min(11, $maxCi); $c++) {
            $col = Coordinate::stringFromColumnIndex($c);
            $cell = $ws->getCell($col.$r);
            $fill = $cell->getStyle()->getFill();
            $ft = $fill->getFillType();
            if ($ft && $ft !== 'none') {
                $rgb = strtoupper($fill->getStartColor()->getRGB());
                if ($rgb !== '' && $rgb !== 'FFFFFF' && $rgb !== '000000') {
                    return $rgb;
                }
            }
        }

        return null;
    }

    /**
     * @param  list<array{empresa_id:int,comp:string,mon:string,saldo:float,vto:string}>  $seleccion
     * @return list<array{empresa_id:int,cc_id:int,saldo_erp:float,moneda_id:int,cotizacion:float,comp:string}>
     */
    private function matchearErp(
        array $seleccion,
        string $codigo,
        Proveedor_CuentacorrienteRepositoryInterface $repo,
    ): array {
        $proveedorId = (int) (\App\Models\Compras\Proveedor::query()
            ->where('codigo', $codigo)
            ->value('id') ?? 0);
        if ($proveedorId <= 0) {
            throw new \RuntimeException('Proveedor '.$codigo.' no encontrado.');
        }

        $matched = [];
        foreach ([1, 2] as $empresaId) {
            $deudas = $repo->listarPendientesAplicacion($proveedorId, 'deuda', $empresaId);
            $index = [];
            foreach ($deudas as $d) {
                /** @var Proveedor_Cuentacorriente $d */
                $cp = $d->comprobante_proveedores;
                if (! $cp) {
                    continue;
                }
                $cuota = $d->comprobante_proveedor_cuotas?->numero_cuota;
                $key = strtoupper((string) $cp->letra).'|'
                    .(int) $cp->sucursal.'|'
                    .(int) $cp->numerocomprobante.'|'
                    .($cuota !== null ? (int) $cuota : 1);
                $index[$key][] = $d;
                $index[strtoupper((string) $cp->letra).'|'
                    .(int) $cp->sucursal.'|'
                    .(int) $cp->numerocomprobante.'|*'][] = $d;
            }

            foreach (array_filter($seleccion, static fn (array $s): bool => $s['empresa_id'] === $empresaId) as $s) {
                $p = $this->parseComp($s['comp']);
                if ($p === null) {
                    throw new \RuntimeException('No se pudo parsear: '.$s['comp']);
                }
                $cuota = $p['cuota'];
                $key = $p['letra'].'|'.$p['sucursal'].'|'.$p['numero'].'|'.($cuota ?? 1);
                $cands = $index[$key] ?? [];
                if ($cands === [] && $cuota === null) {
                    $cands = $index[$p['letra'].'|'.$p['sucursal'].'|'.$p['numero'].'|*'] ?? [];
                }
                if ($cands === []) {
                    throw new \RuntimeException(
                        'Sin CC ERP para emp '.$empresaId.': '.$s['comp']
                    );
                }
                $best = null;
                $bestDiff = 1e99;
                foreach ($cands as $d) {
                    $saldo = ProveedorCuentacorrienteGrillaSupport::saldoPendienteAbsoluto(
                        (float) $d->total,
                        isset($d->aplicado) ? (float) $d->aplicado : null
                    );
                    $diff = abs($saldo - (float) $s['saldo']);
                    if ($diff < $bestDiff) {
                        $bestDiff = $diff;
                        $best = ['cc' => $d, 'saldo' => $saldo];
                    }
                }
                if ($best === null || $bestDiff > 0.05) {
                    throw new \RuntimeException(sprintf(
                        'Saldo no coincide emp %d %s xlsx=%.2f erp=%s diff=%.4f',
                        $empresaId,
                        $s['comp'],
                        $s['saldo'],
                        $best['saldo'] ?? 'n/a',
                        $bestDiff
                    ));
                }
                $d = $best['cc'];
                $matched[] = [
                    'empresa_id' => $empresaId,
                    'cc_id' => (int) $d->id,
                    'saldo_erp' => (float) $best['saldo'],
                    'moneda_id' => (int) $d->moneda_id,
                    'cotizacion' => (float) $d->cotizacion,
                    'comp' => $s['comp'],
                ];
            }
        }

        return $matched;
    }

    /**
     * @return array{letra:string,sucursal:int,numero:int,cuota:?int}|null
     */
    private function parseComp(string $comp): ?array
    {
        if (! preg_match('/\bA(\d+)-(\d+)\b/', $comp, $m)) {
            return null;
        }
        $cuota = null;
        if (preg_match('/\((\d+)\//', $comp, $c)) {
            $cuota = (int) $c[1];
        }

        return [
            'letra' => 'A',
            'sucursal' => (int) $m[1],
            'numero' => (int) $m[2],
            'cuota' => $cuota,
        ];
    }
}
