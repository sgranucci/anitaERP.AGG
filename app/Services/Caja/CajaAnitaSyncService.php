<?php

namespace App\Services\Caja;

use App\ApiAnita;
use App\Models\Caja\Caja;
use App\Models\Configuracion\Empresa;
use App\Support\Ventas\GastronomiaSalaSupport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Importa cajas físicas desde Anita (sistema caja / tabla caja) con sala opcional.
 */
class CajaAnitaSyncService
{
    /**
     * @return array{en_anita:int, importados:int, actualizados:int, omitidos:int, errores:list<string>}
     */
    public function sincronizarConAnita(): array
    {
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $ret = ['en_anita' => 0, 'importados' => 0, 'actualizados' => 0, 'omitidos' => 0, 'errores' => []];
        $lista = $this->listarCajasDesdeAnita();
        $ret['en_anita'] = count($lista);

        $salaId = GastronomiaSalaSupport::idPorCodigo(
            (string) config('caja_anita.sala_codigo', '')
        );
        $empresaId = (int) config('caja_anita.empresa_default_id', 1);
        if ($empresaId <= 0 || ! Empresa::query()->whereKey($empresaId)->exists()) {
            $ret['errores'][] = "empresa_id {$empresaId} inexistente.";

            return $ret;
        }

        $orden = 0;
        foreach ($lista as $row) {
            $orden++;
            $nombre = trim((string) ($row->caja_desc ?? ''));
            if ($nombre === '') {
                $ret['omitidos']++;

                continue;
            }

            // Anita tabla caja solo tiene caja_desc; el nro de caja es la posición
            // (cajero.caj_caja apunta a ese índice 1-based).
            $codigo = (string) $orden;

            try {
                $estado = $this->upsertCaja($codigo, $nombre, $empresaId, $salaId);
                if ($estado === 'importado') {
                    $ret['importados']++;
                } elseif ($estado === 'actualizado') {
                    $ret['actualizados']++;
                } else {
                    $ret['omitidos']++;
                }
            } catch (\Throwable $e) {
                $msg = "Caja Anita {$codigo}: ".$e->getMessage();
                $ret['errores'][] = $msg;
                Log::warning('CajaAnitaSync: '.$msg, ['exception' => $e]);
            }
        }

        return $ret;
    }

    /**
     * @return list<object>
     */
    public function listarCajasDesdeAnita(): array
    {
        $api = new ApiAnita;
        $payload = $this->payloadBridge([
            'acc' => 'list',
            'tabla' => config('caja_anita.tabla', 'caja'),
            'campos' => config('caja_anita.campos_listado', 'caja_desc'),
        ]);

        $rows = json_decode($api->apiCall($payload));

        return is_array($rows) ? $rows : [];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function payloadBridge(array $payload): array
    {
        $sistema = config('caja_anita.sistema');
        if ($sistema !== null && $sistema !== '') {
            $payload['sistema'] = $sistema;
        }
        $ifx = trim((string) config('caja_anita.ifx_server', ''));
        if ($ifx !== '') {
            $payload['ifx_server'] = $ifx;
        }
        $path = rtrim(trim((string) config('caja_anita.path_sistema', '')), '/');
        if ($path !== '') {
            $payload['path_sistema'] = $path;
        }

        return $payload;
    }

    /**
     * @return 'importado'|'actualizado'|'omitido'
     */
    private function upsertCaja(string $codigo, string $nombre, int $empresaId, ?int $salaId): string
    {
        $existente = Caja::query()
            ->where('codigo', $codigo)
            ->when($salaId !== null, fn ($q) => $q->where('sala_id', $salaId))
            ->when($salaId === null, fn ($q) => $q->whereNull('sala_id'))
            ->first();

        $datos = [
            'codigo' => $codigo,
            'nombre' => $nombre,
            'empresa_id' => $empresaId,
            'sala_id' => $salaId,
        ];

        return DB::transaction(function () use ($existente, $datos) {
            if ($existente) {
                $existente->update($datos);

                return 'actualizado';
            }

            Caja::query()->create($datos);

            return 'importado';
        });
    }
}
