<?php

namespace App\Services\Caja;

use App\ApiAnita;
use App\Models\Caja\Caja;
use App\Models\Caja\CajeroCrown;
use App\Models\Caja\CajeroCrownExterno;
use App\Models\Configuracion\Empresa;
use App\Models\Ventas\MozoGastronomia;
use App\Support\Caja\CajeroCrownSistemaExternoSupport;
use App\Support\Database\EloquentAuditDeleteSupport;
use App\Support\Ventas\GastronomiaSalaSupport;
use Illuminate\Support\Facades\Log;

/**
 * Importa cajeros Crown desde Anita (cajero + cajeroexterno) y enlaza mozos de la sala configurada.
 *
 * Mapeo: caj_mozo → mozo_gastronomia.codigo (sala CIP en Crown).
 * Sistemas externos: PXSOL/GST por codigo_externo; GASTRONOMIA por mozo_gastronomia_id.
 */
class CajeroCrownAnitaSyncService
{
    /**
     * @return array{
     *   en_anita:int,
     *   importados:int,
     *   actualizados:int,
     *   omitidos:int,
     *   enlazados_mozo:int,
     *   externos:int,
     *   errores:list<string>
     * }
     */
    public function sincronizarDesdeAnita(): array
    {
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $ret = [
            'en_anita' => 0,
            'importados' => 0,
            'actualizados' => 0,
            'omitidos' => 0,
            'enlazados_mozo' => 0,
            'externos' => 0,
            'errores' => [],
        ];

        $empresaId = (int) config('cajero_crown_anita.empresa_default_id', 1);
        if ($empresaId <= 0 || ! Empresa::query()->whereKey($empresaId)->exists()) {
            $ret['errores'][] = "empresa_id {$empresaId} inexistente.";

            return $ret;
        }

        $salaCodigo = trim((string) config('cajero_crown_anita.sala_codigo', ''));
        $salaId = GastronomiaSalaSupport::idPorCodigo($salaCodigo);
        if ($salaCodigo !== '' && $salaId === null) {
            $ret['errores'][] = "sala_codigo {$salaCodigo} inexistente.";

            return $ret;
        }

        $cajeros = $this->listarCajerosDesdeAnita();
        $ret['en_anita'] = count($cajeros);
        $externosPorCajero = $this->listarExternosDesdeAnitaAgrupados();
        $mozosPorCodigo = $this->indiceMozosSala($empresaId, $salaId);
        $cajasPorCodigo = $this->indiceCajasSala($empresaId, $salaId);

        foreach ($cajeros as $row) {
            $codigo = (int) ($row->caj_cajero ?? 0);
            if ($codigo <= 0) {
                $ret['omitidos']++;

                continue;
            }

            try {
                $resultado = $this->upsertCajero(
                    $row,
                    $codigo,
                    $empresaId,
                    $salaId,
                    $mozosPorCodigo,
                    $cajasPorCodigo,
                    $externosPorCajero[$codigo] ?? []
                );
                if ($resultado['estado'] === 'importado') {
                    $ret['importados']++;
                } elseif ($resultado['estado'] === 'actualizado') {
                    $ret['actualizados']++;
                } else {
                    $ret['omitidos']++;
                }
                if ($resultado['enlazado_mozo']) {
                    $ret['enlazados_mozo']++;
                }
                $ret['externos'] += $resultado['externos'];
            } catch (\Throwable $e) {
                $msg = "Cajero Anita {$codigo}: ".$e->getMessage();
                $ret['errores'][] = $msg;
                Log::warning('CajeroCrownAnitaSync: '.$msg, ['exception' => $e]);
            }
        }

        return $ret;
    }

