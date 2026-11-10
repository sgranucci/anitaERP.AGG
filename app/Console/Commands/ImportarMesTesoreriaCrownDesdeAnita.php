<?php

namespace App\Console\Commands;

use App\Models\Caja\TesoreriaCrownJornada;
use App\Models\Configuracion\Sala;
use App\Services\Caja\TesoreriaCrownBovedaAnitaImportService;
use App\Services\Caja\TesoreriaCrownCajaAnitaImportService;
use App\Services\Caja\TesoreriaCrownMesaAnitaImportService;
use App\Services\Caja\TesoreriaCrownSlotAnitaImportService;
use App\Support\Caja\TesoreriaCrownAnexo1Calculo;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Ventas\GastronomiaSalaSupport;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Throwable;

/**
 * Abre jornadas del rango e importa mesas / cajas / slots / bóveda (sin corte)
 * para armar el MTD nativo de anexos I y II.
 */
class ImportarMesTesoreriaCrownDesdeAnita extends Command
{
    protected $signature = 'tesoreria-crown:importar-mes-anita
                            {--desde= : Fecha inicial Y-m-d}
                            {--hasta= : Fecha final Y-m-d}
                            {--excepto-cajas= : Cajas Anita a excluir (coma). Las cajas operadas en el ERP se saltean solas}
                            {--ejecutar : Graba. Sin este flag solo resume el plan}';

    protected $description = 'Crea jornadas e importa mesas/cajas/slots/bóveda Anita día a día (MTD anexos).';

