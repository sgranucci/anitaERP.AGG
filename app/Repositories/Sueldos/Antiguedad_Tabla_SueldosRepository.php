<?php

namespace App\Repositories\Sueldos;

use App\ApiAnita;
use App\Models\Sueldos\Antiguedad_Tabla_Sueldos;
use App\Models\Sueldos\Antiguedad_Tabla_Tramo_Sueldos;
use App\Support\Sueldos\AntiguedadTablaResolver;
use App\Support\Sueldos\AntiguedadTablaSueldosListadoFiltros;
use Illuminate\Support\Facades\DB;

class Antiguedad_Tabla_SueldosRepository implements Antiguedad_Tabla_SueldosRepositoryInterface
{
    protected Antiguedad_Tabla_Sueldos $model;

    public function __construct(Antiguedad_Tabla_Sueldos $model)
    {
        $this->model = $model;
    }

    public function leeAntiguedadTabla($filtros, $flPaginando = null)
    {
        if (is_string($filtros)) {
            $texto = trim($filtros);
            $filtros = [
                'modo' => AntiguedadTablaSueldosListadoFiltros::MODO_TODOS,
                'campo' => 'descripcion',
                'operador' => 'contiene',
                'valor' => $texto,
                'valor_hasta' => '',
                'busqueda' => $texto,
            ];
        } elseif (! is_array($filtros)) {
            $filtros = AntiguedadTablaSueldosListadoFiltros::filtrosVacios();
        }

        $query = $this->model->newQuery()
            ->select('antiguedad_tabla_sueldos.*')
            ->withCount('tramos');

        if (AntiguedadTablaSueldosListadoFiltros::tieneCriteriosAplicados($filtros)) {
            AntiguedadTablaSueldosListadoFiltros::aplicar($query, $filtros);
        }

        $query->orderBy('antiguedad_tabla_sueldos.codigo');

        if (isset($flPaginando)) {
            return $flPaginando ? $query->paginate(15) : $query->get();
        }

        return $query->get();
    }

    public function create(array $data)
    {
        return DB::transaction(function () use ($data) {
            $tramos = $data['tramos'] ?? [];
            unset($data['tramos']);
            $tabla = $this->model->create($this->normalizarPayload($data));
            $this->syncTramos($tabla, is_array($tramos) ? $tramos : []);
            AntiguedadTablaResolver::forgetCache((int) $tabla->codigo);

            return $tabla->load('tramos');
        });
    }

    public function update(array $data, $id)
    {
        return DB::transaction(function () use ($data, $id) {
            $registro = $this->model->findOrFail($id);
            $codigoAnterior = (int) $registro->codigo;
            $tramos = $data['tramos'] ?? [];
            unset($data['tramos']);
            $registro->update($this->normalizarPayload($data, $registro));
            $this->syncTramos($registro, is_array($tramos) ? $tramos : []);
            AntiguedadTablaResolver::forgetCache($codigoAnterior);
            AntiguedadTablaResolver::forgetCache((int) $registro->codigo);

            return $registro->load('tramos');
        });
    }

    public function delete($id)
    {
        $registro = $this->model->find($id);
        if ($registro === null) {
            return false;
        }
        $codigo = (int) $registro->codigo;
        $ok = (bool) $this->model->destroy($id);
        if ($ok) {
            AntiguedadTablaResolver::forgetCache($codigo);
        }

        return $ok;
    }

    public function findOrFail($id)
    {
        return $this->model->with('tramos')->findOrFail($id);
    }

    public function sincronizarConAnita(): array
    {
        $resultado = ['en_anita' => 0, 'tablas' => 0, 'tramos' => 0, 'errores' => []];

        try {
            $api = new ApiAnita();
            $raw = $api->apiCall([
                'acc' => 'list',
                'sistema' => 'sueldos',
                'tabla' => 'antmov',
                'campos' => 'antv_codigo, antv_anio, antv_porcentaje, antv_cantidad',
                'orderBy' => 'antv_codigo',
            ]);
            $filas = ApiAnita::decodificarListaFilas(is_string($raw) ? $raw : null);
        } catch (\Throwable $e) {
            $resultado['errores'][] = $e->getMessage();

            return $resultado;
        }

        $porCodigo = [];
        foreach ($filas as $f) {
            $f = (array) $f;
            $cod = (int) ($f['antv_codigo'] ?? 0);
            $anio = (int) ($f['antv_anio'] ?? 0);
            if ($cod <= 0 || $anio <= 0) {
                continue;
            }
            $resultado['en_anita']++;
            $porCodigo[$cod][] = [
                'anio' => $anio,
                'porcentaje' => (float) ($f['antv_porcentaje'] ?? 0),
                'cantidad' => (float) ($f['antv_cantidad'] ?? 0),
            ];
        }

        DB::transaction(function () use ($porCodigo, &$resultado) {
            foreach ($porCodigo as $codigo => $tramos) {
                usort($tramos, fn ($a, $b) => $a['anio'] <=> $b['anio']);
                $tabla = Antiguedad_Tabla_Sueldos::query()
                    ->where('codigo', $codigo)
                    ->whereNull('empresa_id')
                    ->first();
                if ($tabla === null) {
                    $tabla = Antiguedad_Tabla_Sueldos::create([
                        'empresa_id' => null,
                        'codigo' => $codigo,
                        'descripcion' => 'Tabla antigüedad '.$codigo,
                        'activo' => true,
                    ]);
                    $resultado['tablas']++;
                } else {
                    $tabla->update(['activo' => true]);
                    $resultado['tablas']++;
                }

                Antiguedad_Tabla_Tramo_Sueldos::query()
                    ->where('antiguedad_tabla_id', $tabla->id)
                    ->delete();

                $nro = 0;
                foreach ($tramos as $t) {
                    $nro++;
                    Antiguedad_Tabla_Tramo_Sueldos::create([
                        'antiguedad_tabla_id' => $tabla->id,
                        'anio' => $t['anio'],
                        'porcentaje' => $t['porcentaje'],
                        'cantidad' => $t['cantidad'],
                        'nro_linea' => $nro,
                    ]);
                    $resultado['tramos']++;
                }
                AntiguedadTablaResolver::forgetCache($codigo);
            }
        });

        return $resultado;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizarPayload(array $data, ?Antiguedad_Tabla_Sueldos $existente = null): array
    {
        return [
            'empresa_id' => isset($data['empresa_id']) && $data['empresa_id'] !== ''
                ? (int) $data['empresa_id'] : ($existente->empresa_id ?? null),
            'codigo' => (int) $data['codigo'],
            'descripcion' => trim((string) $data['descripcion']),
            'activo' => (bool) ($data['activo'] ?? true),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $tramos
     */
    private function syncTramos(Antiguedad_Tabla_Sueldos $tabla, array $tramos): void
    {
        Antiguedad_Tabla_Tramo_Sueldos::query()
            ->where('antiguedad_tabla_id', $tabla->id)
            ->delete();

        $nro = 0;
        foreach ($tramos as $t) {
            if (! is_array($t)) {
                continue;
            }
            $anio = (int) ($t['anio'] ?? 0);
            if ($anio <= 0) {
                continue;
            }
            $nro++;
            Antiguedad_Tabla_Tramo_Sueldos::create([
                'antiguedad_tabla_id' => $tabla->id,
                'anio' => $anio,
                'porcentaje' => (float) ($t['porcentaje'] ?? 0),
                'cantidad' => (float) ($t['cantidad'] ?? 0),
                'nro_linea' => (int) ($t['nro_linea'] ?? $nro) ?: $nro,
            ]);
        }
    }
}