    /**
     * @return list<object>
     */
    public function listarCajerosDesdeAnita(): array
    {
        $api = new ApiAnita;
        $payload = $this->payloadBridge([
            'acc' => 'list',
            'tabla' => config('cajero_crown_anita.tabla', 'cajero'),
            'campos' => config('cajero_crown_anita.campos_listado'),
            'orderBy' => 'caj_cajero',
        ]);

        $rows = json_decode($api->apiCall($payload));

        return is_array($rows) ? $rows : [];
    }

    /**
     * @return list<object>
     */
    public function listarExternosDesdeAnita(): array
    {
        $api = new ApiAnita;
        $payload = $this->payloadBridge([
            'acc' => 'list',
            'tabla' => config('cajero_crown_anita.tabla_externo', 'cajeroexterno'),
            'campos' => config('cajero_crown_anita.campos_externo'),
            'orderBy' => 'caje_cajero',
        ]);

        $rows = json_decode($api->apiCall($payload));

        return is_array($rows) ? $rows : [];
    }

    /**
     * @return array<int, list<object>>
     */
    private function listarExternosDesdeAnitaAgrupados(): array
    {
        $porCajero = [];
        foreach ($this->listarExternosDesdeAnita() as $row) {
            $codigo = (int) ($row->caje_cajero ?? 0);
            if ($codigo <= 0) {
                continue;
            }
            $porCajero[$codigo][] = $row;
        }

        return $porCajero;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function payloadBridge(array $payload): array
    {
        $sistema = config('cajero_crown_anita.sistema');
        if ($sistema !== null && $sistema !== '') {
            $payload['sistema'] = $sistema;
        }
        $ifx = trim((string) config('cajero_crown_anita.ifx_server', ''));
        if ($ifx !== '') {
            $payload['ifx_server'] = $ifx;
        }
        $path = rtrim(trim((string) config('cajero_crown_anita.path_sistema', '')), '/');
        if ($path !== '') {
            $payload['path_sistema'] = $path;
        }

        return $payload;
    }

    /**
     * @return array<string, MozoGastronomia>
     */
    private function indiceMozosSala(int $empresaId, ?int $salaId): array
    {
        $q = MozoGastronomia::query()->where('empresa_id', $empresaId);
        if ($salaId !== null) {
            $q->where('sala_id', $salaId);
        }

        $indice = [];
        foreach ($q->get(['id', 'codigo', 'nombre']) as $mozo) {
            $cod = (string) (int) $mozo->codigo;
            if ($cod !== '0') {
                $indice[$cod] = $mozo;
            }
        }

        return $indice;
    }

    /**
     * @return array<string, int> codigo caja → id
     */
    private function indiceCajasSala(int $empresaId, ?int $salaId): array
    {
        $q = Caja::query()->where('empresa_id', $empresaId);
        if ($salaId !== null) {
            $q->where('sala_id', $salaId);
        }

        $indice = [];
        foreach ($q->get(['id', 'codigo']) as $caja) {
            $indice[(string) (int) $caja->codigo] = (int) $caja->id;
        }

        return $indice;
    }

    /**
     * @param  array<string, MozoGastronomia>  $mozosPorCodigo
     * @param  array<string, int>  $cajasPorCodigo
     * @param  list<object>  $externosAnita
     * @return array{estado:string, enlazado_mozo:bool, externos:int}
     */
    private function upsertCajero(
        object $row,
        int $codigo,
        int $empresaId,
        ?int $salaId,
        array $mozosPorCodigo,
        array $cajasPorCodigo,
        array $externosAnita,
    ): array {
        $nombre = trim((string) ($row->caj_nombre ?? ''));
        if ($nombre === '') {
            $nombre = "Cajero {$codigo}";
        }

        $legajo = (int) ($row->caj_legajo ?? 0);
        $legajo = $legajo > 0 ? $legajo : null;

        $cajaAnita = (int) ($row->caj_caja ?? 0);
        $cajaId = $cajaAnita > 0
            ? ($cajasPorCodigo[(string) $cajaAnita] ?? null)
            : null;

        $mozoAnita = (int) ($row->caj_mozo ?? 0);
        $mozo = null;
        if ($mozoAnita > 0) {
            // Solo enlaza si el mozo Anita existe en la sala (CIP).
            $mozo = $mozosPorCodigo[(string) $mozoAnita] ?? null;
        } else {
            // Sin caj_mozo: mismo código de cajero = código mozo de la sala.
            $mozo = $mozosPorCodigo[(string) $codigo] ?? null;
        }
        $mozoId = $mozo?->id;

        $datos = [
            'nombre' => mb_substr($nombre, 0, 120),
            'empresa_id' => $empresaId,
            'sala_id' => $salaId,
            'legajo' => $legajo,
            'caja_id' => $cajaId,
            'mozo_gastronomia_id' => $mozoId,
            'activo' => true,
        ];

        $existente = CajeroCrown::query()
            ->where('empresa_id', $empresaId)
            ->where('codigo', $codigo)
            ->first();

        if ($existente === null) {
            $cajero = CajeroCrown::query()->create(array_merge($datos, ['codigo' => $codigo]));
            $estado = 'importado';
        } else {
            $cajero = $existente;
            $cajero->fill($datos);
            if ($cajero->isDirty()) {
                $cajero->save();
                $estado = 'actualizado';
            } else {
                $estado = 'omitido';
            }
        }

        $nExternos = $this->sincronizarExternos($cajero, $externosAnita, $mozo);

        return [
            'estado' => $estado,
            'enlazado_mozo' => $mozoId !== null,
            'externos' => $nExternos,
        ];
    }

    /**
     * @param  list<object>  $externosAnita
     */
    private function sincronizarExternos(
        CajeroCrown $cajero,
        array $externosAnita,
        ?MozoGastronomia $mozo,
    ): int {
        $deseados = [];

        foreach ($externosAnita as $row) {
            $sistema = CajeroCrownSistemaExternoSupport::normalizar(
                (string) ($row->caje_sistema ?? '')
            );
            if (! CajeroCrownSistemaExternoSupport::esValido($sistema)) {
                continue;
            }
            if ($sistema === CajeroCrownSistemaExternoSupport::GASTRONOMIA) {
                continue; // se arma abajo desde el mozo enlazado
            }
            $codigoExt = trim((string) ($row->caje_cajero_ext ?? ''));
            if ($codigoExt === '') {
                continue;
            }
            $deseados[$sistema] = [
                'sistema' => $sistema,
                'codigo_externo' => mb_substr($codigoExt, 0, 30),
                'mozo_gastronomia_id' => null,
            ];
        }

        if ($mozo !== null) {
            $deseados[CajeroCrownSistemaExternoSupport::GASTRONOMIA] = [
                'sistema' => CajeroCrownSistemaExternoSupport::GASTRONOMIA,
                'codigo_externo' => mb_substr((string) $mozo->codigo, 0, 30),
                'mozo_gastronomia_id' => (int) $mozo->id,
            ];
        }

        $sistemasOk = array_keys($deseados);
        if ($sistemasOk === []) {
            EloquentAuditDeleteSupport::each(
                CajeroCrownExterno::query()->where('cajero_crown_id', $cajero->id)
            );

            return 0;
        }

        EloquentAuditDeleteSupport::each(
            CajeroCrownExterno::query()
                ->where('cajero_crown_id', $cajero->id)
                ->whereNotIn('sistema', $sistemasOk)
        );

        $guardados = 0;
        foreach ($deseados as $datos) {
            $ext = CajeroCrownExterno::query()->updateOrCreate(
                [
                    'cajero_crown_id' => $cajero->id,
                    'sistema' => $datos['sistema'],
                ],
                [
                    'codigo_externo' => $datos['codigo_externo'],
                    'mozo_gastronomia_id' => $datos['mozo_gastronomia_id'],
                ]
            );
            $guardados++;
            unset($ext);
        }

        return $guardados;
    }
}
