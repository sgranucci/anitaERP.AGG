<?php

namespace App\Services\Ventas;

use App\ApiAnita;
use App\Models\Ventas\MaquinavendingRendicion;
use App\Support\Caja\AnitaSync\RendicionAnitaIdempotenciaWhereSupport;
use App\Support\Caja\AnitaSync\MaquinavendingRendicionCabeceraAnitaMapper;
use App\Support\Caja\AnitaSync\RendicionGastronomiaValorAnitaMapper;
use App\Support\Caja\MaquinavendingRendicionNroOperPisoSupport;
use App\Support\Caja\RendicionGastronomiaSecuenciaSupport;
use App\Support\Ventas\MaquinavendingRendicionAnitaContextBuilder;
use Illuminate\Support\Facades\Log;

class MaquinavendingRendicionAnitaSyncService
{
    private const LOG_EVENTO = 'maquinavending_rendicion.anita_bridge.fallo';

    public function sincronizacionHabilitada(): bool
    {
        return filter_var(config('rendicion_maquinavending_anita.sincronizar', true), FILTER_VALIDATE_BOOLEAN);
    }

    public function sincronizarDespuesDeGuardar(MaquinavendingRendicion $rendicion): void
    {
        if (! $this->sincronizacionHabilitada()) {
            return;
        }

        $rendicion->load(['mediosPago.cuentacaja', 'maquinavending.puntoventa']);

        if ((int) ($rendicion->nro_oper_anita ?? 0) <= 0) {
            $propuesta = $this->proponerSiguienteNroOper((int) $rendicion->empresa_id);
            $rendicion->update([
                'codigo' => $propuesta['codigo'],
                'nro_oper_anita' => $propuesta['nro_oper'],
                'fuente_nro_oper' => $propuesta['fuente'],
            ]);
            $rendicion->refresh();
        }

        $this->alinearNroOperDesdeAnita($rendicion);

        if ($this->existsCabeceraEnAnita($rendicion)) {
            $this->actualizarEnAnita($rendicion);
        } else {
            $this->insertarEnAnita($rendicion);
        }

        $rendicion->update(['anita_sincronizado_en' => now()]);
    }

    /**
     * @return array{codigo: string, nro_oper: int, fuente: string}
     */
    public function proponerSiguienteNroOper(int $empresaId): array
    {
        if ($empresaId <= 0) {
            throw new \InvalidArgumentException('Empresa inválida para numeración Anita vending.');
        }

        $ultimoErp = $this->ultimoNroOperEnErp($empresaId);
        $ultimoAnita = 0;
        $consultaAnitaOk = false;

        try {
            $ultimoAnita = $this->ultimoNroOperEnAnita($empresaId);
            $consultaAnitaOk = true;
        } catch (\Throwable $e) {
            Log::warning('MaquinavendingRendicionAnita: no se pudo consultar último nro_oper en Anita', [
                'empresa_id' => $empresaId,
                'mensaje' => $e->getMessage(),
            ]);
        }

        $calculo = RendicionGastronomiaSecuenciaSupport::calcularSiguiente(
            $ultimoAnita,
            $ultimoErp,
            MaquinavendingRendicionNroOperPisoSupport::pisoParaEmpresa($empresaId),
            MaquinavendingRendicionNroOperPisoSupport::techoParaEmpresa($empresaId),
        );

        if (! $consultaAnitaOk) {
            $calculo['fuente'] = RendicionGastronomiaSecuenciaSupport::FUENTE_ERP_FALLBACK;
        }

        $siguiente = (int) $calculo['siguiente'];

        return [
            'codigo' => (string) $siguiente,
            'nro_oper' => $siguiente,
            'fuente' => (string) $calculo['fuente'],
        ];
    }

    public function ultimoNroOperEnErp(int $empresaId): int
    {
        $query = MaquinavendingRendicion::query()->where('empresa_id', $empresaId);
        $piso = MaquinavendingRendicionNroOperPisoSupport::pisoParaEmpresa($empresaId);
        $techo = MaquinavendingRendicionNroOperPisoSupport::techoParaEmpresa($empresaId);

        if ($piso > 0) {
            $query->where('nro_oper_anita', '>=', $piso);
        }
        if ($techo > 0) {
            $query->where('nro_oper_anita', '<', $techo);
        }

        $maxCol = (int) ($query->whereNotNull('nro_oper_anita')->max('nro_oper_anita') ?? 0);
        $maxCodigo = 0;

        foreach (MaquinavendingRendicion::query()->where('empresa_id', $empresaId)->pluck('codigo') as $codigo) {
            $n = RendicionGastronomiaSecuenciaSupport::extraerNroOperDesdeCodigo((string) $codigo);
            if ($n === null || ! MaquinavendingRendicionNroOperPisoSupport::enRangoEmpresa($empresaId, $n)) {
                continue;
            }
            $maxCodigo = max($maxCodigo, $n);
        }

        return max($maxCol, $maxCodigo);
    }

