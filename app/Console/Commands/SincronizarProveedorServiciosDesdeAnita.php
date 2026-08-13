<?php

namespace App\Console\Commands;

use App\ApiAnita;
use App\Models\Compras\Proveedor;
use App\Models\Compras\Proveedor_Servicio;
use App\Models\Configuracion\Empresa;
use App\Repositories\Compras\ProveedorRepositoryInterface;
use App\Support\Compras\ProveedorExclusionAnitaSupport;
use App\Traits\AnitaBridgeEscritura;
use Illuminate\Console\Command;

/**
 * Importa medidores/servicios desde Anita (`servicios`) hacia proveedor_servicio.
 * No trae mails (serv_mail_*).
 */
class SincronizarProveedorServiciosDesdeAnita extends Command
{
    use AnitaBridgeEscritura;

    protected $signature = 'proveedor:sincronizar-servicios-anita
                            {--codigo= : Solo un proveedor (código Anita/ERP)}
                            {--dry-run : Informe sin escribir}';

    protected $description = 'Importa servicios/medidores desde Anita (tabla servicios) a proveedor_servicio';

    private ?string $anitaPathSistema = null;

    public function handle(ProveedorRepositoryInterface $proveedorRepository): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $codigoOpt = trim((string) $this->option('codigo'));

        $apiAnita = new ApiAnita();
        $where = '';
        if ($codigoOpt !== '') {
            $key = ProveedorExclusionAnitaSupport::codigoAnitaParaBridge($codigoOpt);
            $where = " WHERE serv_proveedor = '{$key}' ";
        }

        $filas = ApiAnita::decodificarListaFilas($apiAnita->apiCall([
            'acc' => 'list',
            'tabla' => 'servicios',
            'sistema' => 'compras',
            'campos' => 'serv_empresa,serv_proveedor,serv_cliente,serv_detalle',
            'whereArmado' => $where,
        ]));

        $this->info('Filas Anita servicios: '.count($filas));

        $porProveedor = [];
        foreach ($filas as $fila) {
            $codigoErp = ProveedorExclusionAnitaSupport::codigoErpDesdeAnita((string) ($fila->serv_proveedor ?? ''));
            $porProveedor[$codigoErp][] = $fila;
        }

        $insertados = 0;
        $proveedores = 0;
        $sinProveedor = 0;

        foreach ($porProveedor as $codigoErp => $filasProv) {
            $proveedor = $proveedorRepository->findPorCodigo($codigoErp);
            if (! $proveedor) {
                $sinProveedor++;
                $this->warn("Sin proveedor ERP para código {$codigoErp} (".count($filasProv).' servicios Anita)');
                continue;
            }

            $proveedores++;
            if ($dryRun) {
                $this->line("  [dry-run] {$codigoErp} → proveedor_id={$proveedor->id} filas=".count($filasProv));
                continue;
            }

            Proveedor_Servicio::query()->where('proveedor_id', $proveedor->id)->delete();

            $vistos = [];
            foreach ($filasProv as $fila) {
                $cliente = trim((string) ($fila->serv_cliente ?? ''));
                if ($cliente === '') {
                    continue;
                }
                $empresaId = $this->empresaIdDesdeCodigoAnita($fila->serv_empresa ?? null);
                if ($proveedor->empresa_id && $empresaId && (int) $proveedor->empresa_id !== (int) $empresaId) {
                    continue;
                }
                $clave = ($empresaId ?? 0).'|'.mb_strtolower($cliente);
                if (isset($vistos[$clave])) {
                    continue;
                }
                $vistos[$clave] = true;

                Proveedor_Servicio::query()->create([
                    'proveedor_id' => $proveedor->id,
                    'empresa_id' => $empresaId ?? $proveedor->empresa_id,
                    'cliente' => mb_substr($cliente, 0, 255),
                    'detalle' => mb_substr(trim((string) ($fila->serv_detalle ?? '')), 0, 255) ?: null,
                ]);
                $insertados++;
            }
        }

        $this->info("Proveedores tocados: {$proveedores}; servicios grabados: {$insertados}; códigos Anita sin ERP: {$sinProveedor}");
        if ($dryRun) {
            $this->comment('Dry-run: no se escribió en el ERP.');
        }

        return self::SUCCESS;
    }

    private function empresaIdDesdeCodigoAnita($codigoAnitaEmpresa): ?int
    {
        $codigo = trim((string) $codigoAnitaEmpresa);
        if ($codigo === '' || $codigo === '0') {
            return null;
        }

        $id = Empresa::query()->where('codigo', $codigo)->value('id');
        if ($id) {
            return (int) $id;
        }

        if (ctype_digit($codigo)) {
            $id = Empresa::query()->whereKey((int) $codigo)->value('id');

            return $id ? (int) $id : null;
        }

        return null;
    }

    protected function anitaBridgeLogEvento(): string
    {
        return 'proveedor.servicios.anita_bridge.fallo';
    }
}
