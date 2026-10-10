<?php

declare(strict_types=1);

namespace App\Support\Ticket;

use App\Support\Listado\ListadoOrdenamientoSupport;

/**
 * Columnas de la grilla de administración de tickets.
 * El alcance por rol no vive acá: lo aplica TicketQuery.
 */
final class AdministracionTicketListadoColumnas
{
    public const RECURSO = 'ticket.administracion';

    /**
     * @return array<string, array{label: string, type: string, source: string, attr: string, filterable: bool, group: string}>
     */
    public static function catalogo(): array
    {
        return [
            'id' => self::col('ID', 'entero', 'ticket.id', 'id'),
            'fecha' => self::col('Fecha', 'fecha', 'ticket.fecha', 'fecha'),
            'sala' => self::col('Sala', 'texto', 'sala.nombre', 'nombresala'),
            'sector' => self::col('Sector', 'texto', 'sector_ticket.nombre', 'nombresector'),
            'areadestino' => self::col('Área de destino', 'texto', 'areadestino.nombre', 'nombreareadestino'),
            'usuario' => self::col('Generó usuario', 'texto', 'usuario.nombre', 'nombreusuario'),
            'categoria' => self::col('Categoría', 'texto', 'categoria_ticket.nombre', 'nombrecategoria_ticket'),
            'subcategoria' => self::col('Subcategoría', 'texto', 'subcategoria_ticket.nombre', 'nombresubcategoria_ticket'),
            'estado' => self::col('Estado', 'texto', 'ticket.estado_ticket', 'estado'),
            'titulo' => self::col('Título', 'texto', 'ticket.titulo', 'titulo'),
            'comentario' => self::col('Comentario', 'texto', 'ticket.comentario', 'comentario'),
            'tecnico' => self::col('Técnico asignado', 'texto', 'tickets_tarea.nombretecnico', 'nombretecnico'),
            'fecha_resolucion' => self::col('Resolución', 'fecha', 'ticket.fecha_resolucion', 'fecha_resolucion'),
            'tiempo_insumido' => self::col('Tiempo insumido (min)', 'entero', 'ticket.tiempo_insumido_total', 'tiempo_insumido_total'),
        ];
    }

    /**
     * @return array<string, array{label: string, type: string, source: string, attr: string, filterable: bool, group: string}>
     */
    public static function catalogoActivo(): array
    {
        return self::catalogo();
    }

    /**
     * @return list<string>
     */
    public static function defaultsVisibles(): array
    {
        return array_keys(self::catalogo());
    }

    /**
     * @return array<string, array{label: string, type: string, column: string}>
     */
    public static function camposFiltrables(): array
    {
        $out = [];
        foreach (self::catalogoActivo() as $key => $meta) {
            $out[$key] = [
                'label' => $meta['label'],
                'type' => $meta['type'],
                'column' => $meta['source'],
            ];
        }

        return $out;
    }

    /**
     * @return array<string, array{label: string, type: string, column: string}>
     */
    public static function camposOrdenables(): array
    {
        $out = [];
        foreach (self::camposFiltrables() as $key => $meta) {
            if (! ListadoOrdenamientoSupport::esColumnaSqlSegura($meta['column'])) {
                continue;
            }
            $out[$key] = $meta;
        }

        return $out;
    }

    public static function valorCelda(object $row, string $key): string
    {
        return match ($key) {
            'fecha' => self::fecha($row->fecha ?? null),
            'fecha_resolucion' => TicketEstadisticaSupport::formatearResolucionDisplay(
                $row->fecha_resolucion ?? null,
                $row->hora_resolucion ?? null
            ),
            'tiempo_insumido' => TicketEstadisticaSupport::formatearTiempoInsumido($row->tiempo_insumido_total ?? null),
            'tecnico' => self::tecnicos($row),
            default => trim((string) ($row->{self::catalogo()[$key]['attr'] ?? 'id'} ?? '')),
        };
    }

    public static function valorAgrupacion(object $row, string $key): string
    {
        $texto = self::valorCelda($row, $key);

        return $texto !== '' ? $texto : '(vacío)';
    }

    private static function tecnicos(object $row): string
    {
        $nombres = [];
        foreach ($row->ticket_tareas ?? [] as $tarea) {
            $nombre = trim((string) ($tarea->tecnicos->nombre ?? ''));
            if ($nombre !== '' && ! in_array($nombre, $nombres, true)) {
                $nombres[] = $nombre;
            }
        }
        if ($nombres !== []) {
            return implode(', ', $nombres);
        }

        return trim((string) ($row->nombretecnico ?? ''));
    }

    private static function fecha(mixed $valor): string
    {
        $texto = trim((string) $valor);
        if ($texto === '' || str_starts_with($texto, '0000')) {
            return '';
        }
        $ts = strtotime($texto);

        return $ts ? date('d/m/Y', $ts) : $texto;
    }

    /**
     * @return array{label: string, type: string, source: string, attr: string, filterable: bool, default: bool, group: string}
     */
    private static function col(string $label, string $type, string $source, string $attr): array
    {
        return [
            'label' => $label,
            'type' => $type,
            'source' => $source,
            'attr' => $attr,
            'filterable' => true,
            'default' => true,
            'group' => 'Ticket',
        ];
    }
}
