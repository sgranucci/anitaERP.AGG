<?php

declare(strict_types=1);

namespace App\Services\Caja\Bingo;

use App\ApiAnita;
use App\Models\Caja\Bingo\RendicionBingoCaja;
use App\Support\Caja\AnitaSync\RendicionBingoAnitaContextBuilder;
use App\Support\Caja\AnitaSync\RendicionBingoCabeceraAnitaMapper;
use App\Support\Caja\AnitaSync\RendicionBingoCartonAnitaMapper;
use App\Support\Caja\AnitaSync\RendicionBingoPremioAnitaMapper;
use App\Support\Caja\RendicionGastronomiaSecuenciaSupport;
use Illuminate\Support\Facades\Log;

final class RendicionBingoAnitaSyncService
{
    private const LOG_EVENTO = 'rendicion_bingo.anita_bridge.fallo';

    public function sincronizacionHabilitada(): bool
    {
        return filter_var(config('rendicion_bingo_anita.sincronizar', true), FILTER_VALIDATE_BOOLEAN);
    }

    public function sincronizarDespuesDeGuardar(RendicionBingoCaja $rendicion): void
    {
        if (! $this->sincronizacionHabilitada()) {
            return;
        }

        if ((int) ($rendicion->nro_oper_anita ?? 0) <= 0) {
            $propuesta = $this->proponerSiguienteNroOper((int) $rendicion->empresa_id);
            $rendicion->update([
                'codigo' => $propuesta['codigo'],
                'nro_oper_anita' => $propuesta['nro_oper'],
                'fuente_nro_oper' => $propuesta['fuente'],
            ]);
            $rendicion->refresh();
        }

        if ($this->existsCabeceraEnAnita($rendicion)) {
            $this->actualizarEnAnita($rendicion);
        } else {
            $this->insertarEnAnita($rendicion);
        }

        $this->sincronizarDetalleEnAnita($rendicion);

        $rendicion->update(['anita_sincronizado_en' => now()]);
    }

    /**
     * Reemplaza por completo el detalle de la rendición en Informix
     * (rendcarton + rendpremio) para que quede consistente con la cabecera.
     */
    public function sincronizarDetalleEnAnita(RendicionBingoCaja $rendicion): void
    {
        $ctx = RendicionBingoAnitaContextBuilder::desdeRendicion($rendicion);
        $nroOper = (int) ($ctx['nro_oper'] ?? 0);
        $tipoOper = (string) ($ctx['tipo_oper'] ?? '');
        if ($nroOper <= 0) {
            return;
        }

        $api = new ApiAnita;

        $this->eliminarDetalleEnAnita($api, $nroOper, $tipoOper);

        foreach (($ctx['lineas_carton'] ?? []) as $linea) {
            $api->apiCallEscritura([
                'acc' => 'insert',
                'tabla' => $this->tablaCarton(),
                'sistema' => $this->sistema(),
                'campos' => RendicionBingoCartonAnitaMapper::camposInsert(),
                'valores' => RendicionBingoCartonAnitaMapper::valoresInsert($linea, $ctx),
            ], 'rendcarton insert carton '.($linea['carton'] ?? 0), self::LOG_EVENTO);
        }

        foreach (($ctx['lineas_premio'] ?? []) as $linea) {
            $api->apiCallEscritura([
                'acc' => 'insert',
                'tabla' => $this->tablaPremio(),
                'sistema' => $this->sistema(),
                'campos' => RendicionBingoPremioAnitaMapper::camposInsert(),
                'valores' => RendicionBingoPremioAnitaMapper::valoresInsert($linea, $ctx),
            ], 'rendpremio insert concepto '.($linea['concepto'] ?? 0), self::LOG_EVENTO);
        }
    }

