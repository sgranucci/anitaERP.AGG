<?php

namespace App\Support\Stock;

use App\Models\Stock\Recuento;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Recuentos cerrados de cada depósito para el inventario de contaduría.
 * La fecha pedida es el corte: entran los que tienen fecha hasta el día
 * siguiente (el cierre del 30 se cuenta el 1). No salta a uno posterior.
 * De cada rubro que el comentario nombra se queda el día más reciente,
 * así un recuento posterior de solo bebidas o un ajuste de una línea
 * no borra alimentos, limpieza o cigarrillos contados antes.
 * Un recuento sin rubro en el comentario actualiza los artículos que trae
 * y deja el resto. Si ese día hay varios del mismo rubro, entran todos.
 */
final class ContaduriaInventarioRecuentoSupport
{
    /**
     * @param  list<int>  $depositoIds
     * @return array{
     *     faltantes:list<int>,
     *     por_deposito:array<int, array{
     *         fecha:string,
     *         recuentos:list<array{id:int,codigo:string,estado:string}>,
     *         cantidades:array<int,float>
     *     }>
     * }
     */
    public static function resolver(array $depositoIds, string $fechaHasta): array
    {
        $depositoIds = array_values(array_unique(array_filter(array_map('intval', $depositoIds))));
        if ($depositoIds === [] || $fechaHasta === '') {
            return ['faltantes' => $depositoIds, 'por_deposito' => []];
        }

        $cierre = Carbon::parse($fechaHasta)->startOfDay();
        $fechaSiguiente = $cierre->copy()->addDay()->format('Y-m-d');

        $recuentos = Recuento::query()
            ->with('usuario:id,nombre')
            ->whereIn('deposito_id', $depositoIds)
            ->where('fecha', '<=', $fechaSiguiente)
            ->whereIn('estado', [Recuento::ESTADO_CERRADO_PARCIAL, Recuento::ESTADO_CERRADO_TOTAL])
            ->orderBy('deposito_id')
            ->orderBy('fecha')
            ->orderBy('id')
            ->get(['id', 'codigo', 'fecha', 'deposito_id', 'estado', 'comentario', 'usuario_id']);

        $porDeposito = [];
        foreach ($recuentos as $recuento) {
            $depositoId = (int) $recuento->deposito_id;
            $fecha = $recuento->fecha instanceof Carbon
                ? $recuento->fecha->format('Y-m-d')
                : substr((string) $recuento->fecha, 0, 10);
            $comentario = trim((string) ($recuento->comentario ?? ''));
            $porDeposito[$depositoId][] = [
                'id' => (int) $recuento->id,
                'codigo' => trim((string) $recuento->codigo),
                'estado' => (string) $recuento->estado,
                'comentario' => $comentario,
                'usuario' => trim((string) ($recuento->usuario?->nombre ?? '')),
                'fecha' => $fecha,
                'rubro' => ContaduriaInventarioRubroSupport::rubroDelComentario($comentario),
            ];
        }

        $fechaPorDeposito = [];
        $elegidos = [];
        foreach ($porDeposito as $depositoId => $lista) {
            $seleccion = self::elegirRecuentos($lista);
            if ($seleccion === []) {
                continue;
            }
            $fechas = array_column($seleccion, 'fecha');
            rsort($fechas);
            $fechaPorDeposito[$depositoId] = $fechas[0];
            $elegidos[$depositoId] = $seleccion;
        }

        $faltantes = [];
        foreach ($depositoIds as $depositoId) {
            if (! isset($elegidos[$depositoId])) {
                $faltantes[] = $depositoId;
            }
        }

        $ids = [];
        foreach ($elegidos as $lista) {
            foreach ($lista as $recuento) {
                $ids[] = $recuento['id'];
            }
        }

        $conteo = self::cantidadesPorDeposito($elegidos, $ids);

        $porDeposito = [];
        foreach ($elegidos as $depositoId => $lista) {
            usort($lista, static fn (array $a, array $b) => $a['id'] <=> $b['id']);
            $porDeposito[$depositoId] = [
                'fecha' => $fechaPorDeposito[$depositoId],
                'recuentos' => $lista,
                'cantidades' => $conteo['cantidades'][$depositoId] ?? [],
                'recuento_por_articulo' => $conteo['recuento_por_articulo'][$depositoId] ?? [],
            ];
        }

        return [
            'faltantes' => $faltantes,
            'por_deposito' => $porDeposito,
        ];
    }

    /**
     * @param  list<array{id:int,codigo:string,nombre:string}>  $depositos
     * @param  array<int, array{fecha:string,recuentos:list<array{id:int,codigo:string,estado:string}>,cantidades:array<int,float>}>  $porDeposito
     */
    public static function etiqueta(array $depositos, array $porDeposito): string
    {
        $partes = [];
        foreach ($depositos as $deposito) {
            $info = $porDeposito[(int) $deposito['id']] ?? null;
            if ($info === null) {
                continue;
            }
            $codigos = array_map(static fn (array $recuento) => $recuento['codigo'], $info['recuentos']);
            $fechas = [];
            foreach ($info['recuentos'] as $recuento) {
                $fechaRecuento = trim((string) ($recuento['fecha'] ?? ''));
                if ($fechaRecuento !== '') {
                    $fechas[$fechaRecuento] = Carbon::parse($fechaRecuento)->format('d/m/Y');
                }
            }
            if ($fechas === []) {
                $fechas[$info['fecha']] = Carbon::parse($info['fecha'])->format('d/m/Y');
            }
            ksort($fechas);
            $fechaTxt = count($fechas) === 1
                ? 'del '.reset($fechas)
                : 'entre el '.reset($fechas).' y el '.end($fechas);
            $estados = array_values(array_unique(array_column($info['recuentos'], 'estado')));
            $estado = count($estados) === 1
                ? mb_strtolower(Recuento::etiquetaEstado($estados[0]))
                : 'cerrado parcial y total';
            $partes[] = 'Dep. '.$deposito['codigo'].' '.$deposito['nombre'].': '
                .self::unir($codigos).' '.$fechaTxt.' ('.$estado.')';
        }

        return 'Origen: último recuento de cada depósito · '.implode(' · ', $partes);
    }

