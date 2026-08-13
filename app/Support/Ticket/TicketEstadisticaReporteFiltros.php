<?php

namespace App\Support\Ticket;

use Illuminate\Http\Request;

/**
 * Filtros del informe estadístico de tickets (área Tecnología).
 */
final class TicketEstadisticaReporteFiltros
{
    public const CRITERIO_FECHA_ALTA = 'alta';

    public const CRITERIO_FECHA_RESOLUCION = 'resolucion';

    /**
     * @return array<string, mixed>
     */
    public static function filtrosVacios(): array
    {
        return [
            'fecha_desde' => null,
            'fecha_hasta' => null,
            'criterio_fecha' => self::CRITERIO_FECHA_ALTA,
            'sala_id' => null,
            'tecnico_id' => null,
            'estado' => '',
            'consultar' => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function resolverDesdeRequest(Request $request): array
    {
        $filtros = self::filtrosVacios();
        $filtros['fecha_desde'] = self::normalizarFecha($request->input('fecha_desde'));
        $filtros['fecha_hasta'] = self::normalizarFecha($request->input('fecha_hasta'));
        $criterio = trim((string) $request->input('criterio_fecha', self::CRITERIO_FECHA_ALTA));
        $filtros['criterio_fecha'] = $criterio === self::CRITERIO_FECHA_RESOLUCION
            ? self::CRITERIO_FECHA_RESOLUCION
            : self::CRITERIO_FECHA_ALTA;
        $salaId = (int) $request->input('sala_id', 0);
        $filtros['sala_id'] = $salaId > 0 ? $salaId : null;
        $tecnicoId = (int) $request->input('tecnico_id', 0);
        $filtros['tecnico_id'] = $tecnicoId > 0 ? $tecnicoId : null;
        $filtros['estado'] = trim((string) $request->input('estado', ''));
        $filtros['consultar'] = $request->boolean('consultar') || $request->input('consultar') == '1';

        return $filtros;
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, string|int>
     */
    public static function paraQueryString(array $filtros): array
    {
        $out = [];
        if (! empty($filtros['fecha_desde'])) {
            $out['fecha_desde'] = (string) $filtros['fecha_desde'];
        }
        if (! empty($filtros['fecha_hasta'])) {
            $out['fecha_hasta'] = (string) $filtros['fecha_hasta'];
        }
        if (($filtros['criterio_fecha'] ?? self::CRITERIO_FECHA_ALTA) === self::CRITERIO_FECHA_RESOLUCION) {
            $out['criterio_fecha'] = self::CRITERIO_FECHA_RESOLUCION;
        }
        if (! empty($filtros['sala_id'])) {
            $out['sala_id'] = (int) $filtros['sala_id'];
        }
        if (! empty($filtros['tecnico_id'])) {
            $out['tecnico_id'] = (int) $filtros['tecnico_id'];
        }
        if (trim((string) ($filtros['estado'] ?? '')) !== '') {
            $out['estado'] = (string) $filtros['estado'];
        }
        if (! empty($filtros['consultar'])) {
            $out['consultar'] = 1;
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function tieneCriteriosAplicados(array $filtros): bool
    {
        return ! empty($filtros['fecha_desde']) && ! empty($filtros['fecha_hasta']);
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function esPorTecnico(array $filtros): bool
    {
        return (int) ($filtros['tecnico_id'] ?? 0) > 0;
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function firma(array $filtros): string
    {
        return sha1(json_encode([
            'd' => $filtros['fecha_desde'] ?? '',
            'h' => $filtros['fecha_hasta'] ?? '',
            'c' => $filtros['criterio_fecha'] ?? self::CRITERIO_FECHA_ALTA,
            's' => (int) ($filtros['sala_id'] ?? 0),
            't' => (int) ($filtros['tecnico_id'] ?? 0),
            'e' => (string) ($filtros['estado'] ?? ''),
        ], JSON_UNESCAPED_UNICODE));
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function subtitulo(array $filtros, string $nombreSala = '', string $nombreTecnico = ''): string
    {
        $partes = [];
        $criterio = ($filtros['criterio_fecha'] ?? self::CRITERIO_FECHA_ALTA) === self::CRITERIO_FECHA_RESOLUCION
            ? 'resolución'
            : 'alta';
        if (! empty($filtros['fecha_desde']) && ! empty($filtros['fecha_hasta'])) {
            $partes[] = 'Período '.$criterio.' '.self::fechaDisplay((string) $filtros['fecha_desde'])
                .' – '.self::fechaDisplay((string) $filtros['fecha_hasta']);
        }
        if ($nombreSala !== '') {
            $partes[] = 'Sala: '.$nombreSala;
        }
        if ($nombreTecnico !== '') {
            $partes[] = 'Técnico: '.$nombreTecnico;
        } else {
            $partes[] = 'Tiempo insumido: total del ticket';
        }
        if ($nombreTecnico !== '') {
            $partes[] = 'Tiempo insumido: del técnico';
        }
        $estado = trim((string) ($filtros['estado'] ?? ''));
        $partes[] = $estado !== '' ? 'Estado: '.$estado : 'Estado: todos';

        return implode(' · ', $partes);
    }

    private static function normalizarFecha($valor): ?string
    {
        $s = trim((string) $valor);
        if ($s === '' || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) {
            return null;
        }

        return $s;
    }

    private static function fechaDisplay(string $ymd): string
    {
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $ymd, $p)) {
            return $p[3].'/'.$p[2].'/'.$p[1];
        }

        return $ymd;
    }
}
