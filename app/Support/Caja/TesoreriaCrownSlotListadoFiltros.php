<?php

namespace App\Support\Caja;

use App\Models\Caja\TesoreriaCrownSlot;
use App\Support\Listado\FiltrosListadoRequest;
use DateTime;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class TesoreriaCrownSlotListadoFiltros
{
    /**
     * @return array<string, mixed>
     */
    public static function resolverDesdeRequest(Request $request, ?string $busquedaRuta = null): array
    {
        $salaId = (int) $request->input('sala_id', 0);
        if (FiltrosListadoRequest::solicitudLimpiaFiltros($request)) {
            return self::filtrosVacios($salaId);
        }

        return [
            'valor' => FiltrosListadoRequest::valorBusqueda($request, $busquedaRuta),
            'sala_id' => $salaId,
            'jornada_id' => (int) $request->input('jornada_id', 0),
            'fecha_desde' => self::fecha($request->input('fecha_desde')),
            'fecha_hasta' => self::fecha($request->input('fecha_hasta')),
            'tipo' => self::enum($request->input('tipo'), array_keys(TesoreriaCrownSlot::TIPOS)),
            'estado' => self::enum($request->input('estado'), array_keys(TesoreriaCrownSlot::ESTADOS)),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function filtrosVacios(int $salaId = 0): array
    {
        return [
            'valor' => '',
            'sala_id' => $salaId,
            'jornada_id' => 0,
            'fecha_desde' => '',
            'fecha_hasta' => '',
            'tipo' => '',
            'estado' => '',
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function tieneCriteriosAplicados(array $filtros): bool
    {
        return trim((string) ($filtros['valor'] ?? '')) !== ''
            || trim((string) ($filtros['fecha_desde'] ?? '')) !== ''
            || trim((string) ($filtros['fecha_hasta'] ?? '')) !== ''
            || trim((string) ($filtros['tipo'] ?? '')) !== ''
            || trim((string) ($filtros['estado'] ?? '')) !== ''
            || (int) ($filtros['jornada_id'] ?? 0) > 0;
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, string|int>
     */
    public static function paraQueryString(array $filtros): array
    {
        $params = [];
        if (trim((string) ($filtros['valor'] ?? '')) !== '') {
            $params['filtro_valor'] = $filtros['valor'];
        }
        if ((int) ($filtros['sala_id'] ?? 0) > 0) {
            $params['sala_id'] = (int) $filtros['sala_id'];
        }
        if ((int) ($filtros['jornada_id'] ?? 0) > 0) {
            $params['jornada_id'] = (int) $filtros['jornada_id'];
        }
        foreach (['fecha_desde', 'fecha_hasta', 'tipo', 'estado'] as $clave) {
            $valor = trim((string) ($filtros[$clave] ?? ''));
            if ($valor !== '') {
                $params[$clave] = $valor;
            }
        }

        return $params;
    }

    /**
     * @param  Builder<TesoreriaCrownSlot>  $query
     * @param  array<string, mixed>  $filtros
     */
    public static function aplicar(Builder $query, array $filtros): void
    {
        $salaId = (int) ($filtros['sala_id'] ?? 0);
        if ($salaId > 0) {
            $query->where('tesoreria_crown_slot.sala_id', $salaId);
        }
        $jornadaId = (int) ($filtros['jornada_id'] ?? 0);
        if ($jornadaId > 0) {
            $query->where('tesoreria_crown_slot.jornada_id', $jornadaId);
        }
        $desde = trim((string) ($filtros['fecha_desde'] ?? ''));
        if ($desde !== '') {
            $query->whereHas('jornada', fn ($q) => $q->whereDate('fecha', '>=', $desde));
        }
        $hasta = trim((string) ($filtros['fecha_hasta'] ?? ''));
        if ($hasta !== '') {
            $query->whereHas('jornada', fn ($q) => $q->whereDate('fecha', '<=', $hasta));
        }
        $tipo = trim((string) ($filtros['tipo'] ?? ''));
        if ($tipo !== '') {
            $query->where('tesoreria_crown_slot.tipo', $tipo);
        }
        $estado = trim((string) ($filtros['estado'] ?? ''));
        if ($estado !== '') {
            $query->where('tesoreria_crown_slot.estado', $estado);
        }
        $valor = trim((string) ($filtros['valor'] ?? ''));
        if ($valor !== '') {
            $like = '%'.$valor.'%';
            $query->where(function ($q) use ($like, $valor) {
                $q->where('tesoreria_crown_slot.maquina', 'like', $like)
                    ->orWhere('tesoreria_crown_slot.maquina_nombre', 'like', $like);
                if (ctype_digit($valor)) {
                    $q->orWhere('tesoreria_crown_slot.numero', (int) $valor);
                }
            });
        }
    }

    /**
     * @param  list<string>  $validos
     */
    private static function enum(mixed $valor, array $validos): string
    {
        $texto = trim((string) $valor);

        return in_array($texto, $validos, true) ? $texto : '';
    }

    private static function fecha(mixed $valor): string
    {
        $texto = trim((string) $valor);
        if ($texto === '') {
            return '';
        }
        $dt = DateTime::createFromFormat('Y-m-d', $texto)
            ?: DateTime::createFromFormat('d/m/Y', $texto);

        return $dt ? $dt->format('Y-m-d') : '';
    }
}
