<?php

namespace App\Console\Commands;

use App\Models\Configuracion\Empresa;
use App\Models\Seguridad\Usuario;
use App\Repositories\Compras\ProveedorRepositoryInterface;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Support\Compras\ProveedorAnitaEsquemaSupport;
use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;

/**
 * Import de proveedores exclusivo INTERFORMING (esquema promae reducido).
 */
class SincronizarProveedorDesdeAnitaInterforming extends Command
{
    protected $signature = 'proveedor:sincronizar-anita-interforming
                            {--codigo= : Importar/actualizar un proveedor por código Anita (prom_proveedor)}
                            {--empresa= : Código o ID ERP de empresa (altas con filtro empresa)}
                            {--path= : Path Anita (default ANITA_BDD_PATH)}
                            {--usuario= : ID usuario para altas (default: primer usuario)}
                            {--dry-run : Informe sin escribir en el ERP}';

    protected $description = 'INTERFORMING: importa proveedores desde Anita con esquema promae propio (sin columnas AGG). Preferir tras compras:sincronizar-maestros-anita-interforming.';

    public function handle(
        ProveedorRepositoryInterface $proveedorRepository,
        EmpresaRepositoryInterface $empresaRepository,
    ): int {
        if (! EntornoEmpresaSupport::esInterforming()) {
            $this->error('Este comando solo aplica a EMPRESA=INTERFORMING.');

            return self::FAILURE;
        }

        if (ProveedorAnitaEsquemaSupport::variante() !== ProveedorAnitaEsquemaSupport::VARIANTE_INTERFORMING) {
            $this->error('Esquema proveedor no es Interforming (revisar EMPRESA / config).');

            return self::FAILURE;
        }

        $usuarioId = $this->option('usuario');
        $usuarioId = ($usuarioId !== null && $usuarioId !== '')
            ? (int) $usuarioId
            : (int) (Usuario::query()->orderBy('id')->value('id') ?? 1);

        if ($usuarioId <= 0 || ! Auth::loginUsingId($usuarioId)) {
            $this->error('Usuario inválido.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $codigo = $this->option('codigo');
        $codigo = is_string($codigo) ? trim($codigo) : '';
        $pathSistema = $this->option('path');
        $pathSistema = is_string($pathSistema) ? trim($pathSistema) : '';
        $pathSistema = $pathSistema !== '' ? $pathSistema : null;

        $empresaId = $this->resolverEmpresaId($empresaRepository);
        if ($empresaId === false) {
            return self::FAILURE;
        }

        $this->comment('Esquema: Interforming (promae sin columnas AGG; sin promadic/proexcl/propago).');

        try {
            if ($codigo !== '') {
                if ($dryRun) {
                    $preview = $proveedorRepository->previewSincronizacionDesdeAnita($codigo, $pathSistema, $empresaId);
                    if ($preview === null) {
                        $this->warn("Proveedor Anita {$codigo} no encontrado.");

                        return self::FAILURE;
                    }
                    $this->table(['Campo', 'Valor'], [
                        ['Código ERP', $preview['codigo']],
                        ['Código Anita', $preview['codigo_anita']],
                        ['Nombre Anita', $preview['nombre_anita']],
                        ['Acción', $preview['accion']],
                    ]);

                    return self::SUCCESS;
                }

                $existe = $proveedorRepository->existeProveedorPorCodigo($codigo, $empresaId);
                $this->info(($existe ? 'Actualizando' : 'Importando')." proveedor Anita {$codigo}…");
                $resultado = $proveedorRepository->traerRegistroDeAnita($codigo, null, $empresaId, $pathSistema);
                if ($resultado === null) {
                    $this->warn('Proveedor no encontrado en Anita o sin datos.');

                    return self::FAILURE;
                }
                $this->info($resultado === 'insertado' ? 'Proveedor importado.' : 'Proveedor actualizado.');

                return self::SUCCESS;
            }

            $this->info($dryRun
                ? 'Simulación: sync masivo proveedores Interforming…'
                : 'Sincronizando proveedores Interforming (puede tardar)…');

            $stats = $proveedorRepository->resincronizarDesdeAnita($dryRun, $empresaId, $pathSistema);

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
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info($dryRun ? 'Simulación finalizada.' : 'Sincronización de proveedores finalizada.');

        return self::SUCCESS;
    }

    /**
     * @return int|null|false
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

        $this->error("Empresa no encontrada para --empresa={$raw}.");

        return false;
    }
}
