<?php

namespace App\Support\Ticket;

use App\Models\Ticket\Ticket;
use App\Models\Ticket\Ticket_Tarea;
use Carbon\Carbon;
use DateTimeInterface;

/**
 * Estadísticas a nivel ticket: sello de resolución (Finalizado) y suma de minutos de todas las tareas.
 */
class TicketEstadisticaSupport
{
    public const ESTADO_FINALIZADO = 'Finalizado';

    public static function sumarTiempoInsumido(int $ticketId): float
    {
        return round((float) Ticket_Tarea::query()
            ->where('ticket_id', $ticketId)
            ->sum('tiempoinsumido'), 2);
    }

    public static function todasLasTareasFinalizadas(int $ticketId): bool
    {
        $tareas = Ticket_Tarea::query()->where('ticket_id', $ticketId)->get();
        if ($tareas->isEmpty()) {
            return false;
        }

        foreach ($tareas as $tarea) {
            if (! self::tareaEstaFinalizada($tarea)) {
                return false;
            }
        }

        return true;
    }

    public static function tareaEstaFinalizada(Ticket_Tarea $tarea): bool
    {
        $fecha = (string) ($tarea->fechafinalizacion ?? '');

        return $fecha !== '' && $fecha >= '2000-01-01';
    }

    public static function tieneSelloResolucion(Ticket $ticket): bool
    {
        $fecha = self::fechaYmd($ticket->fecha_resolucion ?? null);
        if ($fecha === '' || $fecha < '2000-01-01') {
            return false;
        }

        return self::horaResolucionEsReal($ticket->hora_resolucion ?? null);
    }

    public static function horaResolucionEsReal($hora): bool
    {
        $h = self::formatearHora($hora);

        return $h !== '' && $h !== '00:00';
    }

    /**
     * @return array{fecha_resolucion: string, hora_resolucion: string, sello_nuevo: bool}
     */
    public static function selloResolucion(Ticket $ticket, ?Carbon $momento = null): array
    {
        if (self::tieneSelloResolucion($ticket)) {
            return [
                'fecha_resolucion' => self::fechaYmd($ticket->fecha_resolucion),
                'hora_resolucion' => self::formatearHora($ticket->hora_resolucion),
                'sello_nuevo' => false,
            ];
        }

        $now = $momento ?? Carbon::now();
        $hoy = $now->toDateString();
        $fechaExistente = self::fechaYmd($ticket->fecha_resolucion ?? null);

        if ($fechaExistente !== '' && $fechaExistente >= '2000-01-01') {
            if ($fechaExistente === $hoy) {
                return [
                    'fecha_resolucion' => $fechaExistente,
                    'hora_resolucion' => $now->format('H:i'),
                    'sello_nuevo' => true,
                ];
            }

            return [
                'fecha_resolucion' => $fechaExistente,
                'hora_resolucion' => self::formatearHora($ticket->hora_resolucion) ?: '00:00',
                'sello_nuevo' => false,
            ];
        }

        return [
            'fecha_resolucion' => $hoy,
            'hora_resolucion' => $now->format('H:i'),
            'sello_nuevo' => true,
        ];
    }

