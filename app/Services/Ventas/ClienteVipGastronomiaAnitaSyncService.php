<?php

namespace App\Services\Ventas;

use App\Models\Configuracion\Empresa;
use App\Models\Ventas\ClienteVipGastronomia;
use App\Support\Ventas\ClivipgAnitaBridgeSupport;
use App\Support\Ventas\ClivipgFieldMapper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ClienteVipGastronomiaAnitaSyncService
{
    /**
     * @return array{en_anita:int, importados:int, actualizados:int, omitidos:int, errores:list<string>}
     */
    public function sincronizarConAnita(): array
    {
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $ret = ['en_anita' => 0, 'importados' => 0, 'actualizados' => 0, 'omitidos' => 0, 'errores' => []];

        foreach ($this->empresasSyncOrden() as $empresaId) {
            if (! Empresa::query()->whereKey($empresaId)->exists()) {
                $ret['errores'][] = "Empresa id {$empresaId} inexistente; se omite sincronización.";
                continue;
            }

            $lista = ClivipgAnitaBridgeSupport::listar($empresaId);
            $ret['en_anita'] += count($lista);

            foreach ($lista as $row) {
                $numeroid = ClivipgFieldMapper::mapNumeroid($row);
                if ($numeroid === null) {
                    $ret['omitidos']++;

                    continue;
                }

                try {
                    $estado = $this->importarFila($row, $empresaId, $numeroid);
                    if ($estado === 'importado') {
                        $ret['importados']++;
                    } elseif ($estado === 'actualizado') {
                        $ret['actualizados']++;
                    } else {
                        $ret['omitidos']++;
                    }
                } catch (\Throwable $e) {
                    $msg = "Cliente VIP Anita empresa {$empresaId} numeroid={$numeroid}: ".$e->getMessage();
                    $ret['errores'][] = $msg;
                    Log::warning('ClienteVipGastronomiaAnitaSync: '.$msg, ['exception' => $e]);
                }
            }
        }

        return $ret;
    }

    /**
     * @return 'importado'|'actualizado'|'omitido'
     */
    public function traerRegistroDeAnita(int $empresaId, int $numeroid): string
    {
        $filas = ClivipgAnitaBridgeSupport::listar($empresaId, ' WHERE inumeroid = '.(int) $numeroid);
        if ($filas === []) {
            return 'omitido';
        }

        return $this->importarFila($filas[0], $empresaId, $numeroid);
    }

    /**
     * @return list<int>
     */
    private function empresasSyncOrden(): array
    {
        $orden = (array) config('gastronomia.cliente_vip_anita_empresas_sync', [1, 2, 3]);

        return array_values(array_filter(array_map('intval', $orden), fn (int $id) => $id > 0));
    }

    /**
     * @return 'importado'|'actualizado'|'omitido'
     */
    private function importarFila(object $row, int $empresaId, int $numeroid): string
    {
        $payload = ClivipgFieldMapper::mapAll($row, $empresaId);
        $this->validarPayloadMinimo($payload, $empresaId, $numeroid);

        $existente = ClienteVipGastronomia::query()
            ->where('empresa_id', $empresaId)
            ->where('numeroid', $numeroid)
            ->first();

        DB::beginTransaction();
        try {
            if ($existente) {
                $existente->update($payload);
                DB::commit();

                return 'actualizado';
            }

            ClienteVipGastronomia::query()->create($payload);
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
    private function validarPayloadMinimo(array $payload, int $empresaId, int $numeroid): void
    {
        if (trim((string) ($payload['apellido'] ?? '')) === '' && trim((string) ($payload['nombre'] ?? '')) === '') {
            throw new \InvalidArgumentException("apellido y nombre vacíos (numeroid {$numeroid}, empresa {$empresaId}).");
        }
        if (! Empresa::query()->whereKey($empresaId)->exists()) {
            throw new \InvalidArgumentException("empresa_id {$empresaId} inexistente (numeroid {$numeroid}).");
        }
    }
}
