<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Caja\TesoreriaCrownAsignacion;
use App\Models\Caja\TesoreriaCrownComprobante;
use App\Models\Caja\TesoreriaCrownJornada;
use App\Models\Seguridad\Usuario;
use App\Support\Caja\TesoreriaCrownAcceso;
use HeadlessChromium\BrowserFactory;
use HeadlessChromium\Cookies\Cookie;
use Illuminate\Console\Command;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Session;

/**
 * Capturas reales (Chromium) para el manual Tesorería Crown.
 * No pide contraseña: autentica por sesión Laravel y setea la cookie en el browser.
 */
class CapturarPantallasManualTesoreriaCrown extends Command
{
    protected $signature = 'manual:capturar-tesoreria-crown
                            {--usuario=sergio : Usuario ERP}
                            {--rol= : ID de rol (opcional)}
                            {--solo= : Claves de captura separadas por coma}
                            {--base= : URL base (default APP_URL)}';

    protected $description = 'Captura pantallas reales de Tesorería Crown con Chromium para el manual';

    public function handle(): int
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();

        $outDir = public_path('docs/manual-tesoreria-crown/img');
        File::ensureDirectoryExists($outDir);

        $usuarioLogin = (string) $this->option('usuario');
        $user = Usuario::where('usuario', $usuarioLogin)->first();
        if (! $user) {
            $this->error("Usuario no encontrado: {$usuarioLogin}");

            return self::FAILURE;
        }

        $base = rtrim((string) ($this->option('base') ?: (
            rtrim((string) config('app.url'), '/').(env('APP_CARPETA', '') ?: '')
        )), '/');

        $jornada = TesoreriaCrownJornada::query()
            ->where('estado', TesoreriaCrownJornada::ABIERTA)
            ->orderByDesc('fecha')
            ->first()
            ?? TesoreriaCrownJornada::query()->orderByDesc('fecha')->first();

        if (! $jornada) {
            $this->error('No hay jornadas Crown para capturar.');

            return self::FAILURE;
        }

        $salaId = (int) $jornada->sala_id;
        $jid = (int) $jornada->id;

        $hab = TesoreriaCrownComprobante::query()
            ->where('tipo', 'H')
            ->orderByDesc('id')
            ->first();
        $ren = TesoreriaCrownComprobante::query()
            ->where('tipo', 'F')
            ->orderByDesc('id')
            ->first();
        $renExterna = TesoreriaCrownComprobante::query()
            ->where('tipo', 'F')
            ->whereHas('asignacion', fn ($q) => $q->whereIn('tipo_tarea', ['G', 'H']))
            ->orderByDesc('id')
            ->first() ?? $ren;

        $asigCierre = TesoreriaCrownAsignacion::query()
            ->whereHas('cierres')
            ->orderByDesc('id')
            ->value('id')
            ?? TesoreriaCrownAsignacion::query()->orderByDesc('id')->value('id');

        $pantallas = [
            'jornada-listado' => '/caja/tesoreria-crown/jornada',
            'jornada-escritorio' => "/caja/tesoreria-crown/jornada/{$jid}?panel=cajeros",
            'habilitacion-firma' => $hab ? '/caja/tesoreria-crown/movimiento/'.$hab->id : null,
            'rendicion-firma' => $ren ? '/caja/tesoreria-crown/movimiento/'.$ren->id : null,
            'rendicion-externa' => $renExterna ? '/caja/tesoreria-crown/movimiento/'.$renExterna->id : null,
            'cierre-caja' => $asigCierre ? '/caja/tesoreria-crown/jornada/cierre/'.$asigCierre : null,
            'mesas-propina' => "/caja/tesoreria-crown/jornada/{$jid}?panel=mesas",
            'slots-softcount' => "/caja/tesoreria-crown/jornada/{$jid}?panel=slots",
            'boveda-stock' => "/caja/tesoreria-crown/boveda?sala_id={$salaId}&jornada_id={$jid}",
            'poder-publico' => "/caja/tesoreria-crown/jornada/{$jid}?panel=poder",
            'cotizacion' => '/caja/tesoreria-crown/cotizacion',
            'kardex-valores' => "/caja/tesoreria-crown/kardex-valores?sala_id={$salaId}&jornada_id={$jid}&consultar=1",
            'anexo1' => "/caja/tesoreria-crown/jornada/{$jid}?panel=anexo",
            'anexo8' => "/caja/tesoreria-crown/jornada/{$jid}?panel=anexo8",
            'flash-crown' => '/caja/flash-crown',
        ];

        $solo = array_values(array_filter(array_map('trim', explode(',', (string) $this->option('solo')))));
        if ($solo !== []) {
            $pantallas = array_intersect_key($pantallas, array_flip($solo));
        }
        $pantallas = array_filter($pantallas, static fn ($path) => is_string($path) && $path !== '');

