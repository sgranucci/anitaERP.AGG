<?php

namespace App\Console\Commands;

use App\Services\Ventas\Gastronomia\WigosAccountInfoService;
use App\Services\Ventas\Gastronomia\WigosCanjePremioService;
use App\Support\Wigos\WigosConfigResolver;
use App\Support\Wigos\WigosSqlServerOpenSsl;
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
                            {--empresa=0 : empresa_id Anita (p. ej. 2 = Kandiko Wilde)}
                            {--cupon= : Cupón real para invocar spVoucherGiftData (opcional)}
                            {--trackdata= : Trackdata de tarjeta para probar AccountInfoJSON (opcional)}';

    protected $description = 'Prueba conexión SQL Server Wigos y servicio HTTP AccountInfoJSON';

    public function handle(): int
    {
        $empresaId = max(0, (int) $this->option('empresa'));

        $this->probarSqlServer($empresaId);
        $this->probarAccountInfo($empresaId);

        return self::SUCCESS;
    }

    private function probarSqlServer(int $empresaId): void
    {
        $this->line('');
        $this->line('=== SQL Server (canje premio Wigos) ===');
        if ($empresaId > 0) {
            $this->info('Empresa Anita: '.$empresaId);
        }

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

        $primario = WigosConfigResolver::currWigos($empresaId);
        $secundario = $primario === 'A' ? 'B' : 'A';
        $preferido = WigosConfigResolver::currWigosConfigurado($empresaId);
        if ($primario !== $preferido) {
            $this->info("Alias lectura (monitor): {$primario} — preferido config: {$preferido}");
        } else {
            $this->info("Alias lectura / preferido config: {$primario}");
        }

        foreach ([$primario, $secundario] as $alias) {
            $this->probarConexionAlias($alias, $empresaId);
        }

        $cupon = trim((string) $this->option('cupon'));
        if ($cupon !== '') {
            $this->line('');
            $this->info('Invocando spVoucherGiftData con cupón '.$cupon.' …');

            try {
                $filas = app(WigosCanjePremioService::class)->consultarPorCodigoBarras($cupon, $empresaId);
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

    private function probarConexionAlias(string $alias, int $empresaId): void
    {
        $cfg = WigosConfigResolver::conexion($alias, $empresaId);
        $host = trim((string) ($cfg['host'] ?? ''));
        if ($host === '') {
            $this->warn("[$alias] sin host configurado — se omite.");

            return;
        }

        $endpoint = $host.','.($cfg['port'] ?? '1433');
        $this->info("[$alias] probando ".$endpoint.' / '.($cfg['database'] ?? ''));

        try {
            $res = WigosSqlServerProcess::consultarVersion($alias, $empresaId);
            $linea = trim(preg_split('/[\r\n]/', $res['version'])[0] ?? '');
            $this->info("[$alias] OK — ".$linea);
        } catch (RuntimeException $e) {
            $this->error("[$alias] FALLO — ".$e->getMessage());
        }
    }

    private function probarAccountInfo(int $empresaId): void
    {
        $this->line('');
        $this->line('=== HTTP AccountInfoJSON (canje fidelidad) ===');
        if ($empresaId > 0) {
            $this->info('Empresa Anita: '.$empresaId);
        }

        if (! config('wigos.account_info_habilitado', false)) {
            $this->warn('WIGOS_ACCOUNT_INFO_HABILITADO=false — consulta de tarjeta deshabilitada.');

            return;
        }

        $urls = WigosConfigResolver::accountInfoUrls($empresaId);
        $this->info('URLs: '.($urls !== [] ? implode(' → ', $urls) : '(sin configurar)'));
        $this->info('Timeout: '.config('wigos.account_info_timeout').'s');

        $trackdata = trim((string) $this->option('trackdata'));
        if ($trackdata === '') {
            $this->line('(pasar --trackdata=XXXX para probar una tarjeta real)');

            return;
        }

        try {
            $res = app(WigosAccountInfoService::class)->consultarPorTrackdata($trackdata, $empresaId);
            $this->info('AccountInfo OK — account='.$res['account_number'].
                ' doc='.$res['documento'].
                ' '.$res['apellido'].' '.$res['nombre'].
                ' level='.$res['level'].'/'.$res['level_code']);
        } catch (Throwable $e) {
            $this->error('AccountInfo FALLO — '.$e->getMessage());
        }
    }
}
