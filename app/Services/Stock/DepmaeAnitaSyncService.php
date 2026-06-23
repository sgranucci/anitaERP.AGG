<?php

namespace App\Services\Stock;

use App\Models\Configuracion\Empresa;
use App\Models\Stock\Depmae;
use App\Support\Stock\DepmaeAnitaBridgeSupport;
use App\Support\Stock\DepmaeAnitaExclusionSupport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DepmaeAnitaSyncService
{
    /**
     * @return array{
     *     en_anita: int,
     *     omitidos_maquina: int,
     *     importados: int,
     *     actualizados: int,
     *     omitidos: int,
     *     errores: list<string>
     * }
     */
    public function sincronizarEmpresaDesdeAnita(int $empresaId): array
    {
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        if ($empresaId <= 0 || ! Empresa::query()->whereKey($empresaId)->exists()) {
            throw new \InvalidArgumentException("empresa_id {$empresaId} inexistente.");
        }

        $ret = [
            'en_anita' => 0,
            'omitidos_maquina' => 0,
            'importados' => 0,
            'actualizados' => 0,
            'omitidos' => 0,
            'errores' => [],
        ];

        $todas = DepmaeAnitaBridgeSupport::listar($empresaId);
        $ret['en_anita'] = count($todas);

        foreach ($todas as $row) {
            $codigo = trim((string) ($row->{DepmaeAnitaBridgeSupport::keyField()} ?? ''));
            if (DepmaeAnitaExclusionSupport::debeOmitirCodigo($codigo)) {
                $ret['omitidos_maquina']++;

                continue;
            }

            try {
                $estado = $this->importarFila($row, $empresaId, $codigo);
                if ($estado === 'importado') {
                    $ret['importados']++;
                } elseif ($estado === 'actualizado') {
                    $ret['actualizados']++;
                } else {
                    $ret['omitidos']++;
                }
            } catch (\Throwable $e) {
                $msg = "Depósito Anita empresa {$empresaId} codigo={$codigo}: ".$e->getMessage();
                $ret['errores'][] = $msg;
                Log::warning('DepmaeAnitaSync: '.$msg, ['exception' => $e]);
            }
        }

        return $ret;
    }

    /**
     * @return array{
     *     en_anita: int,
     *     omitidos_maquina: int,
     *     importados: int,
     *     actualizados: int,
     *     omitidos: int,
     *     errores: list<string>
     * }
     */
    public function sincronizarConAnita(?array $empresaIds = null): array
    {
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $ret = [
            'en_anita' => 0,
            'omitidos_maquina' => 0,
            'importados' => 0,
            'actualizados' => 0,
            'omitidos' => 0,
            'errores' => [],
        ];

        foreach ($this->empresasSyncOrden($empresaIds) as $empresaId) {
            if (! Empresa::query()->whereKey($empresaId)->exists()) {
                $ret['errores'][] = "Empresa id {$empresaId} inexistente; se omite sincronización.";

                continue;
            }

            $parcial = $this->sincronizarEmpresaDesdeAnita($empresaId);
            foreach (['en_anita', 'omitidos_maquina', 'importados', 'actualizados', 'omitidos'] as $clave) {
                $ret[$clave] += $parcial[$clave];
            }
            array_push($ret['errores'], ...$parcial['errores']);
        }

        return $ret;
    }

    /**
     * @return 'importado'|'actualizado'|'omitido'
     */
    public function traerRegistroDeAnita(int $empresaId, string $codigo): string
    {
        if (DepmaeAnitaExclusionSupport::debeOmitirCodigo($codigo)) {
            return 'omitido';
        }

        $key = DepmaeAnitaBridgeSupport::keyField();
        $filas = DepmaeAnitaBridgeSupport::listar(
            $empresaId,
            " WHERE {$key} = '".addslashes($codigo)."' "
        );
        if ($filas === []) {
            return 'omitido';
        }

        return $this->importarFila($filas[0], $empresaId, $codigo);
    }

    /**
     * @return list<int>
     */
    private function empresasSyncOrden(?array $empresaIds): array
    {
        if ($empresaIds !== null) {
            return array_values(array_filter(array_map('intval', $empresaIds), fn (int $id) => $id > 0));
        }

        $orden = (array) config('stock.depmae_anita_empresas_sync', [1, 2, 3]);

        return array_values(array_filter(array_map('intval', $orden), fn (int $id) => $id > 0));
    }

    /**
     * @return 'importado'|'actualizado'|'omitido'
     */
    private function importarFila(object $row, int $empresaId, string $codigo): string
    {
        if ($codigo === '') {
            return 'omitido';
        }

        $payload = DepmaeAnitaBridgeSupport::mapPayload($row);
        if ($payload['nombre'] === '') {
            throw new \InvalidArgumentException('depm_desc vacío.');
        }

        $payload['empresa_id'] = $empresaId;

        $existente = Depmae::query()
            ->where('empresa_id', $empresaId)
            ->where('codigo', $codigo)
            ->first();

        DB::beginTransaction();
        try {
            if ($existente) {
                $existente->update([
                    'nombre' => $payload['nombre'],
                    'tipodeposito' => $payload['tipodeposito'],
                ]);
                DB::commit();

                return 'actualizado';
            }

            Depmae::query()->create($payload);
            DB::commit();

            return 'importado';
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
