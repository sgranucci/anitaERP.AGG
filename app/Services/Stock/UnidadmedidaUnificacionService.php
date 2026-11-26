<?php

namespace App\Services\Stock;

use App\Models\Stock\Unidadmedida;
use App\Support\Database\EloquentAuditDeleteSupport;
use App\Support\Stock\UnidadmedidaSifabSupport;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Unifica unidades de medida duplicadas (misma unidad con otro nombre/abreviatura) en la de menor id,
 * y corrige la unidad alternativa: principal Unidades => alternativa Kilos, y viceversa.
 *
 * `plan()` solo lee. `ejecutar()` persiste y deja un CSV con el estado previo.
 */
class UnidadmedidaUnificacionService
{
    /** @var list<array{tabla: string, principal: string, alternativa: ?string}> */
    private const COLUMNAS = [
        ['tabla' => 'articulo', 'principal' => 'unidadmedida_id', 'alternativa' => 'unidadmedidaalternativa_id'],
        ['tabla' => 'pedido_articulo', 'principal' => 'unidadmedida_id', 'alternativa' => 'unidadmedida_alter_id'],
        ['tabla' => 'articulo_proveedor', 'principal' => 'unidadmedida_compra_id', 'alternativa' => null],
        ['tabla' => 'concepto_venta', 'principal' => 'unidadmedida_id', 'alternativa' => null],
        ['tabla' => 'recepcion_proveedor_articulo', 'principal' => 'unidadmedida_id', 'alternativa' => null],
        ['tabla' => 'recuento_item', 'principal' => 'unidadmedida_id', 'alternativa' => null],
        ['tabla' => 'remito_articulo', 'principal' => 'unidadmedida_id', 'alternativa' => null],
    ];

    /**
     * @return array{
     *   mapa: array<int,int>, codigos: array<int,string>, unidad_id: ?int, kilo_id: ?int,
     *   duplicadas: list<array<string,mixed>>, tablas: list<array<string,mixed>>
     * }
     */
    public function plan(): array
    {
        $unidades = Unidadmedida::query()->orderBy('id')->get(['id', 'nombre', 'abreviatura', 'codigo']);

        $grupos = [];
        foreach ($unidades as $u) {
            $clave = UnidadmedidaSifabSupport::claveCanonica($u->nombre) ?? UnidadmedidaSifabSupport::claveCanonica($u->abreviatura);
            if ($clave !== null) {
                $grupos[$clave][] = $u;
            }
        }

        $mapa = [];
        $codigos = [];
        $duplicadas = [];
        $unidadId = null;
        $kiloId = null;
        foreach ($grupos as $clave => $miembros) {
            $sobrevive = $miembros[0];
            if ($clave === 'UNIDAD') {
                $unidadId = (int) $sobrevive->id;
            }
            if ($clave === 'KILO') {
                $kiloId = (int) $sobrevive->id;
            }
            foreach ($miembros as $m) {
                $codigo = trim((string) $m->codigo);
                if ($codigo !== '' && ! isset($codigos[(int) $sobrevive->id])) {
                    $codigos[(int) $sobrevive->id] = $codigo;
                }
                if ((int) $m->id !== (int) $sobrevive->id) {
                    $mapa[(int) $m->id] = (int) $sobrevive->id;
                    $duplicadas[] = [
                        'id' => (int) $m->id,
                        'nombre' => $m->nombre,
                        'abreviatura' => $m->abreviatura,
                        'codigo' => $m->codigo,
                        'pasa_a' => (int) $sobrevive->id,
                        'pasa_a_nombre' => $sobrevive->nombre,
                    ];
                }
            }
        }

        $tablas = [];
        foreach (self::COLUMNAS as $c) {
            $fila = ['tabla' => $c['tabla'], 'remapear_principal' => 0, 'remapear_alternativa' => 0, 'corregir_alternativa' => 0];
            if ($mapa !== []) {
                $fila['remapear_principal'] = DB::table($c['tabla'])->whereIn($c['principal'], array_keys($mapa))->count();
                if ($c['alternativa'] !== null) {
                    $fila['remapear_alternativa'] = DB::table($c['tabla'])->whereIn($c['alternativa'], array_keys($mapa))->count();
                }
            }
            if ($c['alternativa'] !== null && $unidadId !== null && $kiloId !== null) {
                $fila['corregir_alternativa'] = DB::table($c['tabla'])
                    ->whereRaw($this->condicionCorregir($c['principal'], $c['alternativa'], $mapa, $unidadId, $kiloId))
                    ->count();
            }
            $tablas[] = $fila;
        }

        return [
            'mapa' => $mapa, 'codigos' => $codigos, 'unidad_id' => $unidadId, 'kilo_id' => $kiloId,
            'duplicadas' => $duplicadas, 'tablas' => $tablas,
        ];
    }