    public function handle(
        TesoreriaCrownMesaAnitaImportService $mesas,
        TesoreriaCrownCajaAnitaImportService $cajas,
        TesoreriaCrownSlotAnitaImportService $slots,
        TesoreriaCrownBovedaAnitaImportService $boveda,
    ): int {
        if (! EntornoEmpresaSupport::esCrown()) {
            $this->error('Solo en EMPRESA=CROWN.');

            return self::FAILURE;
        }
        $desde = trim((string) $this->option('desde'));
        $hasta = trim((string) $this->option('hasta'));
        if ($desde === '' || $hasta === '') {
            $this->error('Hacen falta --desde y --hasta.');

            return self::FAILURE;
        }
        try {
            $ini = Carbon::parse($desde)->startOfDay();
            $fin = Carbon::parse($hasta)->startOfDay();
        } catch (Throwable $e) {
            $this->error('Fechas inválidas: '.$e->getMessage());

            return self::FAILURE;
        }
        if ($fin->lt($ini)) {
            $this->error('--hasta debe ser >= --desde.');

            return self::FAILURE;
        }
        if ($ini->diffInDays($fin) > 62) {
            $this->error('El rango máximo es 62 días.');

            return self::FAILURE;
        }

        $codigoSala = strtoupper(trim((string) config('caja_anita.sala_codigo', 'CIP')));
        $salaId = GastronomiaSalaSupport::idPorCodigo($codigoSala);
        $sala = $salaId ? Sala::query()->find($salaId) : Sala::query()->orderBy('id')->first();
        if ($sala === null) {
            $this->error('No hay sala Crown ('.$codigoSala.').');

            return self::FAILURE;
        }
        $exceptoCajas = array_values(array_filter(array_map(
            'intval',
            explode(',', (string) $this->option('excepto-cajas'))
        )));
        $ejecutar = (bool) $this->option('ejecutar');
        $this->info(sprintf(
            'Sala %s (id %d) %s → %s | %s',
            $sala->codigo ?? $sala->id,
            $sala->id,
            $ini->toDateString(),
            $fin->toDateString(),
            $ejecutar ? 'EJECUTAR' : 'DRY-RUN'
        ));

        $resumen = [];
        for ($dia = $ini->copy(); $dia->lte($fin); $dia->addDay()) {
            $fecha = $dia->toDateString();
            $fila = [
                'fecha' => $fecha,
                'jornada' => 'ok',
                'mesas' => 0,
                'cajas' => 0,
                'slots' => 0,
                'boveda' => 0,
                'z' => 0,
                'aviso' => '',
            ];
            try {
                $jornada = TesoreriaCrownJornada::query()
                    ->where('sala_id', $sala->id)
                    ->whereDate('fecha', $fecha)
                    ->first();
                if ($jornada === null) {
                    if ($ejecutar) {
                        // Histórico cerrado: deja libre la abierta del día actual.
                        $jornada = TesoreriaCrownJornada::query()->create([
                            'sala_id' => $sala->id,
                            'empresa_id' => $sala->empresa_id,
                            'fecha' => $fecha,
                            'estado' => TesoreriaCrownJornada::CERRADA,
                        ]);
                        $fila['jornada'] = 'creada';
                    } else {
                        $fila['jornada'] = 'faltaría';
                    }
                }

                if ($jornada === null && ! $ejecutar) {
                    $planM = $this->contarMesasSinJornada($fecha);
                    $fila['mesas'] = $planM;
                    $fila['aviso'] = 'sin jornada';
                    $resumen[] = $fila;

                    continue;
                }

                $planMesas = $mesas->plan($fecha);
                $fila['mesas'] = count($planMesas['crear']);
                $planCajas = $cajas->plan($fecha, $exceptoCajas);
                $nuevosCaja = 0;
                foreach ($planCajas['cajas'] as $caja) {
                    foreach ($caja['documentos'] as $doc) {
                        if (! $doc['ya']) {
                            $nuevosCaja++;
                        }
                    }
                }
                $fila['cajas'] = $nuevosCaja;
                $planSlots = $slots->plan($fecha);
                $fila['slots'] = count($planSlots['crear']);
                $planBoveda = $boveda->plan($fecha, '');
                $fila['boveda'] = count($planBoveda['crear']);
                $planZ = $cajas->planCierresZ($fecha);
                $fila['z'] = count($planZ);

                if ($ejecutar) {
                    if ($fila['mesas'] > 0) {
                        $reemplazar = ! ($planMesas['mesa1']['coincide'] ?? true);
                        $mesas->aplicar($planMesas, $reemplazar);
                    }
                    if ($fila['cajas'] > 0) {
                        $cajas->aplicar($planCajas);
                    }
                    if ($fila['slots'] > 0) {
                        $slots->aplicar($planSlots);
                    }
                    if ($fila['boveda'] > 0) {
                        $boveda->aplicar($planBoveda);
                    }
                    if ($fila['z'] > 0) {
                        $cajas->aplicarCierresZ($planZ);
                    }
                    // Persiste caja final / poder público para la cadena del anexo I.
                    $jornada->refresh();
                    try {
                        TesoreriaCrownAnexo1Calculo::deJornada($jornada);
                    } catch (Throwable $anexoEx) {
                        $fila['aviso'] = 'import ok; anexo I: '.mb_substr($anexoEx->getMessage(), 0, 80);
                        $this->warn($fecha.' anexo I: '.$anexoEx->getMessage());
                    }
                }
            } catch (Throwable $e) {
                $fila['aviso'] = mb_substr($e->getMessage(), 0, 120);
                $this->warn($fecha.': '.$e->getMessage());
            }
            $resumen[] = $fila;
            $this->line(sprintf(
                '%s j=%s m=%d c=%d s=%d b=%d z=%d %s',
                $fila['fecha'],
                $fila['jornada'],
                $fila['mesas'],
                $fila['cajas'],
                $fila['slots'],
                $fila['boveda'],
                $fila['z'],
                $fila['aviso']
            ));
        }

        $this->table(
            ['Fecha', 'Jornada', 'Mesas+', 'Cajas+', 'Slots+', 'Bóveda+', 'Z+', 'Aviso'],
            array_map(static fn (array $f) => [
                $f['fecha'], $f['jornada'], $f['mesas'], $f['cajas'], $f['slots'], $f['boveda'], $f['z'], $f['aviso'],
            ], $resumen)
        );

        if (! $ejecutar) {
            $this->warn('Sin --ejecutar no se grabó nada.');
            $this->comment('Para grabar: php artisan tesoreria-crown:importar-mes-anita --desde='.$desde.' --hasta='.$hasta.' --ejecutar');
        }

        return self::SUCCESS;
    }

    private function contarMesasSinJornada(string $fecha): int
    {
        return 0;
    }
}
