<?php

namespace App\Repositories\Ventas;

use App\ApiAnita;
use App\Models\Ventas\Cai;
use App\Support\Ventas\CaiAnitaFechaSupport;
use App\Traits\AnitaBridgeEscritura;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class CaiRepository implements CaiRepositoryInterface
{
    use AnitaBridgeEscritura;

    protected $model;

    protected string $tableAnita = 'cai';

    /** Solo remitos ARCA (letra R). */
    private const LETRA_REMITO = 'R';

    private const TIPO_REMITO = 'REM';

    private const DESC_REMITO = 'Remito';

    public function __construct(Cai $cai)
    {
        $this->model = $cai;
    }

    public function all()
    {
        $this->sincronizarConAnita();

        return $this->model->newQuery()
            ->where('letra', self::LETRA_REMITO)
            ->orderByDesc('orden')
            ->get();
    }

    public function create(array $data)
    {
        $payload = $this->normalizarPayload($data, null);
        $registro = $this->model->create($payload);
        $this->guardarAnita($payload);

        return $registro;
    }

    public function update(array $data, $id)
    {
        $registro = $this->model->findOrFail($id);
        $payload = $this->normalizarPayload($data, $registro);
        $registro->update($payload);
        $this->actualizarAnita($payload, (int) $registro->orden);

        return $registro;
    }

    public function delete($id)
    {
        $registro = $this->model->find($id);
        if ($registro === null) {
            return false;
        }

        $this->eliminarAnita((int) $registro->orden);

        return (bool) $this->model->destroy($id);
    }

    public function find($id)
    {
        if (null == $registro = $this->model->find($id)) {
            throw new ModelNotFoundException('Registro no encontrado');
        }

        return $registro;
    }

    public function findOrFail($id)
    {
        return $this->model->findOrFail($id);
    }

    public function sincronizarConAnita()
    {
        ini_set('max_execution_time', '300');

        $apiAnita = new ApiAnita();
        $data = [
            'acc' => 'list',
            'sistema' => 'ventas',
            'tabla' => $this->tableAnita,
            'campos' => 'cai_orden, cai_tipo, cai_desc, cai_letra, cai_sucursal, cai_nro_cai, cai_fecha_vto',
            'whereArmado' => " WHERE cai_letra = '".self::LETRA_REMITO."' ",
        ];
        $parsed = ApiAnita::parsearRespuestaLista($apiAnita->apiCall($data));
        $filas = $parsed['filas'];

        foreach ($filas as $row) {
            $orden = (int) ($row->cai_orden ?? 0);
            if ($orden <= 0) {
                continue;
            }

            $letra = strtoupper(trim((string) ($row->cai_letra ?? '')));
            if ($letra !== self::LETRA_REMITO) {
                continue;
            }

            $fecha = CaiAnitaFechaSupport::fechaDesdeAnita($row->cai_fecha_vto ?? 0);
            if ($fecha === null) {
                continue;
            }

            $attrs = [
                'orden' => $orden,
                'tipo' => $this->recortar(trim((string) ($row->cai_tipo ?? self::TIPO_REMITO)), 3) ?: self::TIPO_REMITO,
                'descripcion' => $this->recortar(trim((string) ($row->cai_desc ?? self::DESC_REMITO)), 30) ?: self::DESC_REMITO,
                'letra' => self::LETRA_REMITO,
                'sucursal' => (int) ($row->cai_sucursal ?? 0),
                'numero_cai' => $this->recortar(trim((string) ($row->cai_nro_cai ?? '')), 18),
                'fecha_vencimiento' => $fecha,
            ];

            if ($attrs['numero_cai'] === '') {
                continue;
            }

            $existente = $this->model->newQuery()->where('orden', $orden)->first();
            if ($existente) {
                $existente->update($attrs);
            } else {
                $this->model->create($attrs);
            }
        }
    }

    public function guardarAnita(array $data): void
    {
        $apiAnita = new ApiAnita();
        $desc = $this->escaparAnita((string) ($data['descripcion'] ?? self::DESC_REMITO));
        $nro = $this->escaparAnita((string) ($data['numero_cai'] ?? ''));
        $tipo = $this->escaparAnita((string) ($data['tipo'] ?? self::TIPO_REMITO));
        $fechaAnita = CaiAnitaFechaSupport::fechaAnitaDesde($data['fecha_vencimiento'] ?? null);

        $payload = [
            'tabla' => $this->tableAnita,
            'sistema' => 'ventas',
            'acc' => 'insert',
            'campos' => 'cai_orden, cai_tipo, cai_desc, cai_letra, cai_sucursal, cai_nro_cai, cai_fecha_vto',
            'valores' => (int) $data['orden'].", '".$tipo."', '".$desc."', '".self::LETRA_REMITO."', "
                .(int) $data['sucursal'].", '".$nro."', ".$fechaAnita,
        ];
        $this->apiCallAnitaEscritura($apiAnita, $payload, 'cai insert');
    }

    public function actualizarAnita(array $data, int $orden): void
    {
        $apiAnita = new ApiAnita();
        $desc = $this->escaparAnita((string) ($data['descripcion'] ?? self::DESC_REMITO));
        $nro = $this->escaparAnita((string) ($data['numero_cai'] ?? ''));
        $tipo = $this->escaparAnita((string) ($data['tipo'] ?? self::TIPO_REMITO));
        $fechaAnita = CaiAnitaFechaSupport::fechaAnitaDesde($data['fecha_vencimiento'] ?? null);

        $payload = [
            'acc' => 'update',
            'tabla' => $this->tableAnita,
            'sistema' => 'ventas',
            'valores' => "cai_tipo = '".$tipo."', "
                ."cai_desc = '".$desc."', "
                ."cai_letra = '".self::LETRA_REMITO."', "
                .'cai_sucursal = '.(int) $data['sucursal'].', '
                ."cai_nro_cai = '".$nro."', "
                .'cai_fecha_vto = '.$fechaAnita,
            'whereArmado' => ' WHERE cai_orden = '.$orden.' ',
        ];
        $this->apiCallAnitaEscritura($apiAnita, $payload, 'cai update');
    }

    public function eliminarAnita(int $orden): void
    {
        $apiAnita = new ApiAnita();
        $payload = [
            'acc' => 'delete',
            'tabla' => $this->tableAnita,
            'sistema' => 'ventas',
            'whereArmado' => ' WHERE cai_orden = '.$orden.' ',
        ];
        $this->apiCallAnitaEscritura($apiAnita, $payload, 'cai delete');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizarPayload(array $data, ?Cai $existente): array
    {
        $orden = $existente !== null
            ? (int) $existente->orden
            : $this->proximoOrden();

        return [
            'orden' => $orden,
            'tipo' => $this->recortar(trim((string) ($data['tipo'] ?? self::TIPO_REMITO)), 3) ?: self::TIPO_REMITO,
            'descripcion' => $this->recortar(trim((string) ($data['descripcion'] ?? self::DESC_REMITO)), 30) ?: self::DESC_REMITO,
            'letra' => self::LETRA_REMITO,
            'sucursal' => (int) ($data['sucursal'] ?? 1),
            'numero_cai' => $this->recortar(trim((string) ($data['numero_cai'] ?? '')), 18),
            'fecha_vencimiento' => $data['fecha_vencimiento'] ?? null,
        ];
    }

    private function proximoOrden(): int
    {
        $apiAnita = new ApiAnita();
        $data = [
            'acc' => 'list',
            'sistema' => 'ventas',
            'tabla' => $this->tableAnita,
            'campos' => 'max(cai_orden) as max_orden',
        ];
        $parsed = ApiAnita::parsearRespuestaLista($apiAnita->apiCall($data));
        $maxAnita = 0;
        if ($parsed['filas'] !== []) {
            $maxAnita = (int) ($parsed['filas'][0]->max_orden ?? 0);
        }

        $maxLocal = (int) ($this->model->newQuery()->max('orden') ?? 0);

        return max($maxAnita, $maxLocal) + 1;
    }

    private function escaparAnita(string $valor): string
    {
        return str_replace("'", "''", $valor);
    }

    private function recortar(string $valor, int $len): string
    {
        if (function_exists('mb_substr')) {
            return mb_substr($valor, 0, $len);
        }

        return substr($valor, 0, $len);
    }
}
