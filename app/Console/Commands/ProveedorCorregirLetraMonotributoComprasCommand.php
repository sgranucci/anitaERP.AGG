<?php

namespace App\Console\Commands;

use App\ApiAnita;
use App\Models\Compras\Proveedor;
use App\Support\Compras\ProveedorExclusionAnitaSupport;
use App\Support\Configuracion\CondicionivaLetraComprasSupport;
use Illuminate\Console\Command;

/**
 * Compras: proveedores monotributo deben tener prom_letra = C en Anita.
 * Ventas sigue con condicioniva.letra = A (no se toca el maestro ERP).
 */
class ProveedorCorregirLetraMonotributoComprasCommand extends Command
{
    protected $signature = 'proveedor:corregir-letra-monotributo-compras
                            {--dry-run : Solo informe (default si no hay --ejecutar)}
                            {--ejecutar : Actualiza solo prom_letra=C en Anita (promae)}
                            {--path= : Path Anita override (ej. /usr2/ferli)}';

    protected $description = 'Pone letra C en Anita (prom_letra) a proveedores monotributo del ERP. No cambia condicioniva de ventas.';

    public function handle(): int
    {
        $ejecutar = (bool) $this->option('ejecutar');
        $dryRun = ! $ejecutar || (bool) $this->option('dry-run');
        if ($ejecutar && (bool) $this->option('dry-run')) {
            $this->error('No combine --dry-run con --ejecutar.');

            return self::FAILURE;
        }

        $monoId = CondicionivaLetraComprasSupport::condicionivaMonotributoId();
        $proveedores = Proveedor::query()
            ->where('condicioniva_id', $monoId)
            ->orderBy('codigo')
            ->get(['id', 'codigo', 'nombre', 'condicioniva_id']);

        $this->info('ERP proveedores monotributo (condicioniva_id='.$monoId.'): '.$proveedores->count());
        $this->line('Regla compras: letra C. Maestro condicioniva.letra ventas permanece A.');

        if ($proveedores->isEmpty()) {
            return self::SUCCESS;
        }

        $api = new ApiAnita;
        $path = trim((string) ($this->option('path') ?? ''));
        $aCorregir = 0;
        $yaOk = 0;
        $sinAnita = 0;
        $errores = 0;
        $muestra = [];

        foreach ($proveedores as $proveedor) {
            $codigoAnita = ProveedorExclusionAnitaSupport::codigoAnitaParaBridge((string) $proveedor->codigo);
            $fila = $this->leerPromae($api, $codigoAnita, $path);
            if ($fila === null) {
                $sinAnita++;
                if (count($muestra) < 8) {
                    $muestra[] = [$proveedor->codigo, $proveedor->nombre, '—', 'sin promae'];
                }
                continue;
            }

            $letraActual = strtoupper(substr(trim((string) ($fila->prom_letra ?? '')), 0, 1));
            if ($letraActual === 'C') {
                $yaOk++;
                continue;
            }

            $aCorregir++;
            if (count($muestra) < 15) {
                $muestra[] = [$proveedor->codigo, mb_substr((string) $proveedor->nombre, 0, 40), $letraActual ?: '(vacía)', '→ C'];
            }

            if ($dryRun) {
                continue;
            }

            try {
                $this->actualizarLetraAnita($api, $codigoAnita, $path);
            } catch (\Throwable $e) {
                $errores++;
                $this->warn('Error '.$proveedor->codigo.': '.$e->getMessage());
            }
        }

        if ($muestra !== []) {
            $this->table(['Código', 'Nombre', 'Letra Anita', 'Acción'], $muestra);
        }

        $this->table(
            ['Métrica', 'Cantidad'],
            [
                ['Ya tenían C', $yaOk],
                ['A corregir (≠ C)', $aCorregir],
                ['Sin fila promae Anita', $sinAnita],
                ['Errores al escribir', $errores],
            ]
        );

        if ($dryRun) {
            $this->comment('Dry-run: no se escribió Anita. Para persistir: --ejecutar');
        } else {
            $this->info('Actualización Anita terminada (solo prom_letra=C).');
        }

        return $errores > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function leerPromae(ApiAnita $api, string $codigoAnita, string $path): ?object
    {
        $data = [
            'acc' => 'list',
            'tabla' => 'promae',
            'sistema' => 'compras',
            'campos' => 'prom_proveedor,prom_nombre,prom_cond_iva,prom_letra',
            'whereArmado' => " WHERE prom_proveedor = '".addslashes($codigoAnita)."' ",
        ];
        if ($path !== '') {
            $data['path_sistema'] = rtrim($path, '/');
        }

        $raw = json_decode($api->apiCall($data));
        if (is_array($raw) && isset($raw[0]) && is_object($raw[0])) {
            return $raw[0];
        }
        if (is_object($raw) && isset($raw->prom_proveedor)) {
            return $raw;
        }

        return null;
    }

    private function actualizarLetraAnita(ApiAnita $api, string $codigoAnita, string $path): void
    {
        $data = [
            'acc' => 'update',
            'tabla' => 'promae',
            'sistema' => 'compras',
            'valores' => "prom_letra = 'C'",
            'whereArmado' => " WHERE prom_proveedor = '".addslashes($codigoAnita)."' ",
        ];
        if ($path !== '') {
            $data['path_sistema'] = rtrim($path, '/');
        }

        $api->apiCallEscritura($data, 'proveedor.corregir_letra_monotributo');
    }
}
