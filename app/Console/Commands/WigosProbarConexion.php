<?php

namespace App\Console\Commands;

use App\Support\Wigos\WigosSqlServerOpenSsl;
use App\Services\Ventas\Gastronomia\WigosAccountInfoService;
use App\Services\Ventas\Gastronomia\WigosCanjePremioService;
use App\Support\Wigos\WigosSqlServerProcess;
use Illuminate\Console\Command;
use PDO;
use RuntimeException;
use Throwable;

/**
 * Diagnóstico de la integración Wigos para canjes de gastronomía.
 *
 * 1) SQL Server (spVoucherGiftData) — conexión, SELECT @@VERSION y opcionalmente cupón real.
 * 2) AccountInfoJSON (HTTP) — opcional con --trackdata.
 */
class WigosProbarConexion extends Command
{
    protected $signature = 'wigos:probar-conexion
                            {--cupon= : Cupón real para invocar spVoucherGiftData (opcional)}
                            {--trackdata= : Trackdata de tarjeta para probar AccountInfoJSON (opcional)}';

    protected $description = 'Prueba conexión SQL Server Wigos y servicio HTTP AccountInfoJSON';

    public function handle(): int
    {
        $this->probarSqlServer();
        $this->probarAccountInfo();

        return self::SUCCESS;
    }

    private function probarSqlServer(): void
    {
        $this->line('');
        $this->line('=== SQL Server (canje premio Wigos) ===');

        if (! config('wigos.habilitado', false)) {
            $this->warn('WIGOS_HABILITADO=false — integración deshabilitada.');

            return;
        }

        $this->info('Driver pdo_sqlsrv: '.(in_array('sqlsrv', PDO::getAvailableDrivers(), true) ? 'OK' : 'FALTA'));
        $this->info('Encrypt: '.config('wigos.encrypt').
            ' | TrustServerCertificate: '.config('wigos.trust_server_certificate').
            ' | LoginTimeout: '.config('wigos.login_timeout').'s');
        $opensslConf = WigosSqlServerOpenSsl::rutaConfiguracion();
        $this->info('OpenSSL Wigos (subproceso): '.($opensslConf ?? 'no configurado'));

        $primario = strtoupper(trim((string) config('wigos.curr_wigos', 'A')));
        if (! in_array($primario, ['A', 'B'], true)) {
            $primario = 'A';
        }
        $secundario = $primario === 'A' ? 'B' : 'A';

        foreach ([$primario, $secundario] as $alias) {
            $this->probarConexionAlias($alias);
        }

        $cupon = trim((string) $this->option('cupon'));
        if ($cupon !== '') {
            $this->line('');
            $this->info('Invocando spVoucherGiftData con cupón '.$cupon.' …');

            try {
                $filas = app(WigosCanjePremioService::class)->consultarPorCodigoBarras($cupon);
                $this->info('Filas: '.count($filas).' | STATUS: '.($filas[0]->STATUS ?? '?'));
                foreach ($filas as $f) {
                    $this->line(' - GIFT_ID='.($f->GIFT_ID ?? '').
                        ' GIFT_NAME='.($f->GIFT_NAME ?? '').
                        ' QTY='.($f->QUANTITY ?? '').
                        ' STATUS='.($f->STATUS ?? ''));
                }
            } catch (Throwable $e) {
                $this->error($e->getMessage());
            }
        }
    }

    private function probarConexionAlias(string $alias): void
    {
        $cfg = (array) config('wigos.connections.'.$alias, []);
        $host = trim((string) ($cfg['host'] ?? ''));
        if ($host === '') {
            $this->warn("[$alias] sin host configurado — se omite.");

            return;
        }

        $endpoint = $host.','.($cfg['port'] ?? '1433');
        $this->info("[$alias] probando ".$endpoint.' / '.($cfg['database'] ?? ''));

        try {
            $res = WigosSqlServerProcess::consultarVersion($alias);
            $linea = trim(preg_split('/[\r\n]/', $res['version'])[0] ?? '');
            $this->info("[$alias] OK — ".$linea);
        } catch (RuntimeException $e) {
            $this->error("[$alias] FALLO — ".$e->getMessage());
        }
    }

    private function probarAccountInfo(): void
    {
        $this->line('');
        $this->line('=== HTTP AccountInfoJSON (canje fidelidad) ===');

        if (! config('wigos.account_info_habilitado', false)) {
            $this->warn('WIGOS_ACCOUNT_INFO_HABILITADO=false — consulta de tarjeta deshabilitada.');

            return;
        }

        $this->info('URL: '.config('wigos.account_info_url'));
        $this->info('Timeout: '.config('wigos.account_info_timeout').'s');

        $trackdata = trim((string) $this->option('trackdata'));
        if ($trackdata === '') {
            $this->line('(pasar --trackdata=XXXX para probar una tarjeta real)');

            return;
        }

        try {
            $res = app(WigosAccountInfoService::class)->consultarPorTrackdata($trackdata);
            $this->info('AccountInfo OK — account='.$res['account_number'].
                ' doc='.$res['documento'].
                ' '.$res['apellido'].' '.$res['nombre'].
                ' level='.$res['level'].'/'.$res['level_code']);
        } catch (Throwable $e) {
            $this->error('AccountInfo FALLO — '.$e->getMessage());
        }
    }
}
