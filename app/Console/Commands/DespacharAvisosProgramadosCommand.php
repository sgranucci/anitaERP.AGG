<?php

namespace App\Console\Commands;

use App\Models\Configuracion\ModuloAvisoProgramadoLog;
use App\Models\Configuracion\ModuloAvisoTipo;
use App\Support\Configuracion\ModuloAvisoImpactoPreviewSupport;
use App\Support\Configuracion\ModuloAvisoProgramacionSupport;
use App\Support\Configuracion\ModuloAvisoProgramadoDespachoSupport;
use App\Support\Configuracion\ModuloAvisoProgramadoEjecucionSupport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/**
 * Recorre los avisos con programación cargada en Configuración → Avisos por módulo
 * y ejecuta el comando del que ya cumplió día y hora, una vez por día.
 */
class DespacharAvisosProgramadosCommand extends Command
{
    protected $signature = 'avisos:despachar-programados
                            {--simular : Muestra los avisos que corresponden sin ejecutarlos}';

    protected $description = 'Despacha los avisos programados desde el módulo de avisos general.';

    public function handle(): int
    {
        $mapa = config('modulo_aviso.programados', []);
        if (! is_array($mapa) || $mapa === []) {
            $this->info('No hay avisos programables.');

            return self::SUCCESS;
        }

        $ahora = now()->timezone('America/Argentina/Buenos_Aires');
        $feriados = ModuloAvisoProgramacionSupport::feriadosYmdEntre(
            $ahora->toDateString(),
            $ahora->toDateString()
        );
        $simular = (bool) $this->option('simular');
        $disparados = 0;
        /** @var array<string, true> $comandosHechos evita doble corrida si varios tipos apuntan al mismo comando */
        $comandosHechos = [];

        $tipos = ModuloAvisoTipo::query()
            ->where('activo', true)
            ->where('programacion_habilitada', true)
            ->get();

        foreach ($tipos as $tipo) {
            $clave = $tipo->claveCompleta();
            $comando = $mapa[$clave] ?? null;
            if (! is_string($comando) || $comando === '') {
                continue;
            }
            if (! ModuloAvisoProgramadoDespachoSupport::entornoPermite($clave)) {
                continue;
            }

            $cadaPasada = ModuloAvisoProgramadoDespachoSupport::esCadaPasada($tipo);
            // Poll (Flash/definibles): ignora ultima_en del día; la suscripción decide.
            if (! ModuloAvisoProgramacionSupport::correspondeTipo($tipo, $ahora, $feriados, $cadaPasada)) {
                continue;
            }

            $dep = trim((string) ($tipo->programacion_depende_de ?? ''));
            if ($dep !== '') {
                $modoDep = trim((string) ($tipo->programacion_depende_modo ?? 'ok_hoy')) ?: 'ok_hoy';
                if (! ModuloAvisoImpactoPreviewSupport::dependenciaSatisfactoria($dep, $ahora->toDateString(), $modoDep)) {
                    $this->warn($clave.' espera dependencia '.$dep.' (modo '.$modoDep.'); se saltea.');

                    continue;
                }
            }

            // 0 = sin tope (histórico: reintenta cada 15′ hasta OK del día).
            $maxReintentos = max(0, (int) ($tipo->programacion_reintentos_max ?? 0));
            if (! $cadaPasada && $maxReintentos > 0 && $this->reintentosHoy($tipo) >= $maxReintentos) {
                $this->warn($clave.' alcanzó el tope de reintentos ('.$maxReintentos.') hoy.');

                continue;
            }

            $freqLabel = $cadaPasada ? 'cada pasada' : ModuloAvisoProgramacionSupport::etiquetaDesdeTipo($tipo);
            $this->line($clave.' → '.$comando.' ('.$freqLabel.')');

            if ($simular) {
                $disparados++;

                continue;
            }

            $claveComando = $comando.'|'.json_encode(ModuloAvisoProgramadoDespachoSupport::opcionesArtisan($clave));
            if (isset($comandosHechos[$claveComando])) {
                if (! $cadaPasada) {
                    $tipo->programacion_ultima_en = $ahora->toDateString();
                    $tipo->save();
                }
                $disparados++;
                $this->line('  (comando ya ejecutado en esta pasada; solo se marca última corrida)');

                continue;
            }

            $resultado = ModuloAvisoProgramadoEjecucionSupport::ejecutar(
                $tipo,
                $comando,
                'cron',
                ModuloAvisoProgramadoDespachoSupport::opcionesArtisan($clave),
                $cadaPasada
            );
            if ($resultado['salida'] !== '') {
                $this->line($resultado['salida']);
            }
            if ($resultado['exit'] !== self::SUCCESS) {
                $this->error($clave.' terminó con código '.$resultado['exit'].'. Se reintenta en la próxima pasada.');
                if ($resultado['error']) {
                    $this->error($resultado['error']);
                }

                continue;
            }

            $comandosHechos[$claveComando] = true;
            if (! $cadaPasada) {
                $tipo->programacion_ultima_en = $ahora->toDateString();
                $tipo->save();
            }
            $disparados++;
        }

        $this->info(($simular ? 'Corresponderían: ' : 'Despachados: ').$disparados);

        return self::SUCCESS;
    }

    private function reintentosHoy(ModuloAvisoTipo $tipo): int
    {
        if (! Schema::hasTable('modulo_aviso_programado_log')) {
            return 0;
        }

        return (int) ModuloAvisoProgramadoLog::query()
            ->where('modulo_aviso_tipo_id', $tipo->id)
            ->whereDate('inicio_en', now()->timezone('America/Argentina/Buenos_Aires')->toDateString())
            ->where(function ($q) {
                $q->where('exit_code', '!=', 0)->orWhereNotNull('error');
            })
            ->count();
    }
}