    /**
     * @param  array<int, list<array{id:int,codigo:string,estado:string}>>  $elegidos
     * @param  list<int>  $recuentoIds
     * @return array{cantidades:array<int, array<int, float>>, recuento_por_articulo:array<int, array<int, int>>}
     */
    private static function cantidadesPorDeposito(array $elegidos, array $recuentoIds): array
    {
        if ($recuentoIds === []) {
            return ['cantidades' => [], 'recuento_por_articulo' => []];
        }

        $depositoPorRecuento = [];
        foreach ($elegidos as $depositoId => $lista) {
            foreach ($lista as $recuento) {
                $depositoPorRecuento[$recuento['id']] = $depositoId;
            }
        }

        $sumas = [];
        $items = DB::table('recuento_item')
            ->whereIn('recuento_id', $recuentoIds)
            ->orderBy('recuento_id')
            ->get(['recuento_id', 'articulo_id', 'cantidad_contada']);

        foreach ($items as $item) {
            $recuentoId = (int) $item->recuento_id;
            $articuloId = (int) $item->articulo_id;
            if ($articuloId <= 0 || ! isset($depositoPorRecuento[$recuentoId])) {
                continue;
            }
            $sumas[$recuentoId][$articuloId] = ($sumas[$recuentoId][$articuloId] ?? 0) + (float) $item->cantidad_contada;
        }

        $cantidades = [];
        $recuentoPorArticulo = [];
        foreach ($elegidos as $depositoId => $lista) {
            $ordenados = $lista;
            usort($ordenados, static function (array $a, array $b): int {
                $fecha = strcmp((string) ($a['fecha'] ?? ''), (string) ($b['fecha'] ?? ''));

                return $fecha !== 0 ? $fecha : ($a['id'] <=> $b['id']);
            });
            foreach ($ordenados as $recuento) {
                foreach ($sumas[$recuento['id']] ?? [] as $articuloId => $cantidad) {
                    $cantidades[$depositoId][(int) $articuloId] = (float) $cantidad;
                    $recuentoPorArticulo[$depositoId][(int) $articuloId] = (int) $recuento['id'];
                }
            }
        }

        return [
            'cantidades' => $cantidades,
            'recuento_por_articulo' => $recuentoPorArticulo,
        ];
    }

    /**
     * @param  list<array{id:int,fecha:string,rubro:?string}>  $lista
     * @return list<array{id:int,fecha:string,rubro:?string}>
     */
    private static function elegirRecuentos(array $lista): array
    {
        $porRubro = [];
        $sinRubro = [];
        foreach ($lista as $recuento) {
            $rubro = $recuento['rubro'] ?? null;
            if ($rubro === null || $rubro === '') {
                $sinRubro[] = $recuento;
                continue;
            }
            $porRubro[$rubro][] = $recuento;
        }

        $seleccion = [];
        $fechaMinNombrado = null;
        foreach ($porRubro as $grupo) {
            $fechas = array_column($grupo, 'fecha');
            rsort($fechas);
            $maxFecha = $fechas[0] ?? '';
            if ($maxFecha === '') {
                continue;
            }
            if ($fechaMinNombrado === null || $maxFecha < $fechaMinNombrado) {
                $fechaMinNombrado = $maxFecha;
            }
            foreach ($grupo as $recuento) {
                if ($recuento['fecha'] === $maxFecha) {
                    $seleccion[] = $recuento;
                }
            }
        }

        if ($sinRubro !== []) {
            if ($fechaMinNombrado === null) {
                $fechas = array_column($sinRubro, 'fecha');
                rsort($fechas);
                $maxFecha = $fechas[0] ?? '';
                foreach ($sinRubro as $recuento) {
                    if ($recuento['fecha'] === $maxFecha) {
                        $seleccion[] = $recuento;
                    }
                }
            } else {
                foreach ($sinRubro as $recuento) {
                    if ($recuento['fecha'] >= $fechaMinNombrado) {
                        $seleccion[] = $recuento;
                    }
                }
            }
        }

        return $seleccion;
    }

    /**
     * @param  list<string>  $codigos
     */
    public static function enumerarCodigos(array $codigos): string
    {
        return self::unir($codigos);
    }

    /**
     * @param  list<string>  $codigos
     */
    private static function unir(array $codigos): string
    {
        $codigos = array_values(array_filter($codigos, static fn (string $codigo) => $codigo !== ''));
        $cantidad = count($codigos);
        if ($cantidad <= 1) {
            return $codigos[0] ?? '';
        }
        if ($cantidad === 2) {
            return $codigos[0].' y '.$codigos[1];
        }

        $ultimo = array_pop($codigos);

        return implode(', ', $codigos).' y '.$ultimo;
    }
}