    public function ultimoNroOperEnAnita(int $empresaId): int
    {
        $tipoOper = $this->tipoOper();
        $where = " WHERE rendg_empresa = '".$empresaId."' AND rendg_tipo_oper = '".$tipoOper."' "
            .MaquinavendingRendicionNroOperPisoSupport::filtroSqlAnita($empresaId);

        $api = new ApiAnita;
        $rows = ApiAnita::decodificarListaFilas($api->apiCall([
            'acc' => 'list',
            'sistema' => $this->sistema(),
            'tabla' => $this->tablaCabecera(),
            'campos' => 'rendg_nro_oper',
            'orderBy' => 'rendg_nro_oper desc',
            'whereArmado' => $where,
        ]));

        if ($rows === []) {
            return 0;
        }

        return max(0, (int) ($rows[0]->rendg_nro_oper ?? 0));
    }

    private function alinearNroOperDesdeAnita(MaquinavendingRendicion $rendicion): void
    {
        $rendicion->loadMissing('maquinavending.puntoventa');
        $sucursal = MaquinavendingRendicionAnitaContextBuilder::codigoPuntoventaEntero(
            $rendicion->maquinavending?->puntoventa?->codigo
        );

        $where = RendicionAnitaIdempotenciaWhereSupport::whereVendingRendicion(
            (int) $rendicion->id,
            (int) $rendicion->empresa_id,
            $sucursal,
            $this->tipoOper(),
            MaquinavendingRendicionAnitaContextBuilder::hostDesdeRendicion($rendicion),
        );

        if ($where === '') {
            return;
        }

        $api = new ApiAnita;
        $rows = ApiAnita::decodificarListaFilas($api->apiCall([
            'acc' => 'list',
            'sistema' => $this->sistema(),
            'tabla' => $this->tablaCabecera(),
            'campos' => 'rendg_nro_oper',
            'whereArmado' => $where,
        ]));

        if ($rows === []) {
            return;
        }

        $nroAnita = (int) ($rows[0]->rendg_nro_oper ?? 0);
        if ($nroAnita > 0 && $nroAnita !== (int) $rendicion->nro_oper_anita) {
            $rendicion->update([
                'nro_oper_anita' => $nroAnita,
                'codigo' => (string) $nroAnita,
                'fuente_nro_oper' => 'anita_existente',
            ]);
            $rendicion->refresh();
        }
    }

    public function existsCabeceraEnAnita(MaquinavendingRendicion $rendicion): bool
    {
        $nroOper = (int) ($rendicion->nro_oper_anita
            ?? MaquinavendingRendicionCabeceraAnitaMapper::nroOperDesdeCodigo($rendicion->codigo));
        if ($nroOper <= 0) {
            return false;
        }

        $api = new ApiAnita;
        $rows = ApiAnita::decodificarListaFilas($api->apiCall([
            'acc' => 'list',
            'sistema' => $this->sistema(),
            'tabla' => $this->tablaCabecera(),
            'campos' => 'rendg_nro_oper',
            'whereArmado' => MaquinavendingRendicionCabeceraAnitaMapper::whereClave($nroOper, $this->tipoOper()),
        ]));

        return count($rows) > 0;
    }

    public function insertarEnAnita(MaquinavendingRendicion $rendicion): void
    {
        $ctx = MaquinavendingRendicionAnitaContextBuilder::desdeRendicion($rendicion);
        $this->insertarEnAnitaDesdeContexto($ctx);
    }

    public function actualizarEnAnita(MaquinavendingRendicion $rendicion): void
    {
        $ctx = MaquinavendingRendicionAnitaContextBuilder::desdeRendicion($rendicion);
        $api = new ApiAnita;
        $nroOper = (int) $ctx['nro_oper'];
        $tipoOper = (string) $ctx['tipo_oper'];

        $api->apiCallEscritura([
            'acc' => 'update',
            'tabla' => $this->tablaCabecera(),
            'sistema' => $this->sistema(),
            'valores' => MaquinavendingRendicionCabeceraAnitaMapper::valoresUpdate($ctx),
            'whereArmado' => MaquinavendingRendicionCabeceraAnitaMapper::whereClave($nroOper, $tipoOper),
        ], 'rendgastro update vending', self::LOG_EVENTO);

        $this->eliminarValores($api, $nroOper, $tipoOper);
        $this->insertarValores($api, $ctx);
    }