    /**
     * Ajusta el payload de guardado: sello al pasar a Finalizado, limpieza al salir, suma de minutos.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function aplicarAlGuardar(Ticket $ticket, array $data): array
    {
        $estadoNuevo = (string) ($data['estado_ticket'] ?? $ticket->estado_ticket ?? '');
        $estadoAnterior = (string) ($ticket->estado_ticket ?? '');

        $data['tiempo_insumido_total'] = self::sumarTiempoInsumido((int) $ticket->id);

        if ($estadoNuevo === self::ESTADO_FINALIZADO) {
            $horaFormulario = self::formatearHora($data['hora_resolucion'] ?? null);
            $fechaFormulario = self::fechaYmd($data['fecha_resolucion'] ?? null);
            $sello = self::selloResolucion($ticket);
            $data['fecha_resolucion'] = $sello['fecha_resolucion'];
            $data['hora_resolucion'] = $sello['hora_resolucion'];
            if (self::horaResolucionEsReal($horaFormulario)) {
                if ($fechaFormulario !== '' && $fechaFormulario >= '2000-01-01') {
                    $data['fecha_resolucion'] = $fechaFormulario;
                }
                $data['hora_resolucion'] = $horaFormulario;
            }
        } elseif ($estadoAnterior === self::ESTADO_FINALIZADO && $estadoNuevo !== self::ESTADO_FINALIZADO) {
            $data['fecha_resolucion'] = null;
            $data['hora_resolucion'] = null;
        } else {
            unset($data['fecha_resolucion'], $data['hora_resolucion']);
        }

        return $data;
    }

    /**
     * Tras finalizar una tarea: actualiza la suma y, si todas están listas, cierra el ticket.
     *
     * @return array{
     *     estado_ticket: string,
     *     fecha_resolucion: string,
     *     hora_resolucion: string,
     *     tiempo_insumido_total: float,
     *     cerro_ticket: bool,
     *     sello_nuevo: bool
     * }
     */
    public static function sincronizarTrasFinalizarTarea(Ticket $ticket): array
    {
        $total = self::sumarTiempoInsumido((int) $ticket->id);
        $estado = (string) ($ticket->estado_ticket ?? '');
        $attrs = ['tiempo_insumido_total' => $total];
        $cerro = false;
        $selloNuevo = false;
        $fecha = self::fechaYmd($ticket->fecha_resolucion ?? null);
        $hora = self::formatearHora($ticket->hora_resolucion ?? null);

        $puedeCerrar = $estado !== 'Suspendido' && $estado !== 'Baja';
        if ($puedeCerrar && self::todasLasTareasFinalizadas((int) $ticket->id)) {
            $sello = self::selloResolucion($ticket);
            $attrs['estado_ticket'] = self::ESTADO_FINALIZADO;
            $attrs['fecha_resolucion'] = $sello['fecha_resolucion'];
            $attrs['hora_resolucion'] = $sello['hora_resolucion'];
            $estado = self::ESTADO_FINALIZADO;
            $fecha = $sello['fecha_resolucion'];
            $hora = $sello['hora_resolucion'];
            $cerro = true;
            $selloNuevo = $sello['sello_nuevo'];
        }

        $ticket->update($attrs);

        return [
            'estado_ticket' => $estado,
            'fecha_resolucion' => $fecha,
            'hora_resolucion' => $hora,
            'tiempo_insumido_total' => $total,
            'cerro_ticket' => $cerro,
            'sello_nuevo' => $selloNuevo,
        ];
    }

    /**
     * @return array{fecha_resolucion: null, hora_resolucion: null, tiempo_insumido_total: float}
     */
    public static function payloadAlReabrir(Ticket $ticket): array
    {
        return [
            'fecha_resolucion' => null,
            'hora_resolucion' => null,
            'tiempo_insumido_total' => self::sumarTiempoInsumido((int) $ticket->id),
        ];
    }

    public static function formatearHora($hora): string
    {
        if ($hora === null || $hora === '') {
            return '';
        }
        if ($hora instanceof DateTimeInterface) {
            return $hora->format('H:i');
        }
        $s = trim((string) $hora);
        if (strlen($s) >= 5) {
            return substr($s, 0, 5);
        }

        return $s;
    }

    public static function fechaYmd($fecha): string
    {
        if ($fecha === null || $fecha === '') {
            return '';
        }
        if ($fecha instanceof DateTimeInterface) {
            return $fecha->format('Y-m-d');
        }
        $s = trim((string) $fecha);
        if (strlen($s) >= 10) {
            return substr($s, 0, 10);
        }

        return $s;
    }

    public static function formatearFechaDisplay($fecha): string
    {
        $ymd = self::fechaYmd($fecha);
        if ($ymd === '' || $ymd < '2000-01-01') {
            return '';
        }

        return date('d/m/Y', strtotime($ymd));
    }

    public static function formatearResolucionDisplay($fecha, $hora): string
    {
        $fechaFmt = self::formatearFechaDisplay($fecha);
        if ($fechaFmt === '') {
            return '';
        }
        $horaFmt = self::formatearHora($hora);
        if ($horaFmt === '00:00') {
            $horaFmt = '';
        }

        return $horaFmt !== '' ? $fechaFmt.' '.$horaFmt : $fechaFmt;
    }

