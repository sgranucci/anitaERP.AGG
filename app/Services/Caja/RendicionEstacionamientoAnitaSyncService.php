<?php

namespace App\Services\Caja;

use App\ApiAnita;
use App\Models\Caja\RendicionEstacionamientoCaja;
use App\Models\Caja\RendicionEstacionamientoSecuenciaEmpresa;
use App\Support\Caja\AnitaSync\RendicionEstacionamientoAnitaContextBuilder;
use App\Support\Caja\AnitaSync\RendicionEstacionamientoAnitaIdempotenciaSupport;
use App\Support\Caja\AnitaSync\RendicionEstacionamientoAnitaTotalZPorPcService;
use App\Support\Caja\AnitaSync\RendicionEstacionamientoCabeceraAnitaMapper;
use App\Support\Caja\AnitaSync\RendicionEstacionamientoValorAnitaMapper;
use App\Support\Caja\RendicionEstacionamientoSecuenciaSupport;
use Illuminate\Support\Facades\Log;

/**
 * Numeración y réplica Informix rendgastro / rendvalor vía bridge Anita.
 */
class RendicionEstacionamientoAnitaSyncService
{
    private const LOG_EVENTO = 'rendicion_estacionamiento.anita_bridge.fallo';

    public function sincronizacionHabilitada(): bool
    {
        return filter_var(config('rendicion_estacionamiento_anita.sincronizar', true), FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Limpia rendgastro/rendvalor duplicados del turno en Anita y opcionalmente re-sincroniza la rendición ERP.
     * Uso operativo (artisan) cuando la jornada ya fue presentada y no se puede editar desde Caja.
     *
     * @return array{
     *   turno_operativo_id: int,
     *   nro_oper_antes: list<int>,
     *   nro_oper_despues: list<int>,
     *   nro_oper_canonico: int,
     *   eliminados: list<int>,
     *   resincronizado: bool
     * }
     */
    public function limpiarHuerfanosYResincronizar(RendicionEstacionamientoCaja $rendicion, bool $resincronizar = true): array
    {
        if ($rendicion->esRendicionJornada()) {
            throw new \InvalidArgumentException('La rendición tipo jornada no replica cabecera a Anita.');
        }

        if (! $this->sincronizacionHabilitada()) {
            throw new \RuntimeException('RENDICION_ESTACIONAMIENTO_SINCRONIZAR_ANITA está deshabilitado.');
        }

        $rendicion->load(['movimientos.cuentacaja', 'puntoventaCae', 'puntoventaCaea', 'turnoOperativo.turno', 'turnoOperativo.jornada']);

        $turnoId = (int) ($rendicion->turno_operativo_estacionamiento_id ?? 0);
        $empresaId = (int) ($rendicion->empresa_id ?? 0);
        $api = new ApiAnita;
        $tipoOper = $this->tipoOper();

        $antes = RendicionEstacionamientoAnitaIdempotenciaSupport::listarNroOperPorTurno(
            $api,
            $turnoId,
            $empresaId,
            $tipoOper,
            $this->tablaCabecera(),
            $this->sistema(),
        );

        $canonico = RendicionEstacionamientoAnitaIdempotenciaSupport::resolverYAlinearNroOper(
            $api,
            $rendicion,
            $tipoOper,
            $this->tablaCabecera(),
            $this->sistema(),
            $this->tablaValor(),
            self::LOG_EVENTO,
        );

        $despues = RendicionEstacionamientoAnitaIdempotenciaSupport::listarNroOperPorTurno(
            $api,
            $turnoId,
            $empresaId,
            $tipoOper,
            $this->tablaCabecera(),
            $this->sistema(),
        );

        $eliminados = array_values(array_diff($antes, $despues));

        if ($resincronizar) {
            if ($this->existsCabeceraEnAnita($rendicion)) {
                $this->actualizarEnAnita($rendicion);
            } else {
                $this->insertarEnAnita($rendicion);
            }
            $rendicion->update(['anita_sincronizado_en' => now()]);
        }

        return [
            'turno_operativo_id' => $turnoId,
            'nro_oper_antes' => $antes,
            'nro_oper_despues' => $despues,
            'nro_oper_canonico' => $canonico,
            'eliminados' => $eliminados,
            'resincronizado' => $resincronizar,
        ];
    }

    public function sincronizarDespuesDeGuardar(RendicionEstacionamientoCaja $rendicion): void
    {
        if ($rendicion->esRendicionJornada()) {
            return;
        }

        if (! $this->sincronizacionHabilitada()) {
            return;
        }

        $rendicion->load(['movimientos.cuentacaja', 'puntoventaCae', 'puntoventaCaea', 'turnoOperativo.turno', 'turnoOperativo.jornada']);

        $this->prepararIdempotenciaPorTurno($rendicion);

        if ($this->existsCabeceraEnAnita($rendicion)) {
            $this->actualizarEnAnita($rendicion);
        } else {
            $this->insertarEnAnita($rendicion);
        }

        $rendicion->update(['anita_sincronizado_en' => now()]);
    }

    public function sincronizarDespuesDeEliminar(RendicionEstacionamientoCaja $rendicion): void
    {
        if ($rendicion->esRendicionJornada()) {
            return;
        }

        if (! $this->sincronizacionHabilitada()) {
            return;
        }

        $nroOper = (int) ($rendicion->nro_oper_anita
            ?? RendicionEstacionamientoCabeceraAnitaMapper::nroOperDesdeCodigo($rendicion->codigo));
        if ($nroOper <= 0) {
            return;
        }

        $this->eliminarEnAnita($nroOper, $this->tipoOper());
    }

    public function insertarEnAnita(RendicionEstacionamientoCaja $rendicion): void
    {
        $this->insertarEnAnitaConTotalZ($rendicion, null);
    }

    /**
     * Alta rendgastro + rendvalor desde contexto ya armado (proceso cierre Waitry).
     *
     * @param  array<string, mixed>  $ctx
     */
    public function insertarDesdeContexto(array $ctx): void
    {
        if (! $this->sincronizacionHabilitada()) {
            return;
        }

        $this->insertarEnAnitaDesdeContexto($ctx, null);
    }

    private function insertarEnAnitaConTotalZ(RendicionEstacionamientoCaja $rendicion, ?float $totalZ): void
    {
        $ctx = RendicionEstacionamientoAnitaContextBuilder::desdeRendicion($rendicion, $totalZ);
        $this->insertarEnAnitaDesdeContexto($ctx, $rendicion);
    }

    /**
     * @param  array<string, mixed>  $ctx
     */
    private function insertarEnAnitaDesdeContexto(array $ctx, ?RendicionEstacionamientoCaja $rendicion): void
    {
        $api = new ApiAnita;
        $nroOper = (int) ($ctx['nro_oper'] ?? 0);
        $tipoOper = (string) ($ctx['tipo_oper'] ?? '');
        $cabeceraPreexistente = $nroOper > 0 && (
            $rendicion !== null
                ? $this->existsCabeceraEnAnita($rendicion)
                : $this->existsCabeceraEnAnitaPorNroOper($nroOper, $tipoOper)
        );
        $cabeceraInsertadaEnEsteIntento = false;

        try {
            if ($cabeceraPreexistente) {
                if ($rendicion !== null) {
                    $this->actualizarEnAnitaConTotalZ($rendicion, $ctx['total_z'] ?? null);
                } else {
                    $this->actualizarEnAnitaDesdeContexto($ctx);
                }

                return;
            }

            try {
                $api->apiCallEscritura([
                    'tabla' => $this->tablaCabecera(),
                    'acc' => 'insert',
                    'sistema' => $this->sistema(),
                    'campos' => RendicionEstacionamientoCabeceraAnitaMapper::camposInsert(),
                    'valores' => RendicionEstacionamientoCabeceraAnitaMapper::valoresInsert($ctx),
                ], 'rendgastro insert', self::LOG_EVENTO);
                $cabeceraInsertadaEnEsteIntento = true;
            } catch (\RuntimeException $e) {
                if ($this->esErrorDuplicadoInformix($e)) {
                    if ($rendicion !== null) {
                        $this->actualizarEnAnitaConTotalZ($rendicion, $ctx['total_z'] ?? null);
                    } else {
                        $this->actualizarEnAnitaDesdeContexto($ctx);
                    }

                    return;
                }

                throw $e;
            }

            // Anita puede precargar filas rendvalor al insertar cabecera (p. ej. código 17).
            if ($nroOper > 0 && $tipoOper !== '') {
                $this->eliminarValores($api, $nroOper, $tipoOper);
            }

            $this->insertarValores($api, $ctx);
        } catch (\Throwable $e) {
            if ($cabeceraInsertadaEnEsteIntento && $nroOper > 0 && $tipoOper !== '') {
                try {
                    $this->eliminarEnAnita($nroOper, $tipoOper);
                } catch (\Throwable $rollbackErr) {
                    Log::warning(self::LOG_EVENTO.'.compensacion_fallo', [
                        'nro_oper' => $nroOper,
                        'turno_operativo_id' => $rendicion?->turno_operativo_estacionamiento_id,
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
        if ($nroOper <= 0 || $tipoOper === '') {
            return;
        }

        $api->apiCallEscritura([
            'acc' => 'update',
            'tabla' => $this->tablaCabecera(),
            'sistema' => $this->sistema(),
            'valores' => RendicionEstacionamientoCabeceraAnitaMapper::valoresUpdate($ctx),
            'whereArmado' => RendicionEstacionamientoCabeceraAnitaMapper::whereClave($nroOper, $tipoOper),
        ], 'rendgastro update', self::LOG_EVENTO);

        $this->eliminarValores($api, $nroOper, $tipoOper);
        $this->insertarValores($api, $ctx);
    }

    public function existsCabeceraEnAnitaPorNroOper(int $nroOper, ?string $tipoOper = null): bool
    {
        if ($nroOper <= 0) {
            return false;
        }

        $tipoOper = $tipoOper ?? $this->tipoOper();
        $api = new ApiAnita;
        $rows = ApiAnita::decodificarListaFilas($api->apiCall([
            'acc' => 'list',
            'sistema' => $this->sistema(),
            'tabla' => $this->tablaCabecera(),
            'campos' => 'rendg_nro_oper',
            'whereArmado' => RendicionEstacionamientoCabeceraAnitaMapper::whereClave($nroOper, $tipoOper),
        ]));

        return count($rows) > 0;
    }

    public function reaplicarTotalZPorPcEnJornada(int $jornadaId): void
    {
        app(RendicionEstacionamientoAnitaTotalZPorPcService::class)->aplicarSiJornadaCerrada($jornadaId);
    }

    public function reaplicarTotalZDesdeRendicionTurno(RendicionEstacionamientoCaja $rendicion): void
    {
        app(RendicionEstacionamientoAnitaTotalZPorPcService::class)->aplicarDesdeRendicionTurno($rendicion);
    }

    public function resetTotalZPorPcEnJornada(int $jornadaId): void
    {
        app(RendicionEstacionamientoAnitaTotalZPorPcService::class)->resetTotalZEnJornada($jornadaId);
    }

    /**
     * Actualiza solo rendg_total_z en rendgastro (sin tocar rendvalor).
     * Usado al recalcular Z por PC desde Caja cuando la jornada ya está cerrada.
     */
    public function actualizarSoloTotalZEnAnita(RendicionEstacionamientoCaja $rendicion, float $totalZ): void
    {
        if ($rendicion->esRendicionJornada() || ! $this->sincronizacionHabilitada()) {
            return;
        }

        $rendicion->load(['movimientos.cuentacaja', 'puntoventaCae', 'puntoventaCaea', 'turnoOperativo.turno', 'turnoOperativo.jornada']);

        if (! $this->existsCabeceraEnAnita($rendicion)) {
            $this->insertarEnAnitaConTotalZ($rendicion, $totalZ);

            return;
        }

        $nroOper = (int) ($rendicion->nro_oper_anita
            ?? RendicionEstacionamientoCabeceraAnitaMapper::nroOperDesdeCodigo($rendicion->codigo));
        if ($nroOper <= 0) {
            return;
        }

        $api = new ApiAnita;
        $api->apiCallEscritura([
            'acc' => 'update',
            'tabla' => $this->tablaCabecera(),
            'sistema' => $this->sistema(),
            'valores' => 'rendg_total_z = '.RendicionEstacionamientoCabeceraAnitaMapper::decimal($totalZ),
            'whereArmado' => RendicionEstacionamientoCabeceraAnitaMapper::whereClave($nroOper, $this->tipoOper()),
        ], 'rendgastro update total_z', self::LOG_EVENTO);
    }

    /**
     * Actualiza rendg_total_z y rendg_tot_nc por clave nro_oper (reparación por PV/fecha).
     */
    public function actualizarTotalZYNcPorNroOper(int $nroOper, float $totalZ, float $totNc): void
    {
        if (! $this->sincronizacionHabilitada()) {
            throw new \RuntimeException('RENDICION_ESTACIONAMIENTO_SINCRONIZAR_ANITA está deshabilitado.');
        }

        if ($nroOper <= 0) {
            throw new \InvalidArgumentException('nro_oper inválido para actualizar Anita.');
        }

        $api = new ApiAnita;
        $valores = 'rendg_total_z = '.RendicionEstacionamientoCabeceraAnitaMapper::decimal($totalZ)
            .', rendg_tot_nc = '.RendicionEstacionamientoCabeceraAnitaMapper::decimal($totNc);

        $api->apiCallEscritura([
            'acc' => 'update',
            'tabla' => $this->tablaCabecera(),
            'sistema' => $this->sistema(),
            'valores' => $valores,
            'whereArmado' => RendicionEstacionamientoCabeceraAnitaMapper::whereClave($nroOper, $this->tipoOper()),
        ], 'rendgastro update total_z+nc', self::LOG_EVENTO);
    }

    /** @deprecated Use actualizarSoloTotalZEnAnita() */
    public function actualizarCabeceraTotalZEnAnita(RendicionEstacionamientoCaja $rendicion, float $totalZ): void
    {
        $this->actualizarSoloTotalZEnAnita($rendicion, $totalZ);
    }

    public function actualizarEnAnita(RendicionEstacionamientoCaja $rendicion): void
    {
        $this->actualizarEnAnitaConTotalZ($rendicion, null);
    }

    private function actualizarEnAnitaConTotalZ(RendicionEstacionamientoCaja $rendicion, ?float $totalZ): void
    {
        $ctx = RendicionEstacionamientoAnitaContextBuilder::desdeRendicion($rendicion, $totalZ);
        $api = new ApiAnita;
        $nroOper = (int) $ctx['nro_oper'];
        $tipoOper = (string) $ctx['tipo_oper'];

        $api->apiCallEscritura([
            'acc' => 'update',
            'tabla' => $this->tablaCabecera(),
            'sistema' => $this->sistema(),
            'valores' => RendicionEstacionamientoCabeceraAnitaMapper::valoresUpdate($ctx),
            'whereArmado' => RendicionEstacionamientoCabeceraAnitaMapper::whereClave($nroOper, $tipoOper),
        ], 'rendgastro update', self::LOG_EVENTO);

        $this->eliminarValores($api, $nroOper, $tipoOper);
        $this->insertarValores($api, $ctx);
    }

    public function eliminarEnAnita(int $nroOper, string $tipoOper): void
    {
        $api = new ApiAnita;
        $this->eliminarValores($api, $nroOper, $tipoOper);

        $api->apiCallEscritura([
            'acc' => 'delete',
            'tabla' => $this->tablaCabecera(),
            'sistema' => $this->sistema(),
            'whereArmado' => RendicionEstacionamientoCabeceraAnitaMapper::whereClave($nroOper, $tipoOper),
        ], 'rendgastro delete', self::LOG_EVENTO);
    }

    public function existsCabeceraEnAnita(RendicionEstacionamientoCaja $rendicion): bool
    {
        $nroOper = (int) ($rendicion->nro_oper_anita
            ?? RendicionEstacionamientoCabeceraAnitaMapper::nroOperDesdeCodigo($rendicion->codigo));
        if ($nroOper <= 0) {
            return false;
        }

        $api = new ApiAnita;
        $rows = ApiAnita::decodificarListaFilas($api->apiCall([
            'acc' => 'list',
            'sistema' => $this->sistema(),
            'tabla' => $this->tablaCabecera(),
            'campos' => 'rendg_nro_oper',
            'whereArmado' => RendicionEstacionamientoCabeceraAnitaMapper::whereClave($nroOper, $this->tipoOper()),
        ]));

        return count($rows) > 0;
    }

    /**
     * @return array{
     *   codigo: string,
     *   nro_oper: int,
     *   fuente: string,
     *   ultimo_anita: int,
     *   ultimo_erp: int,
     *   consulta_anita_ok: bool
     * }
     */
    public function proponerSiguienteNroOper(int $empresaId): array
    {
        if ($empresaId <= 0) {
            throw new \InvalidArgumentException('Empresa inválida para numeración Anita.');
        }

        $ultimoErp = $this->ultimoNroOperEnErp($empresaId);
        $ultimoAnita = null;
        $consultaAnitaOk = false;

        try {
            $ultimoAnita = $this->ultimoNroOperEnAnita($empresaId);
            $consultaAnitaOk = true;
        } catch (\Throwable $e) {
            Log::warning('RendicionEstacionamientoAnita: no se pudo consultar último nro_oper en Anita', [
                'empresa_id' => $empresaId,
                'mensaje' => $e->getMessage(),
            ]);
        }

        $calculo = RendicionEstacionamientoSecuenciaSupport::calcularSiguiente($ultimoAnita, $ultimoErp);
        if (! $consultaAnitaOk) {
            $calculo['fuente'] = RendicionEstacionamientoSecuenciaSupport::FUENTE_ERP_FALLBACK;
        }

        $this->persistirSecuenciaEmpresa($empresaId, $calculo, $consultaAnitaOk);

        return [
            'codigo' => (string) $calculo['siguiente'],
            'nro_oper' => $calculo['siguiente'],
            'fuente' => $calculo['fuente'],
            'ultimo_anita' => $calculo['ultimo_anita'],
            'ultimo_erp' => $calculo['ultimo_erp'],
            'consulta_anita_ok' => $consultaAnitaOk,
        ];
    }

    public function ultimoNroOperEnAnita(int $empresaId): int
    {
        $tipoOper = $this->tipoOper();
        $where = " WHERE rendg_empresa = '".$empresaId."' AND rendg_tipo_oper = '".$tipoOper."' ";

        $api = new ApiAnita;
        $payload = [
            'acc' => 'list',
            'sistema' => $this->sistema(),
            'tabla' => $this->tablaCabecera(),
            'campos' => 'rendg_nro_oper',
            'orderBy' => 'rendg_nro_oper desc',
            'whereArmado' => $where,
        ];

        $rows = ApiAnita::decodificarListaFilas($api->apiCall($payload));
        if ($rows === []) {
            return 0;
        }

        return max(0, (int) ($rows[0]->rendg_nro_oper ?? 0));
    }

    public function ultimoNroOperEnErp(int $empresaId): int
    {
        $maxDesdeColumna = (int) (RendicionEstacionamientoCaja::query()
            ->where('empresa_id', $empresaId)
            ->whereNotNull('nro_oper_anita')
            ->max('nro_oper_anita') ?? 0);

        $maxDesdeCodigo = 0;
        $codigos = RendicionEstacionamientoCaja::query()
            ->where('empresa_id', $empresaId)
            ->pluck('codigo');

        foreach ($codigos as $codigo) {
            $n = RendicionEstacionamientoSecuenciaSupport::extraerNroOperDesdeCodigo((string) $codigo);
            if ($n !== null && $n > $maxDesdeCodigo) {
                $maxDesdeCodigo = $n;
            }
        }

        return max($maxDesdeColumna, $maxDesdeCodigo);
    }

    /**
     * @param  array<string, mixed>  $cabecera
     * @return array<string, mixed>
     */
    public function enriquecerCabeceraConTracking(array $cabecera, ?string $fuente = null): array
    {
        $nro = RendicionEstacionamientoSecuenciaSupport::extraerNroOperDesdeCodigo($cabecera['codigo'] ?? null);
        if ($nro === null) {
            return $cabecera;
        }

        $cabecera['nro_oper_anita'] = $nro;
        if ($fuente !== null && trim($fuente) !== '') {
            $cabecera['fuente_nro_oper'] = $fuente;
        } elseif (empty($cabecera['fuente_nro_oper'])) {
            $cabecera['fuente_nro_oper'] = RendicionEstacionamientoSecuenciaSupport::FUENTE_ERP;
        }

        return $cabecera;
    }

    /**
     * @param  array<string, mixed>  $ctx
     */
    private function insertarValores(ApiAnita $api, array $ctx): void
    {
        $lineas = RendicionEstacionamientoValorAnitaMapper::lineasAgregadas(
            (int) $ctx['empresa_id'],
            $ctx['movimientos'] ?? [],
        );

        foreach ($lineas as $linea) {
            try {
                $api->apiCallEscritura([
                    'tabla' => $this->tablaValor(),
                    'acc' => 'insert',
                    'sistema' => $this->sistema(),
                    'campos' => RendicionEstacionamientoValorAnitaMapper::camposInsert(),
                    'valores' => RendicionEstacionamientoValorAnitaMapper::valoresInsert($linea, $ctx),
                ], 'rendvalor insert codigo '.$linea['codigo'], self::LOG_EVENTO);
            } catch (\RuntimeException $e) {
                if (! $this->esErrorDuplicadoInformix($e)) {
                    throw $e;
                }

                $api->apiCallEscritura([
                    'acc' => 'update',
                    'tabla' => $this->tablaValor(),
                    'sistema' => $this->sistema(),
                    'valores' => RendicionEstacionamientoValorAnitaMapper::valoresUpdate($linea, $ctx),
                    'whereArmado' => RendicionEstacionamientoValorAnitaMapper::wherePorOperacionYCodigo(
                        (int) ($ctx['nro_oper'] ?? 0),
                        (string) ($ctx['tipo_oper'] ?? ''),
                        (int) ($linea['codigo'] ?? 0),
                    ),
                ], 'rendvalor update codigo '.$linea['codigo'], self::LOG_EVENTO);
            }
        }
    }

    private function esErrorDuplicadoInformix(\RuntimeException $e): bool
    {
        $msg = mb_strtolower($e->getMessage());

        return str_contains($msg, 'duplicate')
            || str_contains($msg, 'duplicado')
            || str_contains($msg, 'unique key')
            || str_contains($msg, 'unique index');
    }

    private function prepararIdempotenciaPorTurno(RendicionEstacionamientoCaja $rendicion): void
    {
        $api = new ApiAnita;
        RendicionEstacionamientoAnitaIdempotenciaSupport::resolverYAlinearNroOper(
            $api,
            $rendicion,
            $this->tipoOper(),
            $this->tablaCabecera(),
            $this->sistema(),
            $this->tablaValor(),
            self::LOG_EVENTO,
        );
    }

    private function eliminarValores(ApiAnita $api, int $nroOper, string $tipoOper): void
    {
        $api->apiCallEscritura([
            'acc' => 'delete',
            'tabla' => $this->tablaValor(),
            'sistema' => $this->sistema(),
            'whereArmado' => RendicionEstacionamientoValorAnitaMapper::wherePorOperacion($nroOper, $tipoOper),
        ], 'rendvalor delete', self::LOG_EVENTO);
    }

    /**
     * @param  array{siguiente:int,fuente:string,ultimo_anita:int,ultimo_erp:int}  $calculo
     */
    private function persistirSecuenciaEmpresa(int $empresaId, array $calculo, bool $consultaAnitaOk): void
    {
        $attrs = [
            'ultimo_nro_anita' => $calculo['ultimo_anita'],
            'ultimo_nro_erp' => $calculo['ultimo_erp'],
            'proximo_nro' => $calculo['siguiente'],
        ];

        if ($consultaAnitaOk) {
            $attrs['consultado_anita_en'] = now();
        }

        RendicionEstacionamientoSecuenciaEmpresa::query()->updateOrCreate(
            ['empresa_id' => $empresaId],
            $attrs,
        );
    }

    private function sistema(): string
    {
        return (string) config('rendicion_estacionamiento_anita.sistema', 'caja');
    }

    private function tablaCabecera(): string
    {
        return (string) config('rendicion_estacionamiento_anita.tabla_cabecera', 'rendgastro');
    }

    private function tablaValor(): string
    {
        return (string) config('rendicion_estacionamiento_anita.tabla_valor', 'rendvalor');
    }

    private function tipoOper(): string
    {
        return substr((string) config('rendicion_estacionamiento_anita.tipo_oper', 'F'), 0, 1);
    }
}
