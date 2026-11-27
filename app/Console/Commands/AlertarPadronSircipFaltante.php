<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\Configuracion\PadronIibbCargaNotificacionSupport;
use App\Support\Configuracion\Sircip\SircipVigenciaSupport;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Avisa si el padrón SIRCIP de un período requerido no está cargado.
 *
 * Período requerido:
 *  - el del mes en curso, si alguna provincia ya está en SIRCIP (fecha desde ≤ hoy);
 *  - el del mes siguiente, a partir del día configurado (el padrón se publica
 *    alrededor del 22) y siempre que SIRCIP vaya a estar vigente en ese mes.
 *
 * Solo lee la base y manda mail: no escribe datos.
 */
class AlertarPadronSircipFaltante extends Command
{
    protected $signature = 'padron-sircip:alertar-faltante
        {--fecha= : Fecha a evaluar (Y-m-d). Por defecto hoy}
        {--forzar-mail : Envía el mail aunque no falte nada}';

    protected $description = 'Avisa si falta cargar el padrón SIRCIP del mes en curso o del siguiente';

    public function handle(): int
    {
        $fecha = Carbon::parse((string) ($this->option('fecha') ?: date('Y-m-d')));

        $marcadas = SircipVigenciaSupport::jurisdiccionesMarcadas();
        if ($marcadas === []) {
            $this->info('Ninguna provincia tiene fecha SIRCIP: nada que controlar.');

            return self::SUCCESS;
        }

        $primera = min($marcadas);
        $diaAlerta = max(1, min(28, (int) config('padrones_iibb.sircip.alertar_desde_dia', 24)));

        $requeridos = [];
        if ($primera <= $fecha->toDateString()) {
            $requeridos[$fecha->format('Ym')] = 'mes en curso';
        }
        $siguiente = $fecha->copy()->startOfMonth()->addMonthNoOverflow();
        if ($fecha->day >= $diaAlerta && $primera <= $siguiente->copy()->endOfMonth()->toDateString()) {
            $requeridos[$siguiente->format('Ym')] = 'mes siguiente';
        }

        if ($requeridos === []) {
            $this->info('SIRCIP todavía no exige padrón (primera vigencia '.$primera.').');

            return self::SUCCESS;
        }

        $cargados = SircipVigenciaSupport::periodosCargados();
        $faltan = [];
        $filas = [];
        foreach ($requeridos as $periodo => $cual) {
            $ok = isset($cargados[$periodo]);
            $filas[] = [$periodo, $cual, $ok ? number_format($cargados[$periodo], 0, ',', '.') : 'NO CARGADO'];
            if (! $ok) {
                $faltan[$periodo] = $cual;
            }
        }

        $this->info('Padrón SIRCIP al '.$fecha->toDateString().' (primera vigencia '.$primera.')');
        $this->table(['Período', 'Cuál', 'Filas'], $filas);

        if ($faltan === [] && ! $this->option('forzar-mail')) {
            $this->info('Todos los períodos requeridos están cargados.');

            return self::SUCCESS;
        }

        $detalle = [];
        foreach ($faltan as $periodo => $cual) {
            $detalle['Período '.$periodo] = 'falta cargar ('.$cual.')';
        }
        $detalle['Dónde'] = 'copiar SIRCIP_AAAAMM_PadronAgente.zip en la carpeta sircip de la bandeja de padrones';

        $mensaje = $faltan === []
            ? 'El padrón SIRCIP requerido está cargado.'
            : 'Falta el padrón SIRCIP de '.implode(' y ', array_keys($faltan)).'. Sin padrón vigente no se puede calcular la percepción SIRCIP con el CRC y la alícuota del contribuyente.';

        PadronIibbCargaNotificacionSupport::notificar($faltan === [], 'SIRCIP padrón faltante', $mensaje, '', $detalle);

        foreach ($faltan as $periodo => $cual) {
            $this->error("Falta el padrón SIRCIP {$periodo} ({$cual}).");
        }

        return self::SUCCESS;
    }
}
