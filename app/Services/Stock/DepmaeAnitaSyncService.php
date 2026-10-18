<?php

namespace App\Services\Stock;

use App\Models\Configuracion\Empresa;
use App\Models\Stock\Depmae;
use App\Support\Stock\DepmaeAnitaBridgeSupport;
use App\Support\Stock\DepmaeAnitaExclusionSupport;
use App\Support\Stock\DepmaeAnitaFuentesSupport;
use App\Support\Ventas\GastronomiaSalaSupport;
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
     *     errores: list<string>,
     *     fuentes?: list<array<string, mixed>>
     * }
     */
    public function sincronizarEmpresaDesdeAnita(int $empresaId): array
    {
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        if ($empresaId <= 0 || ! Empresa::query()->whereKey($empresaId)->exists()) {
            throw new \InvalidArgumentException("empresa_id {$empresaId} inexistente.");
        }

        $ret = $this->retVacio();

        $todas = DepmaeAnitaBridgeSupport::listar($empresaId);
        $ret['en_anita'] = count($todas);

        foreach ($todas as $row) {
            $codigo = trim((string) ($row->{DepmaeAnitaBridgeSupport::keyField()} ?? ''));
            if (DepmaeAnitaExclusionSupport::debeOmitirCodigo($codigo)) {
                $ret['omitidos_maquina']++;

                continue;
            }

            try {
                $estado = $this->importarFila($row, $empresaId, $codigo, null);
                $this->acumularEstado($ret, $estado);
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
     *     errores: list<string>,
     *     fuentes?: list<array<string, mixed>>
     * }
     */
    public function sincronizarConAnita(?array $empresaIds = null, bool $dryRun = false): array
    {
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        if (DepmaeAnitaFuentesSupport::usaFuentesPorSala()) {
            return $this->sincronizarFuentesPorSala($dryRun);
        }

        $ret = $this->retVacio();

        foreach ($this->empresasSyncOrden($empresaIds) as $empresaId) {
            if (! Empresa::query()->whereKey($empresaId)->exists()) {
                $ret['errores'][] = "Empresa id {$empresaId} inexistente; se omite sincronización.";

                continue;
            }

            if ($dryRun) {
                $todas = DepmaeAnitaBridgeSupport::listar($empresaId);
                $ret['en_anita'] += count($todas);
                foreach ($todas as $row) {
                    $codigo = trim((string) ($row->{DepmaeAnitaBridgeSupport::keyField()} ?? ''));
                    if (DepmaeAnitaExclusionSupport::debeOmitirCodigo($codigo)) {
                        $ret['omitidos_maquina']++;

                        continue;
                    }
                    $existente = Depmae::query()
                        ->where('empresa_id', $empresaId)
                        ->where('codigo', $codigo)
                        ->whereNull('sala_id')
                        ->exists();
                    if ($existente) {
                        $ret['actualizados']++;
                    } else {
                        $ret['importados']++;
                    }
                }

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
     * @return array{
     *     en_anita: int,
     *     omitidos_maquina: int,
     *     importados: int,
     *     actualizados: int,
     *     omitidos: int,
     *     errores: list<string>,
     *     fuentes: list<array<string, mixed>>
     * }
     */
    public function sincronizarFuentesPorSala(bool $dryRun = false): array
    {
        $ret = $this->retVacio();
        $ret['fuentes'] = [];

        foreach (DepmaeAnitaFuentesSupport::fuentes() as $fuente) {
            $parcial = $this->sincronizarFuente($fuente, $dryRun);
            $ret['fuentes'][] = array_merge($fuente, [
                'en_anita' => $parcial['en_anita'],
                'importados' => $parcial['importados'],
                'actualizados' => $parcial['actualizados'],
                'omitidos_maquina' => $parcial['omitidos_maquina'],
                'omitidos' => $parcial['omitidos'],
                'errores' => count($parcial['errores']),
            ]);
            foreach (['en_anita', 'omitidos_maquina', 'importados', 'actualizados', 'omitidos'] as $clave) {
                $ret[$clave] += $parcial[$clave];
            }
            array_push($ret['errores'], ...$parcial['errores']);
        }

        return $ret;
    }

    /**
     * @param  array{
     *     ifx_server: string,
     *     sala_codigo: string,
     *     empresa_id: int,
     *     path_sistema: string,
     *     sistema: string
     * }  $fuente
     * @return array{
     *     en_anita: int,
     *     omitidos_maquina: int,
     *     importados: int,
     *     actualizados: int,
     *     omitidos: int,
     *     errores: list<string>
     * }
     */
    public function sincronizarFuente(array $fuente, bool $dryRun = false): array
    {
        $ret = $this->retVacio();
        $empresaId = (int) $fuente['empresa_id'];
        $salaCodigo = (string) $fuente['sala_codigo'];
        $salaId = GastronomiaSalaSupport::idPorCodigo($salaCodigo);

        if ($salaId === null) {
            $ret['errores'][] = "sala_codigo {$salaCodigo} inexistente (fuente {$fuente['ifx_server']}).";

            return $ret;
        }
        if ($empresaId <= 0 || ! Empresa::query()->whereKey($empresaId)->exists()) {
            $ret['errores'][] = "empresa_id {$empresaId} inexistente (fuente {$fuente['ifx_server']}).";

            return $ret;
        }

        $override = [
            'ifx_server' => $fuente['ifx_server'],
            'path_sistema' => $fuente['path_sistema'],
            'sistema' => $fuente['sistema'],
        ];

        $todas = DepmaeAnitaBridgeSupport::listar($empresaId, null, $override);
        $ret['en_anita'] = count($todas);

        foreach ($todas as $row) {
            $codigo = trim((string) ($row->{DepmaeAnitaBridgeSupport::keyField()} ?? ''));
            if (DepmaeAnitaExclusionSupport::debeOmitirCodigo($codigo)) {
                $ret['omitidos_maquina']++;

                continue;
            }

            try {
                if ($dryRun) {
                    $existente = Depmae::query()
                        ->where('empresa_id', $empresaId)
                        ->where('codigo', $codigo)
                        ->where('sala_id', $salaId)
                        ->exists();
                    if ($existente) {
                        $ret['actualizados']++;
                    } else {
                        $ret['importados']++;
                    }

                    continue;
                }

                $estado = $this->importarFila($row, $empresaId, $codigo, $salaId);
                $this->acumularEstado($ret, $estado);
            } catch (\Throwable $e) {
                $msg = "Depósito Anita {$fuente['ifx_server']} sala={$salaCodigo} codigo={$codigo}: ".$e->getMessage();
                $ret['errores'][] = $msg;
                Log::warning('DepmaeAnitaSync: '.$msg, ['exception' => $e]);
            }
        }

        return $ret;
    }

    /**
     * @return 'importado'|'actualizado'|'omitido'
     */
    public function traerRegistroDeAnita(int $empresaId, string $codigo, ?int $salaId = null): string
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

        return $this->importarFila($filas[0], $empresaId, $codigo, $salaId);
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
    private function importarFila(object $row, int $empresaId, string $codigo, ?int $salaId): string
    {
        if ($codigo === '') {
            return 'omitido';
        }

        $payload = DepmaeAnitaBridgeSupport::mapPayload($row);
        if ($payload['nombre'] === '') {
            throw new \InvalidArgumentException('depm_desc vacío.');
        }

        $payload['empresa_id'] = $empresaId;
        $payload['sala_id'] = $salaId;

        $existente = Depmae::query()
            ->where('empresa_id', $empresaId)
            ->where('codigo', $codigo)
            ->when($salaId !== null, fn ($q) => $q->where('sala_id', $salaId))
            ->when($salaId === null, fn ($q) => $q->whereNull('sala_id'))
            ->first();

        DB::beginTransaction();
        try {
            if ($existente) {
                $existente->update([
                    'nombre' => $payload['nombre'],
                    'tipodeposito' => $payload['tipodeposito'],
                    'sala_id' => $salaId,
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
    private function retVacio(): array
    {
        return [
            'en_anita' => 0,
            'omitidos_maquina' => 0,
            'importados' => 0,
            'actualizados' => 0,
            'omitidos' => 0,
            'errores' => [],
        ];
    }

    /**
     * @param  array<string, mixed>  $ret
     */
    private function acumularEstado(array &$ret, string $estado): void
    {
        if ($estado === 'importado') {
            $ret['importados']++;
        } elseif ($estado === 'actualizado') {
            $ret['actualizados']++;
        } else {
            $ret['omitidos']++;
        }
    }
}
