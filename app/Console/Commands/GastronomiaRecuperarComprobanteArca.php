<?php

namespace App\Console\Commands;

use App\Models\Seguridad\Usuario;
use App\Services\Ventas\Gastronomia\GastronomiaRecuperarComprobanteArcaService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;

class GastronomiaRecuperarComprobanteArca extends Command
{
    protected $signature = 'gastronomia:recuperar-comprobante-arca
                            {--empresa=1 : empresa_id}
                            {--pv=00003 : Código punto de venta ARCA}
                            {--numero= : numerocomprobante a recuperar}
                            {--cuenta= : cuenta_gastronomia_id referencia (ítems/descuento)}
                            {--venta-referencia= : venta_id alternativa para clonar ítems}
                            {--usuario= : usuario_id para la operación}
                            {--dry-run : Solo validar ARCA y mostrar plan}';

    protected $description = 'Recupera en ERP y Anita un comprobante ya autorizado en ARCA (hueco por rollback/deadlock)';

    public function handle(GastronomiaRecuperarComprobanteArcaService $service): int
    {
        $numero = (int) $this->option('numero');
        if ($numero <= 0) {
            $this->error('Indique --numero=NNNNNN (numerocomprobante faltante en ERP).');

            return self::FAILURE;
        }

        $cuentaId = $this->option('cuenta') !== null ? (int) $this->option('cuenta') : null;
        $ventaRef = $this->option('venta-referencia') !== null ? (int) $this->option('venta-referencia') : null;
        if (($cuentaId === null || $cuentaId <= 0) && ($ventaRef === null || $ventaRef <= 0)) {
            $this->error('Indique --cuenta=ID o --venta-referencia=ID para armar ítems.');

            return self::FAILURE;
        }

        $usuarioId = (int) ($this->option('usuario') ?: Usuario::query()->orderBy('id')->value('id') ?? 1);
        if ($usuarioId <= 0 || ! Auth::loginUsingId($usuarioId)) {
            $this->error('No se pudo autenticar usuario para la operación.');

            return self::FAILURE;
        }

        $empresaId = (int) $this->option('empresa');
        $pv = (string) $this->option('pv');
        $dryRun = (bool) $this->option('dry-run');

        $this->line(sprintf(
            'Recuperación ARCA | empresa %d | PV %s | número %d%s',
            $empresaId,
            $pv,
            $numero,
            $dryRun ? ' [dry-run]' : '',
        ));

        try {
            $resultado = $service->recuperar(
                $empresaId,
                $pv,
                $numero,
                1,
                $cuentaId > 0 ? $cuentaId : null,
                $ventaRef > 0 ? $ventaRef : null,
                $dryRun,
            );
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if ($dryRun) {
            $this->info('Dry-run OK — comprobante autorizado en ARCA, hueco confirmado en ERP.');
            $this->line(json_encode($resultado, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        $this->info('Recuperado: '.($resultado['codigo'] ?? '').' venta_id='.($resultado['venta_id'] ?? ''));
        $this->line('CAE: '.($resultado['cae'] ?? ''));
        $this->line('Total: $'.number_format((float) ($resultado['total'] ?? 0), 2, '.', ''));

        return self::SUCCESS;
    }
}
