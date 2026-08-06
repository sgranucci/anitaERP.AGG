<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\Configuracion\PadronIibbCargaNotificacionSupport;
use App\Support\Configuracion\PadronIibbVigenciaSupport;
use Illuminate\Console\Command;

/**
 * Avisa qué padrones IIBB no cubren el período en curso.
 *
 * Salvo ARBA, los organismos exigen clave fiscal para bajar el padrón, así que
 * la carga es manual. Este aviso evita el peor escenario: facturar semanas con
 * tasa de descarte sin que nadie note que el padrón quedó viejo.
 */
class AlertarPadronIibbVencido extends Command
{
    protected $signature = 'padron-iibb:alertar-vencidos
        {--fecha= : Fecha a evaluar (Y-m-d). Por defecto hoy}
        {--forzar-mail : Envía el mail aunque estén todos vigentes}';

    protected $description = 'Avisa qué padrones IIBB no tienen vigencia para el período en curso';

    public function handle(): int
    {
        $fecha = (string) ($this->option('fecha') ?: date('Y-m-d'));

        PadronIibbVigenciaSupport::olvidar();
        $estado = PadronIibbVigenciaSupport::estado($fecha);

        if ($estado === []) {
            $this->warn('No hay jurisdicciones configuradas como agente de percepción o retención.');

            return self::SUCCESS;
        }

        $this->info('Vigencia de padrones IIBB al ' . $fecha);
        $this->table(
            ['Juris.', 'Provincia', 'Último período', 'Vigente', 'Descarga'],
            array_map(static fn (array $f): array => [
                $f['jurisdiccion'],
                $f['provincia'],
                $f['ultimo_periodo'] ?? 'sin datos',
                $f['vigente'] ? 'si' : 'NO',
                $f['automatico'] ? 'automatica' : 'manual',
            ], $estado)
        );

        $vencidas = array_values(array_filter($estado, static fn (array $f): bool => ! $f['vigente']));

        if ($vencidas === [] && ! $this->option('forzar-mail')) {
            $this->info('Todos los padrones cubren el período.');

            return self::SUCCESS;
        }

        $detalle = [];
        foreach ($vencidas as $fila) {
            $detalle[$fila['provincia'] . ' (' . $fila['jurisdiccion'] . ')'] = sprintf(
                'último período cargado: %s — descarga %s',
                $fila['ultimo_periodo'] ?? 'sin datos',
                $fila['automatico'] ? 'automática' : 'manual'
            );
        }

        $mensaje = $vencidas === []
            ? 'Todos los padrones IIBB cubren el período en curso.'
            : sprintf(
                'Hay %d padrón/es IIBB sin vigencia al %s. Mientras falten, esas jurisdicciones '
                . 'facturan con la tasa de descarte en lugar de la alícuota del contribuyente.',
                count($vencidas),
                $fecha
            );

        PadronIibbCargaNotificacionSupport::notificar(
            $vencidas === [],
            'IIBB vigencia de padrones',
            $mensaje,
            '',
            $detalle
        );

        foreach ($vencidas as $fila) {
            $this->error(sprintf(
                '%s (%s): sin padrón vigente. Último: %s',
                $fila['provincia'],
                $fila['jurisdiccion'],
                $fila['ultimo_periodo'] ?? 'sin datos'
            ));
        }

        return self::SUCCESS;
    }
}
