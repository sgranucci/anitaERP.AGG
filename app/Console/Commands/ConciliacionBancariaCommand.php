<?php

namespace App\Console\Commands;

use App\Exports\Contable\ConciliacionBancariaExport;
use App\Models\Caja\Cuentacaja;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Services\Contable\ConciliacionBancariaService;
use Illuminate\Console\Command;

class ConciliacionBancariaCommand extends Command
{
    protected $signature = 'contable:conciliacion-bancaria
                            {--empresa= : ID empresa}
                            {--cuentacaja= : ID cuenta de caja (ej. 52 para código 127)}
                            {--codigo-cuentacaja= : Código Anita cuenta caja (ej. 127)}
                            {--mes= : Mes 1-12}
                            {--anio= : Año}
                            {--export= : Ruta Excel de salida (opcional)}
                            {--comparar-excel= : Excel Contaduría para benchmark de carátula}
                            {--sin-persistir : No grabar nuevos pares conciliados}';

    protected $description = 'Conciliación bancaria: mayor analítico (bridge) vs movimientos Interbanking persistidos';

    public function handle(
        ConciliacionBancariaService $service,
        EmpresaRepositoryInterface $empresaRepository,
    ): int {
        $empresaId = (int) ($this->option('empresa') ?: 0);
        if ($empresaId <= 0) {
            $empresas = $empresaRepository->allFiltrado();
            if ($empresas->count() === 1) {
                $empresaId = (int) $empresas->first()->id;
            } else {
                $opciones = $empresas->pluck('nombre', 'id')->all();
                $elegida = $this->choice('Seleccione empresa', $opciones);
                $empresaId = (int) array_search($elegida, $opciones, true);
            }
        }

        $cuentacajaId = (int) ($this->option('cuentacaja') ?: 0);
        $codigoCc = trim((string) $this->option('codigo-cuentacaja'));
        if ($cuentacajaId <= 0 && $codigoCc !== '') {
            $cuentacajaId = (int) (Cuentacaja::query()
                ->paraEmpresa($empresaId)
                ->where('codigo', ltrim($codigoCc, '0'))
                ->value('id') ?? 0);
        }

        if ($cuentacajaId <= 0) {
            $cuentas = Cuentacaja::query()
                ->paraEmpresa($empresaId)
                ->whereNotNull('cuenta_interbanking')
                ->where('cuenta_interbanking', '!=', '')
                ->orderBy('nombre')
                ->get(['id', 'codigo', 'nombre', 'cuenta_interbanking']);

            if ($cuentas->isEmpty()) {
                $this->error('No hay cuentas de caja con Interbanking para la empresa '.$empresaId);

                return self::FAILURE;
            }

            $this->table(['ID', 'Código', 'Nombre', 'Cuenta IB'], $cuentas->map(fn ($c) => [
                $c->id, $c->codigo, $c->nombre, $c->cuenta_interbanking,
            ])->all());

            $cuentacajaId = (int) $this->ask('ID cuenta de caja a conciliar');
        }

        $mes = (int) ($this->option('mes') ?: (int) date('n'));
        $anio = (int) ($this->option('anio') ?: (int) date('Y'));

        $cuenta = Cuentacaja::query()->with('cuentacontables')->find($cuentacajaId);
        if (! $cuenta) {
            $this->error('Cuenta de caja no encontrada.');

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Conciliando empresa %d — cuenta caja %s (%s) — %02d/%d',
            $empresaId,
            $cuenta->codigo,
            $cuenta->nombre,
            $mes,
            $anio,
        ));
        $this->line('Cuenta contable: '.($cuenta->cuentacontables?->codigo ?? '—'));
        $this->line('Interbanking: '.($cuenta->cuenta_interbanking ?? '—'));

        $comparar = trim((string) $this->option('comparar-excel'));
        $opciones = [];
        if ($comparar !== '') {
            $opciones['pendientes_excel'] = $comparar;
        }

