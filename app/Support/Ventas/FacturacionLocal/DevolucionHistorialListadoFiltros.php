<?php

namespace App\Support\Ventas\FacturacionLocal;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class DevolucionHistorialListadoFiltros
{
    /**
     * @return array<string, mixed>
     */
    public static function resolverDesdeRequest(Request $request): array
    {
        $origen = trim((string) $request->input('origen', ''));
        if (! isset(DevolucionHistorialOrigenSupport::ETIQUETAS[$origen])) {
            $origen = '';
        }

        $stock = trim((string) $request->input('vuelve_stock', ''));
        if (! in_array($stock, ['', '1', '0'], true)) {
            $stock = '';
        }

        return [
            'consultar' => $request->boolean('consultar') ? 1 : 0,
            'fecha_desde' => self::fecha($request->input('fecha_desde')),
            'fecha_hasta' => self::fecha($request->input('fecha_hasta')),
            'origen' => $origen,
            'motivo' => trim((string) $request->input('motivo', '')),
            'vuelve_stock' => $stock,
            'local' => trim((string) $request->input('local', '')),
            'texto' => trim((string) $request->input('texto', '')),
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function tieneCriteriosAplicados(array $filtros): bool
    {
        foreach (['fecha_desde', 'fecha_hasta', 'origen', 'motivo', 'vuelve_stock', 'local', 'texto'] as $clave) {
            if (trim((string) ($filtros[$clave] ?? '')) !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, string|int>
     */
    public static function paraQueryString(array $filtros): array
    {
        $out = ['consultar' => 1];
        foreach (['fecha_desde', 'fecha_hasta', 'origen', 'motivo', 'vuelve_stock', 'local', 'texto'] as $clave) {
            $valor = trim((string) ($filtros[$clave] ?? ''));
            if ($valor !== '') {
                $out[$clave] = $valor;
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function subtitulo(array $filtros): string
    {
        $partes = [];
        if (($filtros['fecha_desde'] ?? '') !== '' || ($filtros['fecha_hasta'] ?? '') !== '') {
            $partes[] = 'Desde '.self::mostrarFecha((string) ($filtros['fecha_desde'] ?? ''))
                .' hasta '.self::mostrarFecha((string) ($filtros['fecha_hasta'] ?? ''));
        }
        if (($filtros['origen'] ?? '') !== '') {
            $partes[] = DevolucionHistorialOrigenSupport::etiqueta((string) $filtros['origen']);
        }
        if (($filtros['motivo'] ?? '') !== '') {
            $partes[] = 'Motivo: '.$filtros['motivo'];
        }
        if (($filtros['vuelve_stock'] ?? '') === '1') {
            $partes[] = 'Vuelve al stock';
        } elseif (($filtros['vuelve_stock'] ?? '') === '0') {
            $partes[] = 'No ingresa al stock';
        }
        if (($filtros['local'] ?? '') !== '') {
            $partes[] = 'Local: '.$filtros['local'];
        }
        if (($filtros['texto'] ?? '') !== '') {
            $partes[] = 'Texto: '.$filtros['texto'];
        }

        return implode(' · ', $partes);
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function aplicar(Builder $query, array $filtros): Builder
    {
        if (($filtros['fecha_desde'] ?? '') !== '') {
            $query->whereDate('devolucion_historial.fecha', '>=', $filtros['fecha_desde']);
        }
        if (($filtros['fecha_hasta'] ?? '') !== '') {
            $query->whereDate('devolucion_historial.fecha', '<=', $filtros['fecha_hasta']);
        }
        if (($filtros['origen'] ?? '') !== '') {
            $query->where('devolucion_historial.origen', $filtros['origen']);
        }
        if (($filtros['vuelve_stock'] ?? '') === '1') {
            $query->where('devolucion_historial.vuelve_stock', true);
        } elseif (($filtros['vuelve_stock'] ?? '') === '0') {
            $query->where('devolucion_historial.vuelve_stock', false);
        }
        $motivo = trim((string) ($filtros['motivo'] ?? ''));
        if ($motivo !== '') {
            $like = '%'.$motivo.'%';
            $query->where(function (Builder $q) use ($like) {
                $q->where('devolucion_historial.motivo_nombre', 'like', $like)
                    ->orWhere('devolucion_historial.motivo_codigo', 'like', $like);
            });
        }
        $local = trim((string) ($filtros['local'] ?? ''));
        if ($local !== '') {
            $like = '%'.$local.'%';
            $query->whereIn('devolucion_historial.local_venta_id', function ($sub) use ($like) {
                $sub->select('id')->from('local_venta')->where(function ($w) use ($like) {
                    $w->where('nombre', 'like', $like)->orWhere('codigo', 'like', $like);
                });
            });
        }
        $texto = trim((string) ($filtros['texto'] ?? ''));
        if ($texto !== '') {
            $like = '%'.$texto.'%';
            $query->where(function (Builder $q) use ($like) {
                $q->where('devolucion_historial.sku', 'like', $like)
                    ->orWhere('devolucion_historial.descripcion', 'like', $like)
                    ->orWhereIn('devolucion_historial.venta_id', function ($sub) use ($like) {
                        $sub->select('id')->from('venta')->where('codigo', 'like', $like);
                    });
            });
        }

        return $query;
    }

    private static function fecha(mixed $valor): string
    {
        $texto = trim((string) $valor);
        if ($texto === '') {
            return '';
        }
        $dt = \DateTime::createFromFormat('Y-m-d', $texto);

        return ($dt && $dt->format('Y-m-d') === $texto) ? $texto : '';
    }

    private static function mostrarFecha(string $ymd): string
    {
        if ($ymd === '') {
            return '…';
        }
        $dt = \DateTime::createFromFormat('Y-m-d', $ymd);

        return $dt ? $dt->format('d/m/Y') : $ymd;
    }
}
