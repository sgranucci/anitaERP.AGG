<?php

namespace App\Console\Commands;

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Ventas\Tiendanube\TiendanubeApiHealthSupport;
use Illuminate\Console\Command;

/**
 * Verifica el access_token de Tiendanube y alerta por mail si está inválido.
 */
class TiendanubeVerificarApiCommand extends Command
{
    protected $signature = 'tiendanube:verificar-api
                            {--forzar : Ignora cache de health}
                            {--sin-mail : No envía alerta por mail}';

    protected $description = 'Ping API Tiendanube (token). Alerta por mail si 401/403.';

    public function handle(TiendanubeApiHealthSupport $health): int
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            $this->warn('Solo aplica en entorno Ferli.');

            return self::SUCCESS;
        }

        $resultado = $health->verificar((bool) $this->option('forzar'));

        if ($resultado['ok']) {
            $this->info(sprintf(
                'API OK (status %d)%s',
                $resultado['status'],
                ($resultado['from_cache'] ?? false) ? ' [cache]' : ''
            ));

            return self::SUCCESS;
        }

        $this->error(sprintf(
            'API FAIL status=%d auth_ok=%s — %s',
            $resultado['status'],
            ($resultado['auth_ok'] ?? false) ? 'yes' : 'no',
            $resultado['error'] ?? 'sin detalle'
        ));

        if (! (bool) $this->option('sin-mail') && ! ($resultado['auth_ok'] ?? false)) {
            $enviado = $health->notificarAuthInvalidaSiCorresponde();
            $this->comment($enviado ? 'Alerta mail enviada.' : 'Alerta mail omitida (throttle / sin destinatario).');
        }

        return self::FAILURE;
    }
}