    public function actualizarSoloTotalZ(MaquinavendingRendicion $rendicion, float $totalZ): void
    {
        if (! $this->sincronizacionHabilitada()) {
            return;
        }

        $nroOper = (int) ($rendicion->nro_oper_anita
            ?? MaquinavendingRendicionCabeceraAnitaMapper::nroOperDesdeCodigo($rendicion->codigo));
        if ($nroOper <= 0) {
            return;
        }

        if (! $this->existsCabeceraEnAnita($rendicion)) {
            $this->sincronizarDespuesDeGuardar($rendicion);
            $rendicion->refresh();
            $nroOper = (int) ($rendicion->nro_oper_anita ?? $nroOper);
            if ($nroOper <= 0 || ! $this->existsCabeceraEnAnita($rendicion)) {
                return;
            }
        }

        $api = new ApiAnita;
        $api->apiCallEscritura([
            'acc' => 'update',
            'tabla' => $this->tablaCabecera(),
            'sistema' => $this->sistema(),
            'valores' => MaquinavendingRendicionCabeceraAnitaMapper::valoresUpdatePresentacionCaja($totalZ),
            'whereArmado' => MaquinavendingRendicionCabeceraAnitaMapper::whereClave($nroOper, $this->tipoOper()),
        ], 'rendgastro update presentacion caja vending', self::LOG_EVENTO);
    }

    public function resetTotalZ(MaquinavendingRendicion $rendicion): void
    {
        $this->actualizarSoloTotalZ($rendicion, 0.0);
    }

    public function eliminarEnAnita(MaquinavendingRendicion $rendicion): void
    {
        if (! $this->sincronizacionHabilitada()) {
            return;
        }

        $nroOper = (int) ($rendicion->nro_oper_anita
            ?? MaquinavendingRendicionCabeceraAnitaMapper::nroOperDesdeCodigo($rendicion->codigo));
        if ($nroOper <= 0) {
            return;
        }

        $api = new ApiAnita;
        $tipoOper = $this->tipoOper();
        $this->eliminarValores($api, $nroOper, $tipoOper);

        $api->apiCallEscritura([
            'acc' => 'delete',
            'tabla' => $this->tablaCabecera(),
            'sistema' => $this->sistema(),
            'whereArmado' => MaquinavendingRendicionCabeceraAnitaMapper::whereClave($nroOper, $tipoOper),
        ], 'rendgastro delete vending', self::LOG_EVENTO);
    }

    /**
     * @param  array<string, mixed>  $ctx
     */
    private function insertarEnAnitaDesdeContexto(array $ctx): void
    {
        $api = new ApiAnita;
        $nroOper = (int) ($ctx['nro_oper'] ?? 0);
        $tipoOper = (string) ($ctx['tipo_oper'] ?? '');
        $cabeceraInsertada = false;

        try {
            $api->apiCallEscritura([
                'tabla' => $this->tablaCabecera(),
                'acc' => 'insert',
                'sistema' => $this->sistema(),
                'campos' => MaquinavendingRendicionCabeceraAnitaMapper::camposInsert(),
                'valores' => MaquinavendingRendicionCabeceraAnitaMapper::valoresInsert($ctx),
            ], 'rendgastro insert vending', self::LOG_EVENTO);
            $cabeceraInsertada = true;

            if ($nroOper > 0 && $tipoOper !== '') {
                $this->eliminarValores($api, $nroOper, $tipoOper);
            }

            $this->insertarValores($api, $ctx);
        } catch (\Throwable $e) {
            if ($this->esErrorDuplicadoInformix($e)) {
                $this->actualizarEnAnitaDesdeContexto($ctx);

                return;
            }

            if ($cabeceraInsertada && $nroOper > 0 && $tipoOper !== '') {
                try {
                    $this->eliminarEnAnitaPorNroOper($nroOper, $tipoOper);
                } catch (\Throwable $rollbackErr) {
                    Log::warning(self::LOG_EVENTO.'.compensacion_fallo', [
                        'nro_oper' => $nroOper,
                        'mensaje' => $rollbackErr->getMessage(),
                    ]);
                }
            }

            throw $e;
        }
    }

