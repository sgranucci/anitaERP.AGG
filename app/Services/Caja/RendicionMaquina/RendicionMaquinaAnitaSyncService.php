<?php

declare(strict_types=1);

namespace App\Services\Caja\RendicionMaquina;

use App\ApiAnita;
use App\Models\Caja\RendicionMaquina;
use App\Support\Caja\AnitaSync\RendicionAnitaNroOperUnificadoSupport;
use App\Support\Caja\AnitaSync\RendicionMaquinaAnitaContextBuilder;
use App\Support\Caja\AnitaSync\RendicionMaquinaCabeceraAnitaMapper;
use App\Support\Caja\AnitaSync\RendicionMaquinaGastoAnitaMapper;
use App\Support\Caja\AnitaSync\RendicionMaquinaValorAnitaMapper;
use Illuminate\Support\Facades\Log;

final class RendicionMaquinaAnitaSyncService
{
    private const LOG_EVENTO = 'rendicion_maquina.anita_bridge.fallo';

    public function sincronizacionHabilitada(): bool
    {
        return filter_var(config('rendicion_maquina_anita.sincronizar', false), FILTER_VALIDATE_BOOLEAN);
    }

    public function sincronizarDespuesDeGuardar(RendicionMaquina $rendicion): void
    {
        $rendicion->loadMissing(['valores.cuentacaja', 'gastos.aperturaGasto', 'empresa']);

        // Numeración siempre (local ERP o unificada Anita según flag).
        if ((int) ($rendicion->nro_oper_anita ?? 0) <= 0) {
            $propuesta = $this->proponerSiguienteNroOper((int) $rendicion->empresa_id);
            $rendicion->update(['nro_oper_anita' => $propuesta['nro_oper']]);
            $rendicion->refresh();
        }

        if (! $this->sincronizacionHabilitada()) {
            return;
        }

        if ($this->existsCabeceraEnAnita($rendicion)) {
            $this->actualizarEnAnita($rendicion);
        } else {
            $this->insertarEnAnita($rendicion);
        }

        $this->sincronizarDetalleEnAnita($rendicion);

        $rendicion->update(['anita_sincronizado_en' => now()]);
    }

    public function sincronizarDespuesDeEliminar(RendicionMaquina $rendicion): void
    {
        if (! $this->sincronizacionHabilitada()) {
            return;
        }

        $nroOper = (int) ($rendicion->nro_oper_anita ?? 0);
        if ($nroOper <= 0) {
            return;
        }

        $this->eliminarEnAnita($nroOper, $this->tipoOper());
    }

    /**
     * Flag off: serie local ERP desde 1 (max nro_oper_anita en ERP + 1).
     * Flag on: max(Anita unificado, ERP) + 1 (paridad con la numeración reciente).
     *
     * @return array{nro_oper: int, fuente: string}
     */
    public function proponerSiguienteNroOper(int $empresaId): array
    {
        if ($empresaId <= 0) {
            throw new \InvalidArgumentException('Empresa inválida para numeración Anita máquinas.');
        }

        if (! $this->sincronizacionHabilitada()) {
            // Serie local ERP (paralelo): solo nros aún no sincronizados a Anita → arranca en 1.
            return [
                'nro_oper' => $this->ultimoNroOperSerieLocalEnErp() + 1,
                'fuente' => 'erp_local',
            ];
        }

        $api = new ApiAnita;
        $ultimoErp = $this->ultimoNroOperEnErp();
        $ultimoAnita = 0;
        $fuente = 'erp';

        try {
            $ultimoAnita = RendicionAnitaNroOperUnificadoSupport::maxNroOperEnAnita(
                $api,
                $empresaId,
                $this->sistema(),
            );
            $fuente = 'anita_unificado';
        } catch (\Throwable $e) {
            Log::warning('RendicionMaquinaAnita: no se pudo consultar nro_oper unificado', [
                'empresa_id' => $empresaId,
                'mensaje' => $e->getMessage(),
            ]);
            $fuente = 'erp_fallback';
        }

        $siguiente = max($ultimoAnita, $ultimoErp) + 1;

        while (RendicionAnitaNroOperUnificadoSupport::existeEnAlgunaCabecera(
            $api,
            $siguiente,
            $this->sistema(),
            $empresaId,
        )) {
            $siguiente++;
        }

        return [
            'nro_oper' => $siguiente,
            'fuente' => $fuente,
        ];
    }

    public function ultimoNroOperEnErp(): int
    {
        return max(0, (int) (RendicionMaquina::query()
            ->whereNotNull('nro_oper_anita')
            ->max('nro_oper_anita') ?? 0));
    }

    /**
     * Máximo nro_oper de rendiciones ERP que nunca se escribieron en Anita
     * (anita_sincronizado_en null). Sirve para la serie local desde 1.
     */
    public function ultimoNroOperSerieLocalEnErp(): int
    {
        return max(0, (int) (RendicionMaquina::query()
            ->whereNotNull('nro_oper_anita')
            ->whereNull('anita_sincronizado_en')
            ->max('nro_oper_anita') ?? 0));
    }

