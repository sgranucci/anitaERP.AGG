<?php

declare(strict_types=1);

namespace App\Console\Commands\Contable;

use App\Mail\Contable\CuentacontableSaldoMesIntegridad;
use App\Repositories\Contable\Cuentacontable_Saldo_MesRepositoryInterface;
use App\Support\Contable\CuentacontableSaldoMesIntegridadSupport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Throwable;

class VerificarSaldosCuentaMesCommand extends Command
{
    protected $signature = 'contable:verificar-saldos-cuenta-mes
        {--empresa=* : IDs de empresa; por defecto las configuradas en contable.saldos_cuenta_mes.integridad_diaria.empresas}
        {--desde= : Período YYYYMM inicial}
        {--hasta= : Período YYYYMM final}
        {--meses= : Ventana hacia atrás en meses desde el mes actual (ignora --desde)}
        {--reparar : Reconstruye el snapshot de las empresas con desvío (ESCRIBE en la base)}
        {--mail : Envía el aviso por mail según configuración}';

    protected $description = 'Compara cuentacontable_saldo_mes contra asiento + asiento_movimiento y reporta desvíos del snapshot mensual';

    public function handle(
        CuentacontableSaldoMesIntegridadSupport $support,
        Cuentacontable_Saldo_MesRepositoryInterface $repo,
    ): int {
        $config = (array) config('contable.saldos_cuenta_mes.integridad_diaria', []);

        $empresaIds = array_values(array_filter(array_map('intval', (array) $this->option('empresa')), fn ($v) => $v > 0));
        if ($empresaIds === []) {
            $empresaIds = array_values(array_filter(array_map(
                'intval',
                preg_split('/\D+/', (string) ($config['empresas'] ?? '')) ?: []
            ), fn ($v) => $v > 0));
        }

        [$desde, $hasta] = $this->ventana($config);

        $this->info(sprintf(
            'Integridad snapshot mensual | empresas %s | períodos %s a %s | tolerancia %.2f',
            $empresaIds === [] ? 'todas' : implode(', ', $empresaIds),
            $desde !== null ? (string) $desde : 'inicio',
            $hasta !== null ? (string) $hasta : 'fin',
            CuentacontableSaldoMesIntegridadSupport::TOLERANCIA
        ));

        $informe = $support->analizar($empresaIds, $desde, $hasta);
        $resumen = $informe['resumen'];

        foreach ($informe['empresas'] as $empresa) {
            if ($empresa['periodos_con_desvio'] === 0) {
                $this->line(sprintf(
                    '  OK  empresa %d %s — snapshot y asientos coinciden (desbalance snapshot %s)',
                    $empresa['empresa_id'],
                    $empresa['nombre'],
                    number_format($empresa['snapshot_desbalance'], 2, ',', '.')
                ));
                continue;
            }

            $this->warn(sprintf(
                '  DESVÍO empresa %d %s — %d período(s), suma |desvío| %s',
                $empresa['empresa_id'],
                $empresa['nombre'],
                $empresa['periodos_con_desvio'],
                number_format($empresa['suma_abs_desvio'], 2, ',', '.')
            ));

            foreach ($empresa['periodos'] as $periodo) {
                $this->line(sprintf(
                    '      %d  snapshot %s | asientos %s | desvío %s',
                    $periodo['periodo'],
                    number_format($periodo['snapshot'], 2, ',', '.'),
                    number_format($periodo['asientos'], 2, ',', '.'),
                    number_format($periodo['desvio'], 2, ',', '.')
                ));
                foreach ($periodo['cuentas'] ?? [] as $cuenta) {
                    $this->line(sprintf(
                        '          cuenta %s %s → desvío %s',
                        $cuenta['codigo'],
                        mb_substr($cuenta['nombre'], 0, 34),
                        number_format($cuenta['desvio'], 2, ',', '.')
                    ));
                }
            }
        }

        if ($this->option('mail')) {
            $this->enviarMail($informe, $config);
        }

        if ($resumen['periodos_con_desvio'] === 0) {
            $this->info('INTEGRIDAD OK — el snapshot mensual reproduce los asientos.');

            return self::SUCCESS;
        }

        $this->error(sprintf(
            'INTEGRIDAD ROTA — %d empresa(s) y %d período(s) con desvío | suma |desvío| %s | peor empresa %s período %s (%s)',
            $resumen['empresas_con_desvio'],
            $resumen['periodos_con_desvio'],
            number_format($resumen['suma_abs_desvio'], 2, ',', '.'),
            (string) ($resumen['peor']['empresa_id'] ?? ''),
            (string) ($resumen['peor']['periodo'] ?? ''),
            number_format((float) ($resumen['peor']['desvio'] ?? 0), 2, ',', '.')
        ));

        if ($this->option('reparar')) {
            foreach ($informe['empresas'] as $empresa) {
                if ($empresa['periodos_con_desvio'] === 0) {
                    continue;
                }
                $this->warn('  Reconstruyendo snapshot de la empresa '.$empresa['empresa_id'].'…');
                $filas = $repo->reconstruir((int) $empresa['empresa_id']);
                $this->line('    filas recalculadas: '.$filas);
            }

            $verificacion = $support->analizar($empresaIds, $desde, $hasta);
            if ($verificacion['resumen']['periodos_con_desvio'] === 0) {
                $this->info('Reparado: el snapshot ya reproduce los asientos.');

                return self::SUCCESS;
            }

            $this->error('Persisten desvíos después de reconstruir: revisar manualmente.');
        } else {
            $this->line('Reparación: php artisan contable:verificar-saldos-cuenta-mes --reparar (reescribe el snapshot de las empresas afectadas).');
        }

        return self::FAILURE;
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array{0: int|null, 1: int|null}
     */
    private function ventana(array $config): array
    {
        $hasta = $this->option('hasta') !== null && $this->option('hasta') !== ''
            ? (int) preg_replace('/\D/', '', (string) $this->option('hasta'))
            : (int) now()->format('Ym');

        $meses = $this->option('meses') !== null && $this->option('meses') !== ''
            ? (int) $this->option('meses')
            : (int) ($config['ventana_meses'] ?? 0);

        if ($this->option('desde') !== null && $this->option('desde') !== '') {
            return [(int) preg_replace('/\D/', '', (string) $this->option('desde')), $hasta];
        }

        if ($meses > 0) {
            return [(int) now()->subMonths(max(0, $meses - 1))->format('Ym'), $hasta];
        }

        return [null, null];
    }

    /**
     * @param  array<string, mixed>  $informe
     * @param  array<string, mixed>  $config
     */
    private function enviarMail(array $informe, array $config): void
    {
        $email = trim((string) ($config['email'] ?? ''));
        if ($email === '') {
            $this->warn('Mail no enviado: falta CONTABLE_SALDOS_INTEGRIDAD_EMAIL.');

            return;
        }

        $hayDesvio = (int) ($informe['resumen']['periodos_con_desvio'] ?? 0) > 0;
        $siempre = filter_var($config['mail_siempre'] ?? false, FILTER_VALIDATE_BOOLEAN);
        if (! $hayDesvio && ! $siempre) {
            return;
        }

        try {
            Mail::to($email)->send(new CuentacontableSaldoMesIntegridad($informe));
            $this->info('Aviso enviado a '.$email);
        } catch (Throwable $e) {
            $this->error('No se pudo enviar el aviso: '.$e->getMessage());
        }
    }
}
