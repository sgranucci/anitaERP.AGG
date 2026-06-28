<?php

namespace App\Services\Ventas;

use App\Models\Configuracion\Empresa;
use App\Models\Stock\Depmae;
use App\Models\Ventas\Maquinavending;
use App\Models\Ventas\MaquinavendingArticulo;
use App\Models\Ventas\Puntoventa;
use App\Models\Ventas\UbicacionGastronomia;
use App\Services\Stock\ArticuloAnitaSyncService;
use App\Support\Stock\ArticuloSkuMatchSupport;
use App\Support\Ventas\AnitaSync\Maquinavending\MaquinavendingFieldMapper;
use App\Support\Ventas\MaquinavendingAnitaBridgeSupport;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MaquinavendingAnitaSyncService
{
    /**
     * @return array{
     *   en_anita:int,
     *   importados:int,
     *   actualizados:int,
     *   omitidos:int,
     *   articulos_lineas:int,
     *   errores:list<string>
     * }
     */
    public function sincronizarConAnita(?int $empresaId = null): array
    {
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $ret = [
            'en_anita' => 0,
            'importados' => 0,
            'actualizados' => 0,
            'omitidos' => 0,
            'articulos_lineas' => 0,
            'errores' => [],
        ];

        $empresas = ($empresaId !== null && $empresaId > 0)
            ? [$empresaId]
            : $this->empresasSyncOrden();

        foreach ($empresas as $eid) {
            if (! Empresa::query()->whereKey($eid)->exists()) {
                $ret['errores'][] = "Empresa id {$eid} inexistente; se omite sincronización.";
                continue;
            }

            $parcial = $this->sincronizarEmpresaBridge($eid);
            $ret['en_anita'] += $parcial['en_anita'];
            $ret['importados'] += $parcial['importados'];
            $ret['actualizados'] += $parcial['actualizados'];
            $ret['omitidos'] += $parcial['omitidos'];
            $ret['articulos_lineas'] += $parcial['articulos_lineas'];
            $ret['errores'] = array_merge($ret['errores'], $parcial['errores']);
        }

        return $ret;
    }

    /**
     * @return array{en_anita:int, importados:int, actualizados:int, omitidos:int, articulos_lineas:int, errores:list<string>}
     */
    public function sincronizarEmpresaDesdeAnita(int $empresaId): array
    {
        if ($empresaId <= 0 || ! Empresa::query()->whereKey($empresaId)->exists()) {
            throw new \InvalidArgumentException("empresa_id {$empresaId} inexistente.");
        }

        return $this->sincronizarConAnita($empresaId);
    }

    /**
     * @return 'importado'|'actualizado'|'omitido'
     */
    public function traerRegistroDeAnita(int $codigoAnita, ?int $empresaId = null): string
    {
        $empresas = ($empresaId !== null && $empresaId > 0)
            ? [$empresaId]
            : $this->empresasSyncOrden();

        foreach ($empresas as $eid) {
            $row = $this->leerMaquinaEnAnita($eid, $codigoAnita);
            if ($row === null) {
                continue;
            }

            $estado = $this->importarMaquina($row, $codigoAnita, $eid);
            if ($estado === 'omitido') {
                return 'omitido';
            }

            $this->sincronizarArticulosMaquinaDesdeAnita($eid, $codigoAnita, $estado['id']);

            return $estado['estado'];
        }

        return 'omitido';
    }

    /**
     * @return list<object>
     */
    public function listarMaquinasDesdeAnita(int $empresaId): array
    {
        return MaquinavendingAnitaBridgeSupport::listarMaquinas($empresaId);
    }

    /**
     * @return list<object>
     */
    public function listarArticulosMaquinaDesdeAnita(int $empresaId, int $codigoAnita): array
    {
        return MaquinavendingAnitaBridgeSupport::listarArticulos(
            $empresaId,
            ' WHERE ubimv_codigo='.(int) $codigoAnita
        );
    }

    /**
     * @return array{
     *   en_anita:int,
     *   importados:int,
     *   actualizados:int,
     *   omitidos:int,
     *   articulos_lineas:int,
     *   errores:list<string>
     * }
     */
    private function sincronizarEmpresaBridge(int $empresaId): array
    {
        $ret = [
            'en_anita' => 0,
            'importados' => 0,
            'actualizados' => 0,
            'omitidos' => 0,
            'articulos_lineas' => 0,
            'errores' => [],
        ];

        $maquinas = $this->listarMaquinasDesdeAnita($empresaId);
        $ret['en_anita'] = count($maquinas);

        /** @var array<int, int> $mapCodigoAnitaMaquinaId */
        $mapCodigoAnitaMaquinaId = [];

        foreach ($maquinas as $row) {
            $codigoAnita = MaquinavendingFieldMapper::mapCodigoAnita($row);
            if ($codigoAnita === null) {
                $ret['omitidos']++;

                continue;
            }

            try {
                $estado = $this->importarMaquina($row, $codigoAnita, $empresaId);
                if ($estado === 'omitido') {
                    $ret['omitidos']++;

                    continue;
                }

                if ($estado['estado'] === 'importado') {
                    $ret['importados']++;
                } else {
                    $ret['actualizados']++;
                }

                $mapCodigoAnitaMaquinaId[$codigoAnita] = $estado['id'];
            } catch (\Throwable $e) {
                $msg = "Máquina Anita empresa {$empresaId} {$codigoAnita}: ".$e->getMessage();
                $ret['errores'][] = $msg;
                Log::warning('MaquinavendingAnitaSync: '.$msg, ['exception' => $e]);
            }
        }

        $articulosRet = $this->sincronizarArticulosDesdeAnita($mapCodigoAnitaMaquinaId, $empresaId);
        $ret['articulos_lineas'] = $articulosRet['lineas'];
        $ret['errores'] = array_merge($ret['errores'], $articulosRet['errores']);

        return $ret;
    }

    /**
     * @param  array<int, int>  $mapCodigoAnitaMaquinaId
     * @return array{lineas:int, errores:list<string>}
     */
    private function sincronizarArticulosDesdeAnita(array $mapCodigoAnitaMaquinaId, int $empresaId): array
    {
        $ret = ['lineas' => 0, 'errores' => []];
        if ($mapCodigoAnitaMaquinaId === []) {
            return $ret;
        }

        $rows = MaquinavendingAnitaBridgeSupport::listarArticulos($empresaId);
        $porMaquina = collect($rows)->groupBy(fn ($row) => (int) ($row->ubimv_codigo ?? 0));

        foreach ($mapCodigoAnitaMaquinaId as $codigoAnita => $maquinavendingId) {
            $lineas = $porMaquina->get($codigoAnita, collect());
            try {
                $ret['lineas'] += $this->persistirLineasArticulo((int) $maquinavendingId, $lineas, $empresaId);
            } catch (\Throwable $e) {
                $msg = "Artículos máquina Anita empresa {$empresaId} {$codigoAnita}: ".$e->getMessage();
                $ret['errores'][] = $msg;
                Log::warning('MaquinavendingAnitaSync: '.$msg, ['exception' => $e]);
            }
        }

        return $ret;
    }

    private function sincronizarArticulosMaquinaDesdeAnita(int $empresaId, int $codigoAnita, int $maquinavendingId): void
    {
        $rows = collect($this->listarArticulosMaquinaDesdeAnita($empresaId, $codigoAnita));
        $this->persistirLineasArticulo($maquinavendingId, $rows, $empresaId);
    }

    /**
     * @return array{estado:'importado'|'actualizado', id:int}|'omitido'
     */
    private function importarMaquina(object $row, int $codigoAnita, int $empresaId): array|string
    {
        $sucursal = MaquinavendingFieldMapper::mapSucursal($row);
        $puntoventa = $this->resolverPuntoventaPorSucursal($sucursal, $empresaId);
        if ($puntoventa === null) {
            throw new \InvalidArgumentException("sucursal {$sucursal} sin punto de venta en empresa {$empresaId}.");
        }

        $ubicacion = $this->resolverOCrearUbicacion($empresaId, MaquinavendingFieldMapper::mapUbicacionTexto($row));

        $deposito = $this->resolverDeposito($empresaId, MaquinavendingFieldMapper::mapDepositoCodigoAnita($row));
        if ($deposito === null) {
            throw new \InvalidArgumentException('depósito Anita «'.MaquinavendingFieldMapper::mapDepositoCodigoAnita($row).'» no encontrado en ERP.');
        }

        $datos = [
            'codigo_anita' => $codigoAnita,
            'empresa_id' => $empresaId,
            'nombre' => MaquinavendingFieldMapper::mapNombre($row),
            'puntoventa_id' => (int) $puntoventa->id,
            'ubicacion_id' => (int) $ubicacion->id,
            'deposito_id' => (int) $deposito->id,
            'listaprecio_id' => (int) config('precio.listaprecio_default_id', 2),
            'codigo_arca' => MaquinavendingFieldMapper::mapCodigoArca($row),
            'numero_serie' => MaquinavendingFieldMapper::mapNumeroSerie($row),
        ];

        $existente = Maquinavending::query()
            ->where('empresa_id', $empresaId)
            ->where('codigo_anita', $codigoAnita)
            ->first();

        DB::beginTransaction();
        try {
            if ($existente) {
                $existente->update($datos);
                DB::commit();

                return ['estado' => 'actualizado', 'id' => (int) $existente->id];
            }

            $nuevo = Maquinavending::query()->create($datos);
            DB::commit();

            return ['estado' => 'importado', 'id' => (int) $nuevo->id];
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * @param  Collection<int, object>  $lineas
     */
    private function persistirLineasArticulo(int $maquinavendingId, Collection $lineas, int $empresaIdBridge): int
    {
        $payload = [];

        foreach ($lineas as $row) {
            $numeroRulo = MaquinavendingFieldMapper::mapNumeroRuloDesdeArticulo($row);
            $sku = MaquinavendingFieldMapper::mapSkuArticuloDesdeArticulo($row);
            if ($numeroRulo <= 0 || $sku === null) {
                continue;
            }

            $articuloId = $this->resolverArticuloIdPorSku($sku, $empresaIdBridge);

            $payload[] = [
                'numero_rulo' => $numeroRulo,
                'articulo_id' => $articuloId,
            ];
        }

        DB::transaction(function () use ($maquinavendingId, $payload): void {
            MaquinavendingArticulo::query()->where('maquinavending_id', $maquinavendingId)->delete();
            foreach ($payload as $linea) {
                MaquinavendingArticulo::query()->create([
                    'maquinavending_id' => $maquinavendingId,
                    'numero_rulo' => $linea['numero_rulo'],
                    'articulo_id' => $linea['articulo_id'],
                ]);
            }
        });

        return count($payload);
    }

    private function resolverArticuloIdPorSku(string $sku, int $empresaIdBridge): int
    {
        $sku = trim($sku);
        $canonico = ArticuloSkuMatchSupport::resolverCanonico($sku);
        if ($canonico !== null) {
            return (int) $canonico->id;
        }

        try {
            app(ArticuloAnitaSyncService::class)->sincronizarSkuDesdeAnita($sku, $empresaIdBridge);
        } catch (\Throwable $e) {
            throw new \InvalidArgumentException("artículo SKU {$sku} no encontrado en ERP ni en Anita (empresa {$empresaIdBridge}): ".$e->getMessage());
        }

        $canonico = ArticuloSkuMatchSupport::resolverCanonico($sku);
        if ($canonico === null) {
            throw new \InvalidArgumentException("artículo SKU {$sku} no encontrado en ERP tras importar desde Anita.");
        }

        return (int) $canonico->id;
    }

    private function resolverPuntoventaPorSucursal(int $sucursal, int $empresaId): ?Puntoventa
    {
        if ($sucursal <= 0) {
            return null;
        }

        /** @var Collection<int, Puntoventa> $candidatos */
        $candidatos = Puntoventa::query()
            ->where('estado', 'A')
            ->where('empresa_id', $empresaId)
            ->get();

        foreach ($candidatos as $pv) {
            $num = (int) preg_replace('/\D+/', '', (string) $pv->codigo);
            if ($num === $sucursal) {
                return $pv;
            }
        }

        return $candidatos->firstWhere('codigo', (string) $sucursal);
    }

    /**
     * Busca ubicación por nombre+empresa; si no existe la crea (patrón Biyemas / totems K1-K2).
     */
    private function resolverOCrearUbicacion(int $empresaId, string $texto): UbicacionGastronomia
    {
        $texto = trim($texto);
        if ($texto === '') {
            throw new \InvalidArgumentException('ubicación vacía en Anita.');
        }

        $nombre = mb_substr($texto, 0, 255);

        $existente = UbicacionGastronomia::query()
            ->where('empresa_id', $empresaId)
            ->whereRaw('UPPER(TRIM(nombre)) = ?', [mb_strtoupper($nombre)])
            ->first();

        if ($existente !== null) {
            return $existente;
        }

        return UbicacionGastronomia::query()->create([
            'empresa_id' => $empresaId,
            'nombre' => $nombre,
        ]);
    }

    private function resolverDeposito(int $empresaId, string $codigoAnita): ?Depmae
    {
        $codigoAnita = trim($codigoAnita);
        if ($codigoAnita === '' || $codigoAnita === '0') {
            return null;
        }

        $deposito = Depmae::query()
            ->where('empresa_id', $empresaId)
            ->where('codigo', $codigoAnita)
            ->first();

        if ($deposito !== null) {
            return $deposito;
        }

        $codigoSinCeros = ltrim($codigoAnita, '0');

        return Depmae::query()
            ->where('empresa_id', $empresaId)
            ->where('codigo', $codigoSinCeros !== '' ? $codigoSinCeros : '0')
            ->first();
    }

    private function leerMaquinaEnAnita(int $empresaId, int $codigoAnita): ?object
    {
        $rows = MaquinavendingAnitaBridgeSupport::listarMaquinas(
            $empresaId,
            ' WHERE maqvm_codigo='.(int) $codigoAnita
        );

        return $rows !== [] ? $rows[0] : null;
    }

    /**
     * @return list<int>
     */
    private function empresasSyncOrden(): array
    {
        $orden = (array) config('maquinavending_anita.empresas_sync', [1, 2, 3]);

        return array_values(array_filter(array_map('intval', $orden), fn (int $id) => $id > 0));
    }
}