    private function eliminarDetalleEnAnita(ApiAnita $api, int $nroOper, string $tipoOper): void
    {
        $api->apiCallEscritura([
            'acc' => 'delete',
            'tabla' => $this->tablaCarton(),
            'sistema' => $this->sistema(),
            'whereArmado' => RendicionBingoCartonAnitaMapper::wherePorOperacion($nroOper, $tipoOper),
        ], 'rendcarton delete', self::LOG_EVENTO);

        $api->apiCallEscritura([
            'acc' => 'delete',
            'tabla' => $this->tablaPremio(),
            'sistema' => $this->sistema(),
            'whereArmado' => RendicionBingoPremioAnitaMapper::wherePorOperacion($nroOper, $tipoOper),
        ], 'rendpremio delete', self::LOG_EVENTO);
    }

    /**
     * @return array{codigo: string, nro_oper: int, fuente: string}
     */
    public function proponerSiguienteNroOper(int $empresaId): array
    {
        if ($empresaId <= 0) {
            throw new \InvalidArgumentException('Empresa inválida para numeración Anita bingo.');
        }

        $ultimoErp = $this->ultimoNroOperEnErp();
        $ultimoAnita = 0;
        $consultaAnitaOk = false;

        try {
            $ultimoAnita = $this->ultimoNroOperEnAnita();
            $consultaAnitaOk = true;
        } catch (\Throwable $e) {
            Log::warning('RendicionBingoAnita: no se pudo consultar último nro_oper en Anita', [
                'empresa_id' => $empresaId,
                'mensaje' => $e->getMessage(),
            ]);
        }

        $calculo = RendicionGastronomiaSecuenciaSupport::calcularSiguiente(
            $ultimoAnita,
            $ultimoErp,
            (int) config('rendicion_bingo_anita.nro_oper_piso_global', 700001),
            (int) config('rendicion_bingo_anita.nro_oper_techo_global', 0),
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

    public function ultimoNroOperEnErp(): int
    {
        $piso = (int) config('rendicion_bingo_anita.nro_oper_piso_global', 700001);
        $techo = (int) config('rendicion_bingo_anita.nro_oper_techo_global', 0);

        $query = RendicionBingoCaja::query();
        if ($piso > 0) {
            $query->where('nro_oper_anita', '>=', $piso);
        }
        if ($techo > 0) {
            $query->where('nro_oper_anita', '<', $techo);
        }

        $maxCol = (int) ($query->whereNotNull('nro_oper_anita')->max('nro_oper_anita') ?? 0);
        $maxCodigo = 0;

        foreach (RendicionBingoCaja::query()->pluck('codigo') as $codigo) {
            $n = RendicionGastronomiaSecuenciaSupport::extraerNroOperDesdeCodigo((string) $codigo);
            if ($n === null) {
                continue;
            }
            if ($piso > 0 && $n < $piso) {
                continue;
            }
            if ($techo > 0 && $n >= $techo) {
                continue;
            }
            $maxCodigo = max($maxCodigo, $n);
        }

        return max($maxCol, $maxCodigo);
    }

    public function ultimoNroOperEnAnita(): int
    {
        $tipoOper = $this->tipoOper();
        $where = " WHERE rendb_tipo_oper = '".$tipoOper."'";
        $piso = (int) config('rendicion_bingo_anita.nro_oper_piso_global', 700001);
        $techo = (int) config('rendicion_bingo_anita.nro_oper_techo_global', 0);
        if ($piso > 0) {
            $where .= ' AND rendb_nro_oper >= '.$piso;
        }
        if ($techo > 0) {
            $where .= ' AND rendb_nro_oper < '.$techo;
        }

        $api = new ApiAnita;
        $rows = ApiAnita::decodificarListaFilas($api->apiCall([
            'acc' => 'list',
            'sistema' => $this->sistema(),
            'tabla' => $this->tablaCabecera(),
            'campos' => 'rendb_nro_oper',
            'orderBy' => 'rendb_nro_oper desc',
            'whereArmado' => $where,
        ]));

        if ($rows === []) {
            return 0;
        }

        return max(0, (int) ($rows[0]->rendb_nro_oper ?? 0));
    }

    public function existsCabeceraEnAnita(RendicionBingoCaja $rendicion): bool
    {
        $nroOper = (int) ($rendicion->nro_oper_anita
            ?? RendicionBingoCabeceraAnitaMapper::nroOperDesdeCodigo($rendicion->codigo));
        if ($nroOper <= 0) {
            return false;
        }

        $api = new ApiAnita;
        $rows = ApiAnita::decodificarListaFilas($api->apiCall([
            'acc' => 'list',
            'sistema' => $this->sistema(),
            'tabla' => $this->tablaCabecera(),
            'campos' => 'rendb_nro_oper',
            'whereArmado' => RendicionBingoCabeceraAnitaMapper::whereClave($nroOper, $this->tipoOper()),
        ]));

        return count($rows) > 0;
    }

    public function sincronizarDespuesDeEliminar(RendicionBingoCaja $rendicion): void
    {
        if (! $this->sincronizacionHabilitada()) {
            return;
        }

        $nroOper = (int) ($rendicion->nro_oper_anita
            ?? RendicionBingoCabeceraAnitaMapper::nroOperDesdeCodigo($rendicion->codigo));
        if ($nroOper <= 0) {
            return;
        }

        $this->eliminarEnAnita($nroOper, $this->tipoOper());
    }

    public function eliminarEnAnita(int $nroOper, string $tipoOper): void
    {
        $api = new ApiAnita;

        $this->eliminarDetalleEnAnita($api, $nroOper, $tipoOper);

        $api->apiCallEscritura([
            'acc' => 'delete',
            'tabla' => $this->tablaCabecera(),
            'sistema' => $this->sistema(),
            'whereArmado' => RendicionBingoCabeceraAnitaMapper::whereClave($nroOper, $tipoOper),
        ], 'rendbingo delete', self::LOG_EVENTO);
    }

    public function insertarEnAnita(RendicionBingoCaja $rendicion): void
    {
        $ctx = RendicionBingoAnitaContextBuilder::desdeRendicion($rendicion);
        $api = new ApiAnita;

        $api->apiCallEscritura([
            'acc' => 'insert',
            'tabla' => $this->tablaCabecera(),
            'sistema' => $this->sistema(),
            'campos' => RendicionBingoCabeceraAnitaMapper::camposInsert(),
            'valores' => RendicionBingoCabeceraAnitaMapper::valoresInsert($ctx),
        ], 'rendbingo insert', self::LOG_EVENTO);
    }

    public function actualizarEnAnita(RendicionBingoCaja $rendicion): void
    {
        $ctx = RendicionBingoAnitaContextBuilder::desdeRendicion($rendicion);
        $api = new ApiAnita;
        $nroOper = (int) $ctx['nro_oper'];

        $api->apiCallEscritura([
            'acc' => 'update',
            'tabla' => $this->tablaCabecera(),
            'sistema' => $this->sistema(),
            'valores' => RendicionBingoCabeceraAnitaMapper::valoresUpdate($ctx),
            'whereArmado' => RendicionBingoCabeceraAnitaMapper::whereClave($nroOper, $this->tipoOper()),
        ], 'rendbingo update', self::LOG_EVENTO);
    }

    private function sistema(): string
    {
        return (string) config('rendicion_bingo_anita.sistema', 'caja');
    }

    private function tablaCabecera(): string
    {
        return (string) config('rendicion_bingo_anita.tabla_cabecera', 'rendbingo');
    }

    private function tablaPremio(): string
    {
        return (string) config('rendicion_bingo_anita.tabla_premio', 'rendpremio');
    }

    private function tablaCarton(): string
    {
        return (string) config('rendicion_bingo_anita.tabla_carton', 'rendcarton');
    }

    private function tipoOper(): string
    {
        return substr((string) config('rendicion_bingo_anita.tipo_oper', 'F'), 0, 1);
    }
}