        if ($pantallas === []) {
            $this->error('No hay pantallas para capturar.');

            return self::FAILURE;
        }

        $this->info("Base: {$base}");
        $this->info("Jornada #{$jid} sala={$salaId} fecha=".$jornada->fecha->format('Y-m-d'));
        $this->info("Usuario: {$usuarioLogin}");
        $this->info('Destino: '.$outDir);

        $cookiePayload = $this->crearCookieSesion($user);
        $chrome = $this->resolverChromeBinary();

        try {
            $factory = new BrowserFactory($chrome);
            $browser = $factory->createBrowser([
                'headless' => true,
                'noSandbox' => true,
                'windowSize' => [1280, 900],
                'customFlags' => [
                    '--disable-gpu',
                    '--disable-dev-shm-usage',
                    '--hide-scrollbars',
                ],
            ]);
            $page = $browser->createPage();
            $page->setViewport(1280, 900)->await();

            // Origen para domain de cookie
            $page->navigate($base.'/seguridad/login')->waitForNavigation();
            usleep(400000);
            $page->setCookies([
                Cookie::create($cookiePayload['name'], $cookiePayload['value'], [
                    'domain' => parse_url($base, PHP_URL_HOST) ?: '192.168.1.3',
                    'path' => '/',
                    'httpOnly' => true,
                    'secure' => false,
                    'sameSite' => 'Lax',
                ]),
            ])->await();

            foreach ($pantallas as $nombre => $path) {
                $url = $base.$path;
                $destino = $outDir.'/'.$nombre.'.png';
                $this->line('→ '.$nombre);
                try {
                    $page->navigate($url)->waitForNavigation();
                    usleep(1400000);
                    // Cerrar modales/toasts que tapen; en rendición externa bajar al bloque fiscal/PXSol
                    try {
                        $js = <<<'JS'
document.querySelectorAll(".modal.show,.toast.show").forEach(el=>el.remove());
document.body.classList.remove("modal-open");
document.querySelectorAll(".modal-backdrop").forEach(el=>el.remove());
JS;
                        if ($nombre === 'rendicion-externa') {
                            $js .= <<<'JS'
const alvo = document.querySelector('[id*="pxsol"],[class*="pxsol"],[id*="cierre-fiscal"],[class*="cierre-fiscal"],.tc-rendicion-externa,label');
const labels = Array.from(document.querySelectorAll('label,h3,h4,legend,.card-title'));
const hit = labels.find(el => /PXSol|cierres? fiscales|Aplicar a la rendici|cuenta corriente|notas de cr/i.test(el.textContent||''));
(hit||alvo)?.scrollIntoView({block:'center'});
JS;
                        }
                        if (in_array($nombre, ['anexo1', 'anexo8', 'poder-publico', 'kardex-valores'], true)) {
                            $js .= 'window.scrollTo(0, 180);';
                        }
                        $page->evaluate($js);
                    } catch (\Throwable) {
                    }
                    usleep(350000);
                    $page->screenshot(['format' => 'png', 'captureBeyondViewport' => false])->saveToFile($destino);
                    $this->info('  ✓ '.$nombre.'.png ('.filesize($destino).' bytes)');
                } catch (\Throwable $e) {
                    $this->warn('  ✗ '.$nombre.': '.$e->getMessage());
                }
            }

            $browser->close();
        } catch (\Throwable $e) {
            $this->error('Chromium: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info('Listo. Regenerar: php docs/manual-tesoreria-crown/generar.php');
        $this->comment('circuito-diario.png se conserva como mockup de flujo (no se pisa).');

        return self::SUCCESS;
    }

    /**
     * @return array{name: string, value: string}
     */
    private function crearCookieSesion(Usuario $user): array
    {
        Auth::logout();
        Session::flush();

        $user->loadMissing(['centrocostos', 'sectorLegajocompra']);
        Auth::login($user);

        $roles = $user->roles()->get();
        $empresas = $user->usuario_empresas()->get();
        $user->setSession($roles->toArray(), $empresas->toArray());

        $rolId = $this->option('rol') ?: $roles->first()?->id;
        if ($rolId) {
            $rol = $roles->firstWhere('id', (int) $rolId) ?? $roles->first();
            if ($rol) {
                Session::put(['rol_id' => $rol->id, 'rol_nombre' => $rol->nombre]);
            }
        }

        Session::save();

        $name = (string) config('session.cookie');
        $sid = Session::getId();
        $encrypter = app('encrypter');
        $prefixed = CookieValuePrefix::create($name, $encrypter->getKey()).$sid;
        $value = $encrypter->encrypt($prefixed, false);

        return ['name' => $name, 'value' => $value];
    }

    private function resolverChromeBinary(): string
    {
        foreach (['/usr/bin/chromium', '/usr/bin/chromium-browser', '/usr/bin/google-chrome'] as $bin) {
            if (is_executable($bin)) {
                return $bin;
            }
        }

        return 'chromium';
    }
}