        try {
            $resultado = $service->ejecutar(
                $empresaId,
                $cuentacajaId,
                $mes,
                $anio,
                null,
                ! $this->option('sin-persistir'),
                $opciones,
            );
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $c = $resultado['caratula'] ?? [];
        $this->newLine();
        $this->info('Resultado conciliación');
        $this->table(['Concepto', 'Importe'], [
            ['Saldo banco (extracto)', $c['saldo_banco_extracto'] ?? 0],
            ['Cheques no acreditados (cpromae)', $c['cheques_no_acreditados'] ?? 0],
            ['Pendientes banco (sin contabilizar)', $c['movimientos_pendientes_banco'] ?? 0],
            ['Saldo banco ajustado', $c['saldo_banco_ajustado'] ?? 0],
            ['Saldo contable', $c['saldo_contable'] ?? 0],
            ['Diferencia', $c['diferencia'] ?? 0],
        ]);

        $this->line('Pares nuevos conciliados: '.count($resultado['pares_nuevos'] ?? []));
        $this->line(sprintf(
            'Pendientes cpromae: %d (carátula mes: %d, fuente: %s)',
            count($resultado['pendientes_cheques_cpromae'] ?? []),
            count($resultado['pendientes_cheques_caratula'] ?? []),
            (string) ($resultado['pendientes_cheques_fuente'] ?? '—'),
        ));
        $this->line('Pendientes contables mayor (otros/sin match): '.count($resultado['pendientes_contables_otros'] ?? []));
        $this->line('Pendientes banco: '.count($resultado['pendientes_banco'] ?? []));
        if (isset($resultado['ai_score'])) {
            $this->line('IA score: '.$resultado['ai_score'].' · anomalías: '.count($resultado['ai_anomalias'] ?? []));
        }

        if ($comparar !== '') {
            try {
                $resultado = $service->compararContraExcel($resultado, $comparar);
            } catch (\Throwable $e) {
                $this->error('Comparación Excel: '.$e->getMessage());

                return self::FAILURE;
            }

            $c = $resultado['caratula'] ?? [];
            $this->newLine();
            $this->info('Benchmark vs Excel Contaduría ('.$resultado['excel_referencia']['archivo'].' / '.$resultado['excel_referencia']['fuente'].')');
            $filasCmp = [];
            foreach ($resultado['excel_comparacion']['filas'] ?? [] as $fila) {
                $filasCmp[] = [
                    $fila['concepto'],
                    $fila['excel'] === null ? '—' : number_format((float) $fila['excel'], 2, ',', '.'),
                    $fila['erp'] === null ? '—' : number_format((float) $fila['erp'], 2, ',', '.'),
                    $fila['delta'] === null ? '—' : number_format((float) $fila['delta'], 2, ',', '.'),
                    ! empty($fila['ok']) ? 'OK' : 'Δ',
                ];
            }
            $this->table(['Concepto', 'Excel', 'ERP', 'Delta', ''], $filasCmp);
            $tol = $resultado['excel_comparacion']['tolerancia'] ?? 1;
            if (! empty($resultado['excel_comparacion']['ok'])) {
                $this->info("Carátula alineada al Excel (tolerancia ±{$tol}).");
            } else {
                $this->warn("Hay desvíos vs Excel (tolerancia ±{$tol}).");
            }

            $cob = $resultado['excel_pendientes_cobertura'] ?? [];
            $det = $resultado['excel_pendientes_detalle'] ?? [];
            $this->line(sprintf(
                'Pendientes: Excel %d · ERP %d · ∩ %d · solo Excel %d · solo ERP %d. Banco Excel: %d (suma %s).',
                (int) ($cob['excel_n'] ?? 0),
                (int) ($cob['erp_n'] ?? 0),
                (int) ($cob['interseccion_n'] ?? 0),
                count($cob['solo_excel'] ?? []),
                count($cob['solo_erp'] ?? []),
                count($det['banco_pendientes'] ?? []),
                number_format((float) ($det['suma_banco_pendientes'] ?? 0), 2, ',', '.'),
            ));
        }

        $export = trim((string) $this->option('export'));
        if ($export !== '') {
            (new ConciliacionBancariaExport)->parametros($resultado)->guardarEn($export);
            $this->info('Excel generado: '.$export);
        }

        return self::SUCCESS;
    }
}
