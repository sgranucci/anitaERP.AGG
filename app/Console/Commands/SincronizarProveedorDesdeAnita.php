<?php

namespace App\Console\Commands;

use App\Models\Seguridad\Usuario;
use App\Repositories\Compras\ProveedorRepositoryInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;

class SincronizarProveedorDesdeAnita extends Command
{
    protected $signature = 'proveedor:sincronizar-anita
                            {--codigo= : Importar/actualizar un proveedor por código Anita (prom_proveedor)}
                            {--usuario= : ID usuario para usuario_id en altas (default: primer usuario)}
                            {--dry-run : Informe sin escribir en el ERP}
                            {--informe-solo-erp : Solo listar códigos presentes en ERP y ausentes en Anita}';

    protected $description = 'Importa/actualiza proveedores desde Anita (promae, proexcl, propago). Anita es la fuente de verdad.';

    public function handle(ProveedorRepositoryInterface $proveedorRepository): int
    {
        $usuarioId = $this->option('usuario');
        $usuarioId = ($usuarioId !== null && $usuarioId !== '')
            ? (int) $usuarioId
            : (int) (Usuario::query()->orderBy('id')->value('id') ?? 1);

        if ($usuarioId <= 0) {
            $this->error('usuario inválido.');

            return self::FAILURE;
        }

        if (! Auth::loginUsingId($usuarioId)) {
            $this->error("No existe usuario id {$usuarioId}.");

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $soloErp = (bool) $this->option('informe-solo-erp');
        $codigo = $this->option('codigo');
        $codigo = is_string($codigo) ? trim($codigo) : '';

        try {
            if ($soloErp) {
                $stats = $proveedorRepository->resincronizarDesdeAnita(true);
                $this->info('Proveedores en ERP sin registro en Anita (promae): '.count($stats['solo_en_erp']));
                foreach ($stats['solo_en_erp'] as $codigoErp) {
                    $this->line("  - {$codigoErp}");
                }

                return self::SUCCESS;
            }

            if ($codigo !== '') {
                if ($dryRun) {
                    $preview = $proveedorRepository->previewSincronizacionDesdeAnita($codigo);
                    if ($preview === null) {
                        $this->warn("Proveedor Anita {$codigo} no encontrado.");

                        return self::FAILURE;
                    }
                    $this->mostrarPreview($preview);

                    return self::SUCCESS;
                }

                $existe = $proveedorRepository->existeProveedorPorCodigo($codigo);
                $this->info(($existe ? 'Actualizando' : 'Importando')." proveedor Anita {$codigo}…");
                $resultado = $proveedorRepository->traerRegistroDeAnita($codigo);
                if ($resultado === null) {
                    $this->warn('Proveedor no encontrado en Anita o sin datos.');

                    return self::FAILURE;
                }
                $this->info($resultado === 'insertado' ? 'Proveedor importado.' : 'Proveedor actualizado.');

                return self::SUCCESS;
            }

            $this->info($dryRun
                ? 'Simulación: sincronización de proveedores desde Anita (sin escribir en ERP)…'
                : 'Sincronizando proveedores desde Anita. Puede tardar varios minutos…');

            $stats = $proveedorRepository->resincronizarDesdeAnita($dryRun);

            $this->table(
                ['Métrica', 'Cantidad'],
                [
                    ['Insertados'.($dryRun ? ' (simulado)' : ''), $stats['insertados']],
                    ['Actualizados'.($dryRun ? ' (simulado)' : ''), $stats['actualizados']],
                    ['Omitidos / no encontrados', $stats['omitidos']],
                    ['Errores', $stats['errores']],
                    ['Solo en ERP (no en Anita)', count($stats['solo_en_erp'])],
                ]
            );

            if ($stats['solo_en_erp'] !== []) {
                $this->newLine();
                $this->warn('Códigos en ERP ausentes en Anita (no se modifican):');
                foreach (array_slice($stats['solo_en_erp'], 0, 30) as $codigoErp) {
                    $this->line("  - {$codigoErp}");
                }
                if (count($stats['solo_en_erp']) > 30) {
                    $this->line('  … y '.(count($stats['solo_en_erp']) - 30).' más.');
                }
            }
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info($dryRun ? 'Simulación finalizada.' : 'Sincronización de proveedores finalizada.');

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $preview
     */
    private function mostrarPreview(array $preview): void
    {
        $this->table(
            ['Campo', 'Valor'],
            [
                ['Código ERP', $preview['codigo']],
                ['Código Anita', $preview['codigo_anita']],
                ['Nombre Anita', $preview['nombre_anita']],
                ['Acción', $preview['accion']],
                ['Exclusiones a importar', $preview['exclusiones_anita']],
                ['Formas de pago Anita', $preview['formapagos_anita']],
                ['Filas proexcl', $preview['proexcl_filas']],
                ['proexcl con tipo inválido (sin inferencia)', $preview['proexcl_tipo_invalido']],
            ]
        );
    }
}
