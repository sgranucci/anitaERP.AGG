<?php

namespace App\Services\Ventas;

use App\ApiAnita;
use App\Models\Configuracion\Empresa;
use App\Models\Configuracion\Localidad;
use App\Models\Configuracion\Pais;
use App\Models\Configuracion\Provincia;
use App\Models\Ventas\Puntoventa;
use App\Repositories\Ventas\PuntoventaRepositoryInterface;
use App\Support\Stock\AnitaSync\Puntoventa\PuntoventaFieldMapper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PuntoventaAnitaSyncService
{
    public function __construct(
        private readonly PuntoventaRepositoryInterface $puntoventaRepository,
    ) {}

    /**
     * @return array{en_anita:int, importados:int, actualizados:int, omitidos:int, errores:list<string>}
     */
    public function sincronizarConAnita(): array
    {
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $ret = ['en_anita' => 0, 'importados' => 0, 'actualizados' => 0, 'omitidos' => 0, 'errores' => []];
        $lista = $this->listarDesdeAnita();
        $ret['en_anita'] = count($lista);

        foreach ($lista as $row) {
            $codigoAnita = PuntoventaFieldMapper::mapCodigo($row);
            if ($codigoAnita === null) {
                $ret['omitidos']++;

                continue;
            }

            try {
                $estado = $this->importarFila($row, $codigoAnita);
                if ($estado === 'importado') {
                    $ret['importados']++;
                } elseif ($estado === 'actualizado') {
                    $ret['actualizados']++;
                } else {
                    $ret['omitidos']++;
                }
            } catch (\Throwable $e) {
                $msg = "Punto de venta Anita suc_numero={$codigoAnita}: ".$e->getMessage();
                $ret['errores'][] = $msg;
                Log::warning('PuntoventaAnitaSync: '.$msg, ['exception' => $e]);
            }
        }

        return $ret;
    }

    /**
     * @return 'importado'|'actualizado'|'omitido'
     */
    public function traerRegistroDeAnita(int $sucNumero): string
    {
        $row = $this->leerSucursalEnAnita($sucNumero);
        if ($row === null) {
            return 'omitido';
        }

        $codigoAnita = PuntoventaFieldMapper::mapCodigo($row);
        if ($codigoAnita === null) {
            return 'omitido';
        }

        return $this->importarFila($row, $codigoAnita);
    }

    /**
     * @return list<object>
     */
    public function listarDesdeAnita(): array
    {
        $api = new ApiAnita;
        $payload = [
            'acc' => 'list',
            'tabla' => config('puntoventa_anita.tabla', 'sucursal'),
            'campos' => config('puntoventa_anita.campos_listado'),
            'orderBy' => 'suc_numero',
        ];
        $sistema = config('puntoventa_anita.sistema');
        if ($sistema !== null && $sistema !== '') {
            $payload['sistema'] = $sistema;
        }

        $raw = $api->apiCall($payload);
        $rows = json_decode((string) $raw);
        if (! is_array($rows)) {
            Log::warning('PuntoventaAnitaSync listar: JSON inválido o vacío', [
                'json_error' => json_last_error_msg(),
                'raw_prefix' => substr((string) $raw, 0, 300),
            ]);

            return [];
        }

        return $rows;
    }

    private function leerSucursalEnAnita(int $sucNumero): ?object
    {
        $api = new ApiAnita;
        $payload = [
            'acc' => 'list',
            'tabla' => config('puntoventa_anita.tabla', 'sucursal'),
            'campos' => config('puntoventa_anita.campos_listado'),
            'whereArmado' => " WHERE suc_numero={$sucNumero}",
        ];
        $sistema = config('puntoventa_anita.sistema');
        if ($sistema !== null && $sistema !== '') {
            $payload['sistema'] = $sistema;
        }

        $raw = $api->apiCall($payload);
        $rows = json_decode((string) $raw);
        if (! is_array($rows) || count($rows) === 0) {
            return null;
        }

        return $rows[0];
    }

    /**
     * @return 'importado'|'actualizado'|'omitido'
     */
    private function importarFila(object $row, string $codigoAnita): string
    {
        $payload = PuntoventaFieldMapper::mapAll($row);
        $this->validarPayloadMinimo($payload, $codigoAnita);

        $empresaId = (int) $payload['empresa_id'];
        $codigoNorm = trim((string) $codigoAnita);

        $existente = Puntoventa::withTrashed()
            ->where('empresa_id', $empresaId)
            ->whereRaw('TRIM(codigo) = ?', [$codigoNorm])
            ->orderByRaw('CASE WHEN deleted_at IS NULL THEN 0 ELSE 1 END')
            ->orderBy('id')
            ->first();

        $datos = $payload;
        $datos['codigo'] = $codigoNorm;
        $datos['empresa_id'] = $empresaId;

        DB::beginTransaction();
        try {
            if ($existente) {
                if ($existente->trashed()) {
                    $existente->restore();
                }
                $this->puntoventaRepository->update($datos, $existente->id);
                DB::commit();

                return 'actualizado';
            }

            $this->puntoventaRepository->create($datos);
            DB::commit();

            return 'importado';
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function validarPayloadMinimo(array $payload, string $codigoAnita): void
    {
        if (trim((string) ($payload['nombre'] ?? '')) === '') {
            throw new \InvalidArgumentException("nombre vacío (sucursal {$codigoAnita}).");
        }
        $empresaId = (int) ($payload['empresa_id'] ?? 0);
        if ($empresaId <= 0 || ! Empresa::query()->where('id', $empresaId)->exists()) {
            throw new \InvalidArgumentException("empresa_id {$empresaId} inexistente (sucursal {$codigoAnita}).");
        }
        $paisId = (int) ($payload['pais_id'] ?? 0);
        if ($paisId <= 0 || ! Pais::query()->where('id', $paisId)->exists()) {
            throw new \InvalidArgumentException("pais_id {$paisId} inexistente (sucursal {$codigoAnita}).");
        }
        $provId = (int) ($payload['provincia_id'] ?? 0);
        if ($provId > 0 && ! Provincia::query()->where('id', $provId)->exists()) {
            throw new \InvalidArgumentException("provincia_id {$provId} inexistente (sucursal {$codigoAnita}).");
        }
        $locId = (int) ($payload['localidad_id'] ?? 0);
        if ($locId > 0 && ! Localidad::query()->where('id', $locId)->exists()) {
            throw new \InvalidArgumentException("localidad_id {$locId} inexistente (sucursal {$codigoAnita}).");
        }
    }
}