    public static function formatearTiempoInsumido($minutos): string
    {
        if ($minutos === null || $minutos === '') {
            return '';
        }
        $n = (float) $minutos;
        if (abs($n) < 0.0001) {
            return '0';
        }
        if (abs($n - round($n)) < 0.0001) {
            return (string) (int) round($n);
        }

        return rtrim(rtrim(number_format($n, 2, ',', ''), '0'), ',');
    }

    public static function tiempoInsumidoDesdeTareas($tareas): float
    {
        $total = 0.0;
        foreach ($tareas ?? [] as $tarea) {
            $total += (float) ($tarea->tiempoinsumido ?? 0);
        }

        return round($total, 2);
    }

    public static function tiempoInsumidoDeTecnico($tareas, int $tecnicoId): float
    {
        $total = 0.0;
        foreach ($tareas ?? [] as $tarea) {
            if ((int) ($tarea->tecnico_id ?? 0) !== $tecnicoId) {
                continue;
            }
            $total += (float) ($tarea->tiempoinsumido ?? 0);
        }

        return round($total, 2);
    }

    public static function momentoApertura(Ticket $ticket): ?Carbon
    {
        if (! empty($ticket->created_at)) {
            return Carbon::parse($ticket->created_at);
        }
        $fecha = self::fechaYmd($ticket->fecha ?? null);
        if ($fecha === '' || $fecha < '2000-01-01') {
            return null;
        }

        return Carbon::parse($fecha.' 00:00:00');
    }

    public static function momentoCierre(Ticket $ticket): ?Carbon
    {
        $fecha = self::fechaYmd($ticket->fecha_resolucion ?? null);
        if ($fecha === '' || $fecha < '2000-01-01') {
            return null;
        }
        $hora = self::formatearHora($ticket->hora_resolucion ?? null);
        if ($hora === '') {
            $hora = '00:00';
        }

        return Carbon::parse($fecha.' '.$hora.':00');
    }

    public static function momentoAsignacion($tareas): ?Carbon
    {
        $min = null;
        foreach ($tareas ?? [] as $tarea) {
            if ((int) ($tarea->tecnico_id ?? 0) <= 0) {
                continue;
            }
            $dt = null;
            if (! empty($tarea->created_at)) {
                $dt = Carbon::parse($tarea->created_at);
            } elseif (! empty($tarea->fechacarga) && (string) $tarea->fechacarga >= '2000-01-01') {
                $dt = Carbon::parse($tarea->fechacarga.' 00:00:00');
            }
            if ($dt === null) {
                continue;
            }
            if ($min === null || $dt->lt($min)) {
                $min = $dt;
            }
        }

        return $min;
    }

    public static function minutosEntre(?Carbon $desde, ?Carbon $hasta): ?int
    {
        if ($desde === null || $hasta === null || $hasta->lt($desde)) {
            return null;
        }

        return (int) $desde->diffInMinutes($hasta);
    }

    public static function formatearFechaHoraDisplay(?Carbon $momento): string
    {
        if ($momento === null) {
            return '';
        }

        return $momento->format('d/m/Y H:i');
    }

    public static function formatearDuracion(?int $minutos): string
    {
        if ($minutos === null) {
            return '';
        }
        if ($minutos < 0) {
            return '';
        }
        $dias = intdiv($minutos, 1440);
        $horas = intdiv($minutos % 1440, 60);
        $mins = $minutos % 60;
        $partes = [];
        if ($dias > 0) {
            $partes[] = $dias.' d';
        }
        if ($horas > 0 || $dias > 0) {
            $partes[] = $horas.' h';
        }
        $partes[] = $mins.' min';

        return implode(' ', $partes);
    }

    /**
     * @param  iterable<int, mixed>  $tareas
     * @return list<string>
     */
    public static function nombresTecnicos($tareas, ?int $soloTecnicoId = null): array
    {
        $nombres = [];
        foreach ($tareas ?? [] as $tarea) {
            if ($soloTecnicoId !== null && (int) ($tarea->tecnico_id ?? 0) !== $soloTecnicoId) {
                continue;
            }
            $nombre = trim((string) ($tarea->tecnicos?->nombre ?? ''));
            if ($nombre !== '' && ! in_array($nombre, $nombres, true)) {
                $nombres[] = $nombre;
            }
        }

        return $nombres;
    }
}
