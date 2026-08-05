<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Ventas\AlinearFacturaAnitaArcaService;
use Illuminate\Console\Command;

/**
 * Alinea una factura Anita (venta/climov/comprob/compaux/subdiario/venibr)
 * a los importes/cantidades autorizados en ARCA MTXCA.
 *
 * Piloto: FAC A 10 47 (empresa Bierzo=1, tipo AFIP 1).
 */
class AlinearFacturaAnitaArca extends Command
{
    protected $signature = 'ventas:alinear-factura-anita-arca
                            {tipo=FAC : Tipo Anita (FAC/NCC/...)}
                            {letra=A : Letra}
                            {sucursal=10 : Punto de venta}
                            {numero=47 : Número de comprobante}
                            {--empresa=1 : empresa_id ERP (Bierzo)}
                            {--cbte-tipo=1 : Código tipo AFIP (1=FAC A)}
                            {--force : Aplicar updates en Anita}
                            {--yes : Sin confirmación}';

    protected $description = 'Alinea FAC Anita a montos/cantidades de ARCA (piloto IVA ventas)';

    public function handle(AlinearFacturaAnitaArcaService $service): int
    {
        $tipo = (string) $this->argument('tipo');
        $letra = (string) $this->argument('letra');
        $sucursal = (int) $this->argument('sucursal');
        $numero = (int) $this->argument('numero');
        $empresaId = (int) $this->option('empresa');
        $cbteTipo = (int) $this->option('cbte-tipo');
        $aplicar = (bool) $this->option('force');

        if ($aplicar && ! $this->option('yes')) {
            if (! $this->confirm("¿Aplicar alineación Anita→ARCA de {$tipo} {$letra} {$sucursal} {$numero}?", false)) {
                $this->warn('Cancelado.');

                return self::SUCCESS;
            }
        }

        $this->info(($aplicar ? '[APLICAR] ' : '[PREVIEW] ')."{$tipo} {$letra} {$sucursal} {$numero}");

        try {
            $resultado = $service->alinear(
                $empresaId,
                $tipo,
                $letra,
                $sucursal,
                $numero,
                $cbteTipo,
                $aplicar,
            );
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $antes = $resultado['antes']['venta'];
        $arca = $resultado['arca'];

        $this->table(
            ['campo', 'Anita antes', 'ARCA'],
            [
                ['total', $antes['ven_monto'] ?? '', $arca['total']],
                ['gravado', $antes['ven_gravado'] ?? '', $arca['gravado']],
                ['iva', $antes['ven_impuesto1'] ?? '', $arca['iva']],
                ['iibb', $antes['ven_perc_ing_bruto'] ?? '', $arca['iibb']],
                ['perc_iva', $antes['ven_percepcion_iva'] ?? '', $arca['perc_iva']],
                ['logistica', $antes['ven_logistica'] ?? '', $arca['logistica']],
            ],
        );

        $this->info('Plan ('.count($resultado['plan']).' updates):');
        foreach ($resultado['plan'] as $i => $paso) {
            $this->line(sprintf(
                '  %02d) [%s] %s',
                $i + 1,
                $paso['tabla'],
                $paso['descripcion']
            ));
        }

        $this->line('Backup: '.$resultado['backup_path']);

        if (! $aplicar) {
            $this->warn('Dry-run: no se modificó Anita. Ejecutar con --force --yes para aplicar.');

            return self::SUCCESS;
        }

        $this->info('Updates aplicados: '.count($resultado['aplicados']));
        $despues = $resultado['despues']['venta'] ?? null;
        if ($despues) {
            $this->table(
                ['campo', 'Anita después', 'ARCA', 'ok'],
                [
                    $this->filaCmp('total', $despues['ven_monto'] ?? null, $arca['total']),
                    $this->filaCmp('gravado', $despues['ven_gravado'] ?? null, $arca['gravado']),
                    $this->filaCmp('iva', $despues['ven_impuesto1'] ?? null, $arca['iva']),
                    $this->filaCmp('iibb', $despues['ven_perc_ing_bruto'] ?? null, $arca['iibb']),
                    $this->filaCmp('perc_iva', $despues['ven_percepcion_iva'] ?? null, $arca['perc_iva']),
                    $this->filaCmp('logistica', $despues['ven_logistica'] ?? null, $arca['logistica']),
                ],
            );
        }

        $climov = $resultado['despues']['climov'][0] ?? null;
        if ($climov) {
            $this->line(sprintf(
                'climov: monto=%s cobrado=%s estado=%s (saldo pendiente ≈ %s)',
                $climov['cliv_monto'],
                $climov['cliv_t_cobrado'],
                $climov['cliv_estado'],
                number_format((float) $climov['cliv_monto'] - (float) $climov['cliv_t_cobrado'], 2, '.', '')
            ));
        }

        return self::SUCCESS;
    }

    /**
     * @return list<string|float|bool>
     */
    private function filaCmp(string $campo, mixed $despues, float $arca): array
    {
        $d = round((float) $despues, 2);
        $a = round($arca, 2);

        return [$campo, $d, $a, abs($d - $a) < 0.02 ? 'OK' : 'DIFF'];
    }
}
