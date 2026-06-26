<?php

namespace App\Console\Commands;

use App\ApiAnita;
use App\Models\Seguridad\Usuario;
use App\Services\Ventas\Gastronomia\GastronomiaReplicarVentasAnitaErpService;
use App\Support\Ventas\Gastronomia\GastronomiaCorregirPostCierreWaitryRebiscoSupport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;

class GastronomiaCorregirPostCierreWaitryRebisco extends Command
{
    protected $signature = 'gastronomia:corregir-post-cierre-waitry-rebisco
                            {--aplicar : Ejecutar cambios (default: solo preview)}
                            {--solo-observacion-asientos : Solo reasigna observación jornada 23/06 en asientos agregados 37830/37833}
                            {--replicar : Tras aplicar, replicar las 7 fc en Anita}
                            {--sin-regrabar-j24 : No re-grabar rendgastro jornada 24 si falta}
                            {--usuario= : usuario_id para asientos/replicación}';

    protected $description = 'Renumera post-cierre Waitry Rebisco 54047–54053 → 54194–54200, jornada 23/06, asientos y rendgastro';

    public function handle(
        GastronomiaCorregirPostCierreWaitryRebiscoSupport $support,
        GastronomiaReplicarVentasAnitaErpService $replicarService,
    ): int {
        $this->line('Bridge: '.ApiAnita::urlBridge());

        $aplicar = (bool) $this->option('aplicar');
        $soloObs = (bool) $this->option('solo-observacion-asientos');

        if ($soloObs) {
            $realinear = $support->realinearObservacionAsientosAgregados(! $aplicar);
            $this->table(
                ['Asiento', 'Observación actual', 'Observación nueva'],
                array_map(static fn (array $p): array => [
                    $p['asiento_id'],
                    $p['observacion_actual'],
                    $p['observacion_nueva'],
                ], $realinear['pasos']),
            );
            if (! $aplicar) {
                $this->warn('Modo preview. Use --aplicar --solo-observacion-asientos para grabar.');

                return self::SUCCESS;
            }
            $this->info('Observaciones de asientos agregados actualizadas.');

            return self::SUCCESS;
        }

        $preview = $support->preview();

        $this->info('Destino: jornada '.GastronomiaCorregirPostCierreWaitryRebiscoSupport::FECHA_JORNADA);
        $this->table(
            ['Venta', 'FC actual', 'FC nuevo', 'Jornada actual', 'Total'],
            array_map(static fn (array $r): array => [
                $r['venta_id'],
                $r['fc_actual'],
                $r['fc_nuevo'],
                $r['jornada_actual'],
                number_format($r['total'], 2, '.', ''),
            ], $preview['renumeracion']),
        );

        $this->table(
            ['Asiento', 'Nro', 'Fecha actual'],
            array_map(static fn (array $a): array => [$a['id'], $a['numeroasiento'], $a['fecha_actual']], $preview['asientos']),
        );

        foreach ($preview['rendgastro_post_cierre'] as $jId => $cabs) {
            $this->line('Rendgastro jornada '.$jId.': '.(count($cabs) === 0 ? 'sin CIERRE-WAITRY' : json_encode($cabs)));
        }

        if (! $aplicar) {
            $this->warn('Modo preview. Use --aplicar para ejecutar.');

            return self::SUCCESS;
        }

        $usuarioId = (int) ($this->option('usuario') ?: Usuario::query()->orderBy('id')->value('id') ?? 1);
        if ($usuarioId <= 0 || ! Auth::loginUsingId($usuarioId)) {
            $this->error('Usuario inválido.');

            return self::FAILURE;
        }

        $resultado = $support->ejecutar(
            false,
            ! (bool) $this->option('sin-regrabar-j24'),
        );

        $this->info($resultado['mensaje'] ?? 'OK');
        foreach ($resultado['pasos'] ?? [] as $paso) {
            $this->line('  · '.json_encode($paso, JSON_UNESCAPED_UNICODE));
        }

        if ((bool) $this->option('replicar')) {
            $this->info('Replicando fc post-cierre en Anita…');
            $rep = $replicarService->replicarFaltantes(
                GastronomiaCorregirPostCierreWaitryRebiscoSupport::FECHA_JORNADA,
                GastronomiaCorregirPostCierreWaitryRebiscoSupport::FECHA_JORNADA,
                GastronomiaCorregirPostCierreWaitryRebiscoSupport::EMPRESA_ID,
                '00030',
                false,
                7,
                true,
                true,
            );
            $this->line('Replicadas: '.($rep['replicadas'] ?? 0).' / faltantes: '.($rep['faltantes'] ?? 0));
            foreach ($rep['errores'] ?? [] as $err) {
                $this->error(is_string($err) ? $err : json_encode($err, JSON_UNESCAPED_UNICODE));
            }
        }

        return self::SUCCESS;
    }
}