    public function existsCabeceraEnAnita(RendicionMaquina $rendicion): bool
    {
        $nroOper = (int) ($rendicion->nro_oper_anita ?? 0);
        if ($nroOper <= 0) {
            return false;
        }

        $api = new ApiAnita;
        $rows = ApiAnita::decodificarListaFilas($api->apiCall([
            'acc' => 'list',
            'sistema' => $this->sistema(),
            'tabla' => $this->tablaCabecera(),
            'campos' => 'rendm_nro_oper',
            'whereArmado' => RendicionMaquinaCabeceraAnitaMapper::whereClave($nroOper),
        ]));

        return count($rows) > 0;
    }

    public function insertarEnAnita(RendicionMaquina $rendicion): void
    {
        $ctx = RendicionMaquinaAnitaContextBuilder::desdeRendicion($rendicion);
        $api = new ApiAnita;

        $api->apiCallEscritura([
            'acc' => 'insert',
            'tabla' => $this->tablaCabecera(),
            'sistema' => $this->sistema(),
            'campos' => RendicionMaquinaCabeceraAnitaMapper::camposInsert(),
            'valores' => RendicionMaquinaCabeceraAnitaMapper::valoresInsert($ctx),
        ], 'rendmaquina insert', self::LOG_EVENTO);
    }

    public function actualizarEnAnita(RendicionMaquina $rendicion): void
    {
        $ctx = RendicionMaquinaAnitaContextBuilder::desdeRendicion($rendicion);
        $api = new ApiAnita;
        $nroOper = (int) $ctx['nro_oper'];

        $api->apiCallEscritura([
            'acc' => 'update',
            'tabla' => $this->tablaCabecera(),
            'sistema' => $this->sistema(),
            'valores' => RendicionMaquinaCabeceraAnitaMapper::valoresUpdate($ctx),
            'whereArmado' => RendicionMaquinaCabeceraAnitaMapper::whereClave($nroOper),
        ], 'rendmaquina update', self::LOG_EVENTO);
    }

    public function sincronizarDetalleEnAnita(RendicionMaquina $rendicion): void
    {
        $ctx = RendicionMaquinaAnitaContextBuilder::desdeRendicion($rendicion);
        $nroOper = (int) ($ctx['nro_oper'] ?? 0);
        $tipoOper = (string) ($ctx['tipo_oper'] ?? '');
        if ($nroOper <= 0) {
            return;
        }

        $api = new ApiAnita;
        $this->eliminarDetalleEnAnita($api, $nroOper, $tipoOper);

        foreach (($ctx['lineas_valor'] ?? []) as $linea) {
            $api->apiCallEscritura([
                'acc' => 'insert',
                'tabla' => $this->tablaValor(),
                'sistema' => $this->sistema(),
                'campos' => RendicionMaquinaValorAnitaMapper::camposInsert(),
                'valores' => RendicionMaquinaValorAnitaMapper::valoresInsert($linea, $ctx),
            ], 'rendvalor insert codigo '.($linea['codigo'] ?? 0), self::LOG_EVENTO);
        }

        foreach (($ctx['lineas_gasto'] ?? []) as $linea) {
            $api->apiCallEscritura([
                'acc' => 'insert',
                'tabla' => $this->tablaGasto(),
                'sistema' => $this->sistema(),
                'campos' => RendicionMaquinaGastoAnitaMapper::camposInsert(),
                'valores' => RendicionMaquinaGastoAnitaMapper::valoresInsert($linea, $ctx),
            ], 'rendmapgasto insert codigo '.($linea['codigo'] ?? 0), self::LOG_EVENTO);
        }
    }

    public function eliminarEnAnita(int $nroOper, string $tipoOper): void
    {
        $api = new ApiAnita;
        $this->eliminarDetalleEnAnita($api, $nroOper, $tipoOper);

        $api->apiCallEscritura([
            'acc' => 'delete',
            'tabla' => $this->tablaCabecera(),
            'sistema' => $this->sistema(),
            'whereArmado' => RendicionMaquinaCabeceraAnitaMapper::whereClave($nroOper),
        ], 'rendmaquina delete', self::LOG_EVENTO);
    }

    private function eliminarDetalleEnAnita(ApiAnita $api, int $nroOper, string $tipoOper): void
    {
        $api->apiCallEscritura([
            'acc' => 'delete',
            'tabla' => $this->tablaValor(),
            'sistema' => $this->sistema(),
            'whereArmado' => RendicionMaquinaValorAnitaMapper::wherePorOperacion($nroOper, $tipoOper),
        ], 'rendvalor delete', self::LOG_EVENTO);

        $api->apiCallEscritura([
            'acc' => 'delete',
            'tabla' => $this->tablaGasto(),
            'sistema' => $this->sistema(),
            'whereArmado' => RendicionMaquinaGastoAnitaMapper::wherePorOperacion($nroOper),
        ], 'rendmapgasto delete', self::LOG_EVENTO);
    }

    private function sistema(): string
    {
        return (string) config('rendicion_maquina_anita.sistema', 'caja');
    }

    private function tablaCabecera(): string
    {
        return (string) config('rendicion_maquina_anita.tabla_cabecera', 'rendmaquina');
    }

    private function tablaValor(): string
    {
        return (string) config('rendicion_maquina_anita.tabla_valor', 'rendvalor');
    }

    private function tablaGasto(): string
    {
        return (string) config('rendicion_maquina_anita.tabla_gasto', 'rendmapgasto');
    }

    private function tipoOper(): string
    {
        return substr((string) config('rendicion_maquina_anita.tipo_oper', 'F'), 0, 1);
    }
}
