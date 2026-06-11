<?php

namespace App\Services\Ventas;

use App\ApiAnita;
use App\Models\Configuracion\Empresa;
use App\Models\Ventas\MozoGastronomia;
use App\Repositories\Ventas\MozoGastronomiaRepositoryInterface;
use App\Support\Stock\AnitaSync\Mozo\MozoFieldMapper;
use App\Support\Ventas\GastronomiaTicketTarjetaAnitaBridgeSupport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MozoGastronomiaAnitaSyncService
{
    public function __construct(
        private readonly MozoGastronomiaRepositoryInterface $mozoGastronomiaRepository,
    ) {
    }

    /**
     * Sincroniza mozos de una empresa ERP desde el bridge Anita de esa empresa
     * (vendedor + mozopasswd), forzando empresa_id del destino.
     *
     * @return array{en_anita:int, importados:int, actualizados:int, omitidos:int, errores:list<string>}
     */
    public function sincronizarEmpresaDesdeAnita(int $empresaId): array
    {
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        if ($empresaId <= 0 || ! Empresa::query()->where('id', $empresaId)->exists()) {
            throw new \InvalidArgumentException("empresa_id {$empresaId} inexistente.");
        }

        $ret = ['en_anita' => 0, 'importados' => 0, 'actualizados' => 0, 'omitidos' => 0, 'errores' => []];
        $lista = $this->listarMozosDesdeAnitaEmpresa($empresaId);
        $claves = $this->listarClavesDesdeAnitaEmpresa($empresaId);
        $ret['en_anita'] = count($lista);

        foreach ($lista as $row) {
            $codigoAnita = MozoFieldMapper::mapCodigo($row);
            if ($codigoAnita === null) {
                $ret['omitidos']++;

                continue;
            }

            try {
                $estado = $this->importarFilaEmpresa($row, $codigoAnita, $empresaId, $claves[$codigoAnita] ?? null);
                if ($estado === 'importado') {
                    $ret['importados']++;
                } elseif ($estado === 'actualizado') {
                    $ret['actualizados']++;
                } else {
                    $ret['omitidos']++;
                }
            } catch (\Throwable $e) {
                $msg = "Mozo Anita {$codigoAnita} (empresa {$empresaId}): ".$e->getMessage();
                $ret['errores'][] = $msg;
                Log::warning('MozoGastronomiaAnitaSync: '.$msg, ['exception' => $e]);
            }
        }

        return $ret;
    }

    /**
     * @return array{en_anita:int, importados:int, actualizados:int, omitidos:int, errores:list<string>}
     */
    public function sincronizarConAnita(): array
    {
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $ret = ['en_anita' => 0, 'importados' => 0, 'actualizados' => 0, 'omitidos' => 0, 'errores' => []];
        $lista = $this->listarMozosDesdeAnita();
        $ret['en_anita'] = count($lista);

        foreach ($lista as $row) {
            $codigoAnita = MozoFieldMapper::mapCodigo($row);
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
                $msg = "Mozo Anita {$codigoAnita}: ".$e->getMessage();
                $ret['errores'][] = $msg;
                Log::warning('MozoGastronomiaAnitaSync: '.$msg, ['exception' => $e]);
            }
        }

        return $ret;
    }

    /**
     * @return 'importado'|'actualizado'|'omitido'
     */
    public function traerRegistroDeAnita(int $vendCodigo): string
    {
        $row = $this->leerMozoEnAnita($vendCodigo);
        if ($row === null) {
            return 'omitido';
        }

        $codigoAnita = MozoFieldMapper::mapCodigo($row);
        if ($codigoAnita === null) {
            return 'omitido';
        }

        return $this->importarFila($row, $codigoAnita);
    }

    /**
     * @return list<object>
     */
    public function listarMozosDesdeAnita(): array
    {
        return $this->listarMozosDesdeAnitaEmpresa(0);
    }

    /**
     * @return list<object>
     */
    public function listarMozosDesdeAnitaEmpresa(int $empresaId): array
    {
        $api = new ApiAnita;
        $payload = array_merge($this->payloadBridgeBase($empresaId), [
            'acc' => 'list',
            'tabla' => config('mozo_gastronomia_anita.tabla', 'vendedor'),
            'campos' => config('mozo_gastronomia_anita.campos_listado'),
            'orderBy' => 'vend_codigo',
        ]);

        $rows = json_decode($api->apiCall($payload));

        return is_array($rows) ? $rows : [];
    }

    /**
     * @return array<string, string> codigo mozo => clave Anita (texto plano)
     */
    public function listarClavesDesdeAnitaEmpresa(int $empresaId): array
    {
        $api = new ApiAnita;
        $payload = array_merge($this->payloadBridgeBase($empresaId), [
            'acc' => 'list',
            'tabla' => config('mozo_gastronomia_anita.tabla_password', 'mozopasswd'),
            'campos' => config('mozo_gastronomia_anita.campos_password', 'mozp_mozo, mozp_password'),
            'orderBy' => 'mozp_mozo',
        ]);

        $rows = json_decode($api->apiCall($payload));
        if (! is_array($rows)) {
            return [];
        }

        $claves = [];
        foreach ($rows as $row) {
            $codigo = trim((string) ($row->mozp_mozo ?? ''));
            $clave = trim((string) ($row->mozp_password ?? ''));
            if ($codigo !== '' && $clave !== '') {
                $claves[$codigo] = $clave;
            }
        }

        return $claves;
    }

    /**
     * @return array<string, mixed>
     */
    private function payloadBridgeBase(int $empresaId): array
    {
        $payload = [];
        $sistema = config('mozo_gastronomia_anita.sistema');
        if ($sistema !== null && $sistema !== '') {
            $payload['sistema'] = $sistema;
        }

        if ($empresaId > 0) {
            $bridge = GastronomiaTicketTarjetaAnitaBridgeSupport::parametrosBridge($empresaId);
            if (isset($bridge['servidor'])) {
                $payload['servidor'] = $bridge['servidor'];
            }
            if (isset($bridge['path_sistema'])) {
                $payload['path_sistema'] = $bridge['path_sistema'];
            }
            if (isset($bridge['ifx_server'])) {
                $payload['ifx_server'] = $bridge['ifx_server'];
            }
            // mozopasswd y vendedor viven en Informix ventas, no en base_admin (tickettarj).
        }

        return $payload;
    }

    private function leerMozoEnAnita(int $vendCodigo): ?object
    {
        $api = new ApiAnita;
        $payload = [
            'acc' => 'list',
            'tabla' => config('mozo_gastronomia_anita.tabla', 'vendedor'),
            'campos' => config('mozo_gastronomia_anita.campos_listado'),
            'whereArmado' => " WHERE vend_codigo={$vendCodigo}",
        ];
        $sistema = config('mozo_gastronomia_anita.sistema');
        if ($sistema !== null && $sistema !== '') {
            $payload['sistema'] = $sistema;
        }

        $rows = json_decode($api->apiCall($payload));

        return (is_array($rows) && count($rows) > 0) ? $rows[0] : null;
    }

    /**
     * @return 'importado'|'actualizado'|'omitido'
     */
    private function importarFila(object $row, string $codigoAnita): string
    {
        $payload = MozoFieldMapper::mapAll($row);
        $this->validarPayloadMinimo($payload, $codigoAnita);

        return $this->persistirMozo(
            (int) $payload['empresa_id'],
            $codigoAnita,
            (string) $payload['nombre'],
            null,
        );
    }

    /**
     * @return 'importado'|'actualizado'|'omitido'
     */
    private function importarFilaEmpresa(object $row, string $codigoAnita, int $empresaId, ?string $claveAnita): string
    {
        $nombre = MozoFieldMapper::mapNombre($row);
        if (trim($nombre) === '') {
            throw new \InvalidArgumentException("nombre vacío (mozo {$codigoAnita}).");
        }

        return $this->persistirMozo($empresaId, $codigoAnita, $nombre, $claveAnita);
    }

    /**
     * @return 'importado'|'actualizado'|'omitido'
     */
    private function persistirMozo(int $empresaId, string $codigoAnita, string $nombre, ?string $claveAnita): string
    {
        $datos = [
            'codigo' => $codigoAnita,
            'nombre' => $nombre,
            'empresa_id' => $empresaId,
        ];

        $existente = MozoGastronomia::query()
            ->where('empresa_id', $empresaId)
            ->where('codigo', $codigoAnita)
            ->first();

        $claveNueva = $this->resolverClaveDestino($claveAnita, $existente);

        DB::beginTransaction();
        try {
            if ($existente) {
                $cambios = $datos;
                if ($claveNueva !== null) {
                    $cambios['clave'] = $claveNueva;
                }
                $huboCambio = $existente->nombre !== $datos['nombre']
                    || ($claveNueva !== null && (string) $existente->clave !== $claveNueva);
                if (! $huboCambio) {
                    DB::commit();

                    return 'omitido';
                }
                $existente->update($cambios);
                DB::commit();

                return 'actualizado';
            }

            if ($claveNueva !== null) {
                $datos['clave'] = $claveNueva;
            }
            $this->mozoGastronomiaRepository->create($datos);
            DB::commit();

            return 'importado';
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    private function resolverClaveDestino(?string $claveAnita, ?MozoGastronomia $existente): ?string
    {
        if ($claveAnita !== null && trim($claveAnita) !== '') {
            return trim($claveAnita);
        }

        if ($existente !== null) {
            return null;
        }

        $default = trim((string) config('mozo_gastronomia_anita.clave_default', '12345'));

        return $default !== '' ? $default : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function validarPayloadMinimo(array $payload, string $codigoAnita): void
    {
        if (trim((string) ($payload['nombre'] ?? '')) === '') {
            throw new \InvalidArgumentException("nombre vacío (mozo {$codigoAnita}).");
        }
        $empresaId = (int) ($payload['empresa_id'] ?? 0);
        if ($empresaId <= 0 || ! Empresa::query()->where('id', $empresaId)->exists()) {
            throw new \InvalidArgumentException("empresa_id {$empresaId} inexistente (mozo {$codigoAnita}).");
        }
    }
}