    /**
     * Casos fuera de la regla que NO se tocan (principal distinta de Unidades/Kilos con alternativa cargada).
     *
     * @return array<string,int> "principal->alternativa" => cantidad (ya con el mapa aplicado)
     */
    public function casosNoCubiertos(array $plan): array
    {
        $mapa = $plan['mapa'];
        $p = $this->caseMapa('unidadmedida_id', $mapa);
        $a = $this->caseMapa('unidadmedidaalternativa_id', $mapa);
        $rows = DB::table('articulo')
            ->whereNotNull('unidadmedidaalternativa_id')
            ->where(function ($q) use ($p, $plan) {
                $q->whereRaw("($p) IS NULL")
                    ->orWhereRaw("($p) NOT IN (?, ?)", [$plan['unidad_id'], $plan['kilo_id']]);
            })
            ->selectRaw("($p) as p, ($a) as a, count(*) as n")
            ->groupByRaw("($p), ($a)")
            ->get();

        $out = [];
        foreach ($rows as $r) {
            $out[($r->p ?? 'sin principal').' -> '.$r->a] = (int) $r->n;
        }

        return $out;
    }

    /**
     * @return array{csv: string, tablas: list<array<string,mixed>>}
     */
    public function ejecutar(array $plan): array
    {
        if ($plan['unidad_id'] === null || $plan['kilo_id'] === null) {
            throw new RuntimeException('No se encontró la unidad Unidades o Kilos entre las unidades de medida.');
        }

        $mapa = $plan['mapa'];
        $csvPath = storage_path('app/backups/unificar_unidades_'.date('Ymd_His').'.csv');
        $fh = fopen($csvPath, 'w');
        fputcsv($fh, ['tabla', 'id', 'principal_anterior', 'alternativa_anterior']);

        DB::transaction(function () use ($plan, $mapa, $fh) {
            foreach (self::COLUMNAS as $c) {
                $p = $c['principal'];
                $a = $c['alternativa'];
                $cambia = [];
                if ($mapa !== []) {
                    $cambia[] = $this->caseMapa($p, $mapa)." <> $p";
                    if ($a !== null) {
                        $cambia[] = "($a IS NOT NULL AND ".$this->caseMapa($a, $mapa)." <> $a)";
                    }
                }
                if ($a !== null) {
                    $cambia[] = '('.$this->condicionCorregir($p, $a, $mapa, $plan['unidad_id'], $plan['kilo_id']).')';
                }
                if ($cambia === []) {
                    continue;
                }
                $where = implode(' OR ', $cambia);

                // Estado previo.
                DB::table($c['tabla'])->whereRaw($where)->orderBy('id')->select(array_filter(['id', $p, $a]))
                    ->chunk(2000, function ($rows) use ($fh, $c, $p, $a) {
                        foreach ($rows as $r) {
                            fputcsv($fh, [$c['tabla'], $r->id, $r->{$p}, $a !== null ? $r->{$a} : '']);
                        }
                    });

                // Alternativa primero: sus expresiones leen la principal original.
                $set = [];
                if ($a !== null) {
                    $nuevaAlt = $this->caseMapa($a, $mapa);
                    $contra = $this->contraparte($this->caseMapa($p, $mapa), $plan['unidad_id'], $plan['kilo_id']);
                    $set[] = "$a = IF(".$this->condicionCorregir($p, $a, $mapa, $plan['unidad_id'], $plan['kilo_id']).", $contra, $nuevaAlt)";
                }
                $set[] = "$p = ".$this->caseMapa($p, $mapa);
                DB::update('UPDATE '.$c['tabla'].' SET '.implode(', ', $set).' WHERE '.$where);
            }

            foreach ($plan['codigos'] as $id => $codigo) {
                DB::table('unidadmedida')->where('id', $id)->update(['codigo' => $codigo]);
            }

            if ($mapa !== []) {
                // Antes de borrar, que no quede ninguna referencia.
                foreach (self::COLUMNAS as $c) {
                    foreach (array_filter([$c['principal'], $c['alternativa']]) as $col) {
                        if (DB::table($c['tabla'])->whereIn($col, array_keys($mapa))->exists()) {
                            throw new RuntimeException("Quedan referencias a unidades duplicadas en {$c['tabla']}.$col");
                        }
                    }
                }
                EloquentAuditDeleteSupport::each(Unidadmedida::query()->whereIn('id', array_keys($mapa)));
            }
        });

        fclose($fh);

        return ['csv' => $csvPath, 'tablas' => $this->plan()['tablas']];
    }

    /** @param array<int,int> $mapa */
    private function caseMapa(string $columna, array $mapa): string
    {
        if ($mapa === []) {
            return $columna;
        }
        $whens = '';
        foreach ($mapa as $desde => $hacia) {
            $whens .= ' WHEN '.(int) $desde.' THEN '.(int) $hacia;
        }

        return "CASE $columna$whens ELSE $columna END";
    }

    private function contraparte(string $exprPrincipal, int $unidadId, int $kiloId): string
    {
        return "CASE ($exprPrincipal) WHEN $unidadId THEN $kiloId ELSE $unidadId END";
    }

    /**
     * Alternativa cargada y distinta de la contraparte, con principal Unidades o Kilos (ya unificada).
     *
     * @param array<int,int> $mapa
     */
    private function condicionCorregir(string $p, string $a, array $mapa, int $unidadId, int $kiloId): string
    {
        $mp = $this->caseMapa($p, $mapa);
        $ma = $this->caseMapa($a, $mapa);

        return "($a IS NOT NULL AND ($mp) IN ($unidadId, $kiloId) AND ($ma) <> ".$this->contraparte($mp, $unidadId, $kiloId).')';
    }
}
