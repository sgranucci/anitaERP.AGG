<?php

namespace App\Support\Stock;

use App\Models\Stock\Recuento;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Recuentos cerrados de cada depósito para el inventario de contaduría.
 * El mes es el de la fecha pedida. Si esa fecha es el 1, el 2 o el 3,
 * el cierre es el del mes anterior (se cuenta el primer día y, si el 1
 * cae domingo, el lunes).
 * De cada rubro que el comentario nombra se toma el de los últimos tres
 * días de ese mes o el del día 1 del mes siguiente. Si ese rubro no tiene
 * ninguno, entra el del 2 o el 3. Un depósito puede mezclar el 31 con el 2
 * cuando son rubros distintos. No se vuelve a un mes anterior.
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

        [$desde, $corte, $extension] = self::ventana($fechaHasta);

        $recuentos = Recuento::query()
            ->with('usuario:id,nombre')
            ->whereIn('deposito_id', $depositoIds)
            ->whereBetween('fecha', [$desde, $extension])
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
            $seleccion = self::elegirRecuentos($lista, $desde, $corte, $extension);
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
     * Últimos tres días del mes, el día 1 del siguiente, y el 2 y el 3
     * como respaldo.
     *
     * @return array{0:string,1:string,2:string} desde, corte (día 1), extensión (día 3)
     */
    public static function ventana(string $fechaHasta): array
    {
        $pedida = Carbon::parse($fechaHasta)->startOfDay();
        $mes = $pedida->copy()->startOfMonth();
        if ($pedida->day <= 3) {
            $mes = $mes->subMonth();
        }
        $fin = $mes->copy()->endOfMonth()->startOfDay();
        $desde = $fin->copy()->subDays(2);
        $corte = $fin->copy()->addDay();
        $extension = $corte->copy()->addDays(2);

        return [$desde->toDateString(), $corte->toDateString(), $extension->toDateString()];
    }

    /**
     * Por rubro: el día más reciente entre los últimos del mes y el día 1.
     * El 2 y el 3 solo si ese rubro no tiene ninguno de esos días.
     * Sin rubro en el comentario, el grupo es el del depósito.
     *
     * @param  list<array{id:int,fecha:string,rubro?:?string}>  $lista
     * @return list<array{id:int,fecha:string}>
     */
    private static function elegirRecuentos(array $lista, string $desde, string $corte, string $extension): array
    {
        $grupos = [];
        foreach ($lista as $recuento) {
            $rubro = $recuento['rubro'] ?? null;
            $clave = ($rubro === null || $rubro === '') ? '' : (string) $rubro;
            $grupos[$clave][] = $recuento;
        }

        $seleccion = [];
        foreach ($grupos as $grupo) {
            foreach (self::elegirGrupo($grupo, $desde, $corte, $extension) as $recuento) {
                $seleccion[] = $recuento;
            }
        }

        return $seleccion;
    }

    /**
     * @param  list<array{id:int,fecha:string}>  $grupo
     * @return list<array{id:int,fecha:string}>
     */
    private static function elegirGrupo(array $grupo, string $desde, string $corte, string $extension): array
    {
        $primarios = [];
        $tardios = [];
        foreach ($grupo as $recuento) {
            $fecha = $recuento['fecha'];
            if ($fecha >= $desde && $fecha <= $corte) {
                $primarios[] = $recuento;
            } elseif ($fecha > $corte && $fecha <= $extension) {
                $tardios[] = $recuento;
            }
        }

        $banda = $primarios !== [] ? $primarios : $tardios;
        if ($banda === []) {
            return [];
        }

        $fechas = array_column($banda, 'fecha');
        rsort($fechas);
        $max = $fechas[0];

        return array_values(array_filter(
            $banda,
            static fn (array $recuento) => $recuento['fecha'] === $max,
        ));
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
