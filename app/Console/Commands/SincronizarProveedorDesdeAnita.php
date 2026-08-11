<?php

namespace App\Console\Commands;

use App\Models\Configuracion\Empresa;
use App\Models\Seguridad\Usuario;
use App\Repositories\Compras\ProveedorRepositoryInterface;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;

class SincronizarProveedorDesdeAnita extends Command
{
    protected $signature = 'proveedor:sincronizar-anita
                            {--codigo= : Importar/actualizar un proveedor por código Anita (prom_proveedor)}
                            {--empresa= : Código o ID ERP de empresa (obligatorio en altas si PROVEEDOR_FILTRO_EMPRESA)}
                            {--usuario= : ID usuario para usuario_id en altas (default: primer usuario)}
                            {--dry-run : Informe sin escribir en el ERP}
                            {--informe-solo-erp : Solo listar códigos presentes en ERP y ausentes en Anita}';

    protected $description = 'Importa/actualiza proveedores desde Anita (promae, proexcl, propago). Anita es la fuente de verdad.';

    public function handle(
        ProveedorRepositoryInterface $proveedorRepository,
        EmpresaRepositoryInterface $empresaRepository,
    ): int {
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
            $empresaId = $this->resolverEmpresaId($empresaRepository);
            if ($empresaId === false) {
                return self::FAILURE;
            }

            if ($soloErp) {
                $stats = $proveedorRepository->resincronizarDesdeAnita(true, $empresaId);
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
                    $this->mostrarPreview($preview, $empresaId);

                    return self::SUCCESS;
                }

                if (config('proveedor.filtro_empresa')
                    && ($empresaId === null || $empresaId <= 0)
                    && ! $proveedorRepository->existeProveedorPorCodigo($codigo)) {
                    $this->error('Con PROVEEDOR_FILTRO_EMPRESA activo las altas requieren --empresa= (código o id ERP).');

                    return self::FAILURE;
                }

                $existe = $proveedorRepository->existeProveedorPorCodigo($codigo);
                $this->info(($existe ? 'Actualizando' : 'Importando')." proveedor Anita {$codigo}…");
                $resultado = $proveedorRepository->traerRegistroDeAnita($codigo, null, $empresaId);
                if ($resultado === null) {
                    $this->warn('Proveedor no encontrado en Anita o sin datos.');

                    return self::FAILURE;
                }
                $this->info($resultado === 'insertado' ? 'Proveedor importado.' : 'Proveedor actualizado.');

                return self::SUCCESS;
            }

            if (config('proveedor.filtro_empresa') && ($empresaId === null || $empresaId <= 0) && ! $dryRun) {
                $this->error('Con PROVEEDOR_FILTRO_EMPRESA activo el import masivo requiere --empresa= (código o id ERP).');

                return self::FAILURE;
            }

            $this->info($dryRun
                ? 'Simulación: sincronización de proveedores desde Anita (sin escribir en ERP)…'
                : 'Sincronizando proveedores desde Anita. Puede tardar varios minutos…');
            if ($empresaId) {
                $this->info("Empresa asignada en altas/updates: {$empresaId}");
            }

            $stats = $proveedorRepository->resincronizarDesdeAnita($dryRun, $empresaId);

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
     * @return int|null|false  false = error de resolución
     */
    private function resolverEmpresaId(EmpresaRepositoryInterface $empresaRepository): int|null|false
    {
        $raw = $this->option('empresa');
        $raw = is_string($raw) ? trim($raw) : '';
        if ($raw === '') {
            return null;
        }

        if (ctype_digit($raw)) {
            $porId = Empresa::query()->find((int) $raw);
            if ($porId) {
                return (int) $porId->id;
            }
        }

        $porCodigo = $empresaRepository->findPorCodigo($raw);
        if ($porCodigo) {
            return (int) $porCodigo->id;
        }

        $this->error("Empresa no encontrada para --empresa={$raw} (probar id o código ERP).");

        return false;
    }

    /**
     * @param  array<string, mixed>  $preview
     */
    private function mostrarPreview(array $preview, ?int $empresaId): void
    {
        $rows = [
            ['Código ERP', $preview['codigo']],
            ['Código Anita', $preview['codigo_anita']],
            ['Nombre Anita', $preview['nombre_anita']],
            ['Acción', $preview['accion']],
            ['Exclusiones a importar', $preview['exclusiones_anita']],
            ['Formas de pago Anita', $preview['formapagos_anita']],
            ['Filas proexcl', $preview['proexcl_filas']],
            ['proexcl con tipo inválido (sin inferencia)', $preview['proexcl_tipo_invalido']],
        ];
        if ($empresaId) {
            $rows[] = ['empresa_id a asignar', (string) $empresaId];
        }

        $this->table(['Campo', 'Valor'], $rows);
    }
}
