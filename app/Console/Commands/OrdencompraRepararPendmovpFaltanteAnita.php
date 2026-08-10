<?php

namespace App\Console\Commands;

use App\ApiAnita;
use App\Models\Compras\Ordencompra;
use App\Services\Compras\OrdencompraAnitaBridgeService;
use Illuminate\Console\Command;

class OrdencompraRepararPendmovpFaltanteAnita extends Command
{
    protected $signature = 'ordencompra:reparar-pendmovp-faltante-anita
                            {numero : numeroordencompra a reparar}
                            {--dry-run : Solo diagnostica sin escribir}';

    protected $description = 'Recrea pendmovp faltante en Anita, reasigna nro_interno inválidos y alinea recepciones';

    public function handle(OrdencompraAnitaBridgeService $bridge): int
    {
        if (! $bridge->habilitado()) {
            $this->error('ORDENCOMPRA_ANITA_ESCRITURA_HABILITADA está desactivada.');

            return self::FAILURE;
        }

        $numero = (int) $this->argument('numero');
        if ($numero <= 0) {
            $this->error('Indique un número de OC válido.');

            return self::FAILURE;
        }

        $oc = Ordencompra::query()
            ->where('numeroordencompra', $numero)
            ->with(['ordencompra_articulos.articulos', 'proveedores', 'ordencompra_comprobantes.ordencompra_comprobante_cuotas'])
            ->first();

        if ($oc === null) {
            $this->error("No existe OC ERP numeroordencompra={$numero}.");

            return self::FAILURE;
        }

        $this->line('Bridge: '.ApiAnita::urlBridge());
        $diag = $bridge->diagnosticarSincronizacionAnita($oc);
        $this->line('Diagnóstico previo: '.json_encode($diag, JSON_UNESCAPED_UNICODE));

        if ((bool) $this->option('dry-run')) {
            $this->warn('Dry-run: no se escribió en Anita/ERP.');

            return self::SUCCESS;
        }

        try {
            $resultado = $bridge->repararPendmovpFaltanteDesdeRecepciones($oc);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('Reparación OK OC '.$resultado['numero']);
        foreach ($resultado['acciones'] as $accion) {
            $this->line(' - '.$accion);
        }
        $this->line('mapa_internos: '.json_encode($resultado['mapa_internos']));
        $this->line('cantentr: '.json_encode($resultado['cantentr']));
        if ($resultado['problemas_restantes'] !== []) {
            $this->warn('Problemas restantes: '.implode(' | ', $resultado['problemas_restantes']));
        }

        return self::SUCCESS;
    }
}
