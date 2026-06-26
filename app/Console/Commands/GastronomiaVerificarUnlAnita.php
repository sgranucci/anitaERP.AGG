<?php

namespace App\Console\Commands;

use App\Models\Configuracion\Empresa;
use App\Support\Ventas\Gastronomia\GastronomiaAnitaComprobantePkSupport;
use App\Support\Ventas\Gastronomia\GastronomiaAnitaMesCacheSupport;
use App\Support\Ventas\GastronomiaAnitaImportEmpresaSupport;
use Illuminate\Console\Command;

class GastronomiaVerificarUnlAnita extends Command
{
    protected $signature = 'gastronomia:verificar-unl-anita
                            {directorio : Ruta con venta.unl, vengrav.unl y vencae.unl}
                            {--empresa=3 : empresa_id ERP}
                            {--fecha-desde= : Y-m-d para cache Anita (requerido con --usar-cache)}
                            {--fecha-hasta= : Y-m-d para cache Anita}
                            {--usar-cache : Verificar contra cache PK (sin bridge por línea)}';

    protected $description = 'Verifica UNL: duplicados internos y solapamiento con Anita por PK tipo|letra|sucursal|nro';

    public function handle(GastronomiaAnitaMesCacheSupport $cacheSupport): int
    {
        $dir = trim((string) $this->argument('directorio'));
        if (! str_starts_with($dir, '/')) {
            $dir = base_path($dir);
        }

        $empresaId = (int) $this->option('empresa');
        $empresa = Empresa::query()->find($empresaId);
        if (! $empresa) {
            $this->error("Empresa {$empresaId} inexistente.");

            return self::FAILURE;
        }

        $archivos = [
            'venta.unl' => $dir.'/venta.unl',
            'vengrav.unl' => $dir.'/vengrav.unl',
            'vencae.unl' => $dir.'/vencae.unl',
        ];

        foreach ($archivos as $nombre => $ruta) {
            if (! is_file($ruta)) {
                $this->error("Falta {$nombre} en {$dir}");

                return self::FAILURE;
            }
        }

        $ventaPkIndice = null;
        $vengravPkIndice = null;
        $vencaePkIndice = null;

        if ($this->option('usar-cache')) {
            $desde = trim((string) ($this->option('fecha-desde') ?? ''));
            $hasta = trim((string) ($this->option('fecha-hasta') ?? ''));
            if ($desde === '' || $hasta === '') {
                $this->error('Con --usar-cache indicá --fecha-desde y --fecha-hasta del export.');

                return self::FAILURE;
            }

            $cache = $cacheSupport->cargarExportPk($empresaId, $desde, $hasta);
            $ventaPkIndice = GastronomiaAnitaComprobantePkSupport::indexarVenta($cache['venta']);
            $vengravPkIndice = GastronomiaAnitaComprobantePkSupport::indexarVengrav($cache['vengrav']);
            $vencaePkIndice = GastronomiaAnitaComprobantePkSupport::indexarVencae($cache['vencae']);

            $this->line(sprintf(
                'Cache PK ven_empresa=%s: %d venta, %d vengrav, %d vencae',
                GastronomiaAnitaImportEmpresaSupport::codigoEmpresa($empresaId),
                count($cache['venta']),
                count($cache['vengrav']),
                count($cache['vencae']),
            ));
        }

        $this->newLine();
        $this->info('Verificación UNL (PK tipo|letra|sucursal|nro): '.$dir);

        $clavesVentaUnl = [];
        $clavesVencaeUnl = [];
        $errores = 0;

        $errores += $this->verificarArchivo('venta.unl', $archivos['venta.unl'], $ventaPkIndice, $clavesVentaUnl, true);
        $dummy = [];
        $errores += $this->verificarArchivo('vengrav.unl', $archivos['vengrav.unl'], $vengravPkIndice, $dummy, false);
        $errores += $this->verificarArchivo('vencae.unl', $archivos['vencae.unl'], $vencaePkIndice, $clavesVencaeUnl, true);

        $vencaeSinVenta = 0;
        foreach (array_keys($clavesVencaeUnl) as $clave) {
            if (! isset($clavesVentaUnl[$clave])) {
                $vencaeSinVenta++;
            }
        }

        $this->newLine();
        $this->table(['Control', 'Resultado'], [
            ['vencae.unl sin cabecera en venta.unl', (string) $vencaeSinVenta],
            ['Errores totales', (string) $errores],
        ]);

        if ($errores > 0) {
            $this->error('Falló: hay duplicados internos o filas que ya existen en Anita (misma PK).');

            return self::FAILURE;
        }

        $this->info('OK: sin duplicados internos ni solapamiento PK con Anita.');

        return self::SUCCESS;
    }

    /**
     * @param  array<string, true>|null  $indiceAnita
     * @param  array<string, true>  $clavesVentaUnl
     */
    private function verificarArchivo(
        string $nombre,
        string $ruta,
        ?array $indiceAnita,
        array &$clavesAcumuladas,
        bool $acumularClaves,
    ): int {
        $lineas = file($ruta, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $dupInternos = 0;
        $yaAnita = 0;
        $vistos = [];

        foreach ($lineas as $linea) {
            $p = explode('|', $linea);
            if ($nombre === 'venta.unl') {
                $pk = GastronomiaAnitaComprobantePkSupport::claveVenta(
                    (string) ($p[1] ?? ''),
                    (string) ($p[2] ?? ''),
                    GastronomiaAnitaComprobantePkSupport::sucursalEntera((string) ($p[3] ?? '')),
                    (int) ($p[4] ?? 0),
                );
            } elseif ($nombre === 'vengrav.unl') {
                $pk = GastronomiaAnitaComprobantePkSupport::claveVengrav(
                    (string) ($p[0] ?? ''),
                    (string) ($p[1] ?? ''),
                    GastronomiaAnitaComprobantePkSupport::sucursalEntera((string) ($p[2] ?? '')),
                    (int) ($p[3] ?? 0),
                    (string) ($p[4] ?? ''),
                );
            } else {
                $pk = GastronomiaAnitaComprobantePkSupport::claveVencae(
                    (string) ($p[0] ?? ''),
                    (string) ($p[1] ?? ''),
                    GastronomiaAnitaComprobantePkSupport::sucursalEntera((string) ($p[2] ?? '')),
                    (int) ($p[3] ?? 0),
                );
            }

            if ($pk === null) {
                continue;
            }

            if (isset($vistos[$pk])) {
                $dupInternos++;
            } else {
                $vistos[$pk] = true;
            }

            if ($acumularClaves) {
                $clavesAcumuladas[$pk] = true;
            }

            if ($indiceAnita !== null && isset($indiceAnita[$pk])) {
                $yaAnita++;
            }
        }

        $this->table(
            ['Archivo', 'Líneas', 'Dup. internos', 'Ya en Anita (PK)'],
            [[$nombre, (string) count($lineas), (string) $dupInternos, (string) $yaAnita]],
        );

        return $dupInternos + $yaAnita;
    }
}
