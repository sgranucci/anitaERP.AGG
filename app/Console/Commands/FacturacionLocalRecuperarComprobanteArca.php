<?php

namespace App\Console\Commands;

use App\Models\Seguridad\Usuario;
use App\Models\Ventas\LocalVenta;
use App\Services\Ventas\FacturacionLocal\FacturacionLocalRecuperarComprobanteArcaService;
use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;

class FacturacionLocalRecuperarComprobanteArca extends Command
{
    protected $signature = 'facturacion-local:recuperar-comprobante-arca
                            {--local=1 : local_venta_id}
                            {--numero= : numerocomprobante autorizado en ARCA}
                            {--sku= : SKU del ítem (ej. 63091523)}
                            {--precio= : Precio unitario FINAL con IVA (como el POS Local). Si omitís, usa ImpTotal de ARCA}
                            {--cantidad=1 : Cantidad}
                            {--descuento=0 : % descuento línea}
                            {--combinacion=0 : combinacion_id}
                            {--talle=0 : talle_id}
                            {--cuentacaja= : cuentacaja_id para cobranza (default efectivo del local)}
                            {--monto-cobranza= : Monto cobranza (default = total ARCA)}
                            {--sin-cobranza : No registra cobranza}
                            {--usuario= : usuario_id}
                            {--ejecutar : Persiste (sin esto = dry-run)}';

    protected $description = 'Recupera en ERP una FAC Local ya autorizada en ARCA (hueco por rollback). Dry-run por defecto.';

    public function handle(FacturacionLocalRecuperarComprobanteArcaService $service): int
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            $this->warn('Solo aplica en Calzados Ferli.');

            return self::SUCCESS;
        }

        $numero = (int) $this->option('numero');
        $sku = trim((string) $this->option('sku'));
        $precio = (float) $this->option('precio');
        if ($numero <= 0 || $sku === '' || $precio <= 0) {
            $this->error('Indique --numero= --sku= --precio= (precio final con IVA, como el POS).');

            return self::FAILURE;
        }

        $local = LocalVenta::query()->find((int) $this->option('local'));
        if (! $local) {
            $this->error('Local inexistente.');

            return self::FAILURE;
        }

        $usuarioId = (int) ($this->option('usuario') ?: Usuario::query()->orderBy('id')->value('id') ?? 1);
        if ($usuarioId <= 0 || ! Auth::loginUsingId($usuarioId)) {
            $this->error('No se pudo autenticar usuario.');

            return self::FAILURE;
        }

        $ejecutar = (bool) $this->option('ejecutar');
        if (! $ejecutar) {
            $this->info('Modo dry-run (no escribe). Use --ejecutar para persistir.');
        }

        try {
            $lineas = FacturacionLocalRecuperarComprobanteArcaService::lineaDesdeSku(
                $sku,
                $precio,
                (float) $this->option('cantidad'),
                (float) $this->option('descuento'),
                (int) $this->option('combinacion'),
                (int) $this->option('talle'),
            );

            $medios = [];
            if (! $this->option('sin-cobranza')) {
                $cuentaId = (int) ($this->option('cuentacaja') ?: $local->cuentacaja_efectivo_id);
                if ($cuentaId <= 0) {
                    $this->error('Indique --cuentacaja= o configure efectivo del local.');

                    return self::FAILURE;
                }
                $pv = $local->puntoventaDefault();
                $arcaPreview = $service->consultarArca(
                    (int) ($local->empresa_id ?: $pv?->empresa_id ?: 1),
                    (int) ($pv?->codigo ?? 0),
                    6,
                    $numero,
                );
                $monto = (float) ($this->option('monto-cobranza') ?: $arcaPreview['imp_total']);
                $medios[] = [
                    'cuentacaja_id' => $cuentaId,
                    'moneda_id' => 1,
                    'monto' => $monto,
                ];
            }

            $resultado = $service->recuperar(
                $local,
                $numero,
                $lineas,
                $medios,
                ! $ejecutar,
            );
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->line(json_encode($resultado, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        if (! $ejecutar) {
            $this->warn('Revise el impacto y autorice --ejecutar para persistir.');
        } else {
            $this->info('Recuperación OK: venta_id='.($resultado['venta_id'] ?? '').' CAE='.($resultado['cae'] ?? ''));
        }

        return self::SUCCESS;
    }
}