    /**
     * @param  array<string, mixed>  $ctx
     */
    private function actualizarEnAnitaDesdeContexto(array $ctx): void
    {
        $api = new ApiAnita;
        $nroOper = (int) ($ctx['nro_oper'] ?? 0);
        $tipoOper = (string) ($ctx['tipo_oper'] ?? '');

        $api->apiCallEscritura([
            'acc' => 'update',
            'tabla' => $this->tablaCabecera(),
            'sistema' => $this->sistema(),
            'valores' => MaquinavendingRendicionCabeceraAnitaMapper::valoresUpdate($ctx),
            'whereArmado' => MaquinavendingRendicionCabeceraAnitaMapper::whereClave($nroOper, $tipoOper),
        ], 'rendgastro update vending', self::LOG_EVENTO);

        $this->eliminarValores($api, $nroOper, $tipoOper);
        $this->insertarValores($api, $ctx);
    }

    /**
     * @param  array<string, mixed>  $ctx
     */
    private function insertarValores(ApiAnita $api, array $ctx): void
    {
        $lineas = RendicionGastronomiaValorAnitaMapper::lineasAgregadas(
            (int) $ctx['empresa_id'],
            $ctx['movimientos'] ?? [],
        );

        foreach ($lineas as $linea) {
            try {
                $api->apiCallEscritura([
                    'tabla' => $this->tablaValor(),
                    'acc' => 'insert',
                    'sistema' => $this->sistema(),
                    'campos' => RendicionGastronomiaValorAnitaMapper::camposInsert(),
                    'valores' => RendicionGastronomiaValorAnitaMapper::valoresInsert($linea, $ctx),
                ], 'rendvalor insert vending '.$linea['codigo'], self::LOG_EVENTO);
            } catch (\RuntimeException $e) {
                if (! $this->esErrorDuplicadoInformix($e)) {
                    throw $e;
                }

                $api->apiCallEscritura([
                    'acc' => 'update',
                    'tabla' => $this->tablaValor(),
                    'sistema' => $this->sistema(),
                    'valores' => RendicionGastronomiaValorAnitaMapper::valoresUpdate($linea, $ctx),
                    'whereArmado' => RendicionGastronomiaValorAnitaMapper::wherePorOperacionYCodigo(
                        (int) ($ctx['nro_oper'] ?? 0),
                        (string) ($ctx['tipo_oper'] ?? ''),
                        (int) ($linea['codigo'] ?? 0),
                    ),
                ], 'rendvalor update vending '.$linea['codigo'], self::LOG_EVENTO);
            }
        }
    }

    private function eliminarValores(ApiAnita $api, int $nroOper, string $tipoOper): void
    {
        $api->apiCallEscritura([
            'acc' => 'delete',
            'tabla' => $this->tablaValor(),
            'sistema' => $this->sistema(),
            'whereArmado' => RendicionGastronomiaValorAnitaMapper::wherePorOperacion($nroOper, $tipoOper),
        ], 'rendvalor delete vending', self::LOG_EVENTO);
    }

    private function eliminarEnAnitaPorNroOper(int $nroOper, string $tipoOper): void
    {
        $api = new ApiAnita;
        $this->eliminarValores($api, $nroOper, $tipoOper);
        $api->apiCallEscritura([
            'acc' => 'delete',
            'tabla' => $this->tablaCabecera(),
            'sistema' => $this->sistema(),
            'whereArmado' => MaquinavendingRendicionCabeceraAnitaMapper::whereClave($nroOper, $tipoOper),
        ], 'rendgastro delete vending', self::LOG_EVENTO);
    }

    private function esErrorDuplicadoInformix(\Throwable $e): bool
    {
        $msg = mb_strtolower($e->getMessage());

        return str_contains($msg, 'duplicate')
            || str_contains($msg, 'duplicado')
            || str_contains($msg, 'unique key')
            || str_contains($msg, 'unique index');
    }

    private function sistema(): string
    {
        return (string) config('rendicion_maquinavending_anita.sistema', 'caja');
    }

    private function tablaCabecera(): string
    {
        return (string) config('rendicion_maquinavending_anita.tabla_cabecera', 'rendgastro');
    }

    private function tablaValor(): string
    {
        return (string) config('rendicion_maquinavending_anita.tabla_valor', 'rendvalor');
    }

    private function tipoOper(): string
    {
        return substr((string) config('rendicion_maquinavending_anita.tipo_oper', 'F'), 0, 1);
    }
}
