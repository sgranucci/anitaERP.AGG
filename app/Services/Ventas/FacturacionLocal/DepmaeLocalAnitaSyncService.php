<?php

namespace App\Services\Ventas\FacturacionLocal;

use App\Models\Stock\Depmae;
use App\Models\Ventas\LocalVenta;
use App\Support\Stock\DepmaeAnitaBridgeSupport;
use App\Support\Stock\DepmaeAnitaExclusionSupport;
use App\Support\Ventas\FacturacionLocal\DepmaeLocalAnitaBridgeSupport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Importa depósitos del Anita Local al ERP sin pisar depósitos de fábrica.
 * Solo crea filas nuevas; nunca actualiza un depmae preexistente.
 */
final class DepmaeLocalAnitaSyncService
{
    /**
     * @return array{
     *   en_anita:int,
     *   importados:int,
     *   omitidos_existentes:int,
     *   omitidos_maquina:int,
     *   omitidos:int,
     *   errores:list<string>,
     *   creados:list<array{id:int,codigo:string,nombre:string,anita_codigo:string}>
     * }
     */
    public function sincronizarDesdeLocal(LocalVenta $local): array
    {
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $empresaId = (int) ($local->empresa_id ?: 0);
        if ($empresaId <= 0) {
            throw new \InvalidArgumentException('El local debe tener empresa para importar depósitos.');
        }

        $ret = [
            'en_anita' => 0,
            'importados' => 0,
            'omitidos_existentes' => 0,
            'omitidos_maquina' => 0,
            'omitidos' => 0,
            'errores' => [],
            'creados' => [],
        ];

        $filas = DepmaeLocalAnitaBridgeSupport::listar($local);
        $ret['en_anita'] = count($filas);
        $prefijoLocal = $this->prefijoCodigoLocal($local);

        foreach ($filas as $row) {
            $anitaCodigo = trim((string) ($row->{DepmaeAnitaBridgeSupport::keyField()} ?? ''));
            if ($anitaCodigo === '') {
                $ret['omitidos']++;

                continue;
            }
            if (DepmaeAnitaExclusionSupport::debeOmitirCodigo($anitaCodigo)) {
                $ret['omitidos_maquina']++;

                continue;
            }

            try {
                $resultado = $this->importarFilaSinPisar($row, $empresaId, $anitaCodigo, $prefijoLocal);
                if ($resultado === null) {
                    $ret['omitidos_existentes']++;
                } else {
                    $ret['importados']++;
                    $ret['creados'][] = $resultado;
                }
            } catch (\Throwable $e) {
                $msg = "Depósito Anita local codigo={$anitaCodigo}: ".$e->getMessage();
                $ret['errores'][] = $msg;
                Log::warning('DepmaeLocalAnitaSync: '.$msg, ['exception' => $e, 'local_id' => $local->id]);
            }
        }

        return $ret;
    }

    /**
     * @return array{id:int,codigo:string,nombre:string,anita_codigo:string}|null null = ya existía (no se tocó)
     */
    private function importarFilaSinPisar(object $row, int $empresaId, string $anitaCodigo, string $prefijoLocal): ?array
    {
        $payload = DepmaeAnitaBridgeSupport::mapPayload($row);
        if ($payload['nombre'] === '') {
            throw new \InvalidArgumentException('depm_desc vacío.');
        }

        $codigoErp = $this->codigoErpSinColision($anitaCodigo, $empresaId, $prefijoLocal);
        if ($codigoErp === null) {
            return null;
        }

        if ($codigoErp !== $anitaCodigo) {
            $payload['nombre'] = mb_substr($payload['nombre'].' (local '.$anitaCodigo.')', 0, 50);
        }

        $payload['codigo'] = $codigoErp;
        $payload['empresa_id'] = $empresaId;

        DB::beginTransaction();
        try {
            // Releer por carrera: jamás update.
            if (Depmae::query()->where('empresa_id', $empresaId)->where('codigo', $codigoErp)->exists()) {
                DB::rollBack();

                return null;
            }

            $dep = Depmae::query()->create($payload);
            DB::commit();

            return [
                'id' => (int) $dep->id,
                'codigo' => (string) $dep->codigo,
                'nombre' => (string) $dep->nombre,
                'anita_codigo' => $anitaCodigo,
            ];
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Preferir el código Anita tal cual; si ya existe en la empresa (fábrica),
     * usar prefijo del local. Si ese también existe, no crear (omitir).
     */
    private function codigoErpSinColision(string $anitaCodigo, int $empresaId, string $prefijoLocal): ?string
    {
        $codigoDirecto = mb_substr($anitaCodigo, 0, 10);
        if (! Depmae::query()->where('empresa_id', $empresaId)->where('codigo', $codigoDirecto)->exists()) {
            return $codigoDirecto;
        }

        $sufijo = mb_substr($anitaCodigo, 0, max(1, 10 - mb_strlen($prefijoLocal)));
        $codigoPrefijado = mb_substr($prefijoLocal.$sufijo, 0, 10);
        if ($codigoPrefijado === '' || $codigoPrefijado === $codigoDirecto) {
            return null;
        }
        if (Depmae::query()->where('empresa_id', $empresaId)->where('codigo', $codigoPrefijado)->exists()) {
            return null;
        }

        return $codigoPrefijado;
    }

    private function prefijoCodigoLocal(LocalVenta $local): string
    {
        $cod = preg_replace('/[^A-Za-z0-9]/', '', (string) $local->codigo) ?: 'L';
        $cod = mb_strtoupper(mb_substr($cod, 0, 3));

        return $cod !== '' ? $cod : 'L';
    }
}
