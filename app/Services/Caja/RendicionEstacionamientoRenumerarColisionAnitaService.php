<?php

declare(strict_types=1);

namespace App\Services\Caja;

use App\ApiAnita;
use App\Models\Caja\RendicionEstacionamientoCaja;
use App\Support\Caja\AnitaSync\RendicionEstacionamientoCabeceraAnitaMapper;
use App\Support\Caja\AnitaSync\RendicionEstacionamientoValorAnitaMapper;
use App\Support\Caja\RendicionEstacionamientoNroOperPisoSupport;
use App\Support\Caja\RendicionEstacionamientoSecuenciaSupport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Separa una rendición de estacionamiento que reutilizó nro_oper de rendmaquina/rendbingo.
 * Mueve ERP + rendgastro + rendvalor de la fecha de jornada; no toca rendmaquina ni valores de otra fecha.
 */
final class RendicionEstacionamientoRenumerarColisionAnitaService
{
    private const LOG_EVENTO = 'rendicion_estacionamiento.renumerar_colision_anita';

    public function __construct(
        private readonly RendicionEstacionamientoAnitaSyncService $anitaSyncService,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function ejecutarPorNroOper(int $nroOperAnterior, bool $dryRun = true): array
    {
        $rendicion = RendicionEstacionamientoCaja::query()
            ->where('nro_oper_anita', $nroOperAnterior)
            ->orWhere('codigo', (string) $nroOperAnterior)
            ->first();

        if ($rendicion === null) {
            throw new \InvalidArgumentException(
                'No hay rendición de estacionamiento ERP con nro_oper '.$nroOperAnterior.'.',
            );
        }

        return $this->ejecutar($rendicion, $dryRun);
    }

    /**
     * @return array<string, mixed>
     */
    public function ejecutar(RendicionEstacionamientoCaja $rendicion, bool $dryRun = true): array
    {
        if ($rendicion->esRendicionJornada()) {
            throw new \InvalidArgumentException('La rendición tipo jornada no replica cabecera a Anita.');
        }

        $rendicion->load(['movimientos.cuentacaja', 'puntoventaCae', 'puntoventaCaea', 'turnoOperativo.turno', 'turnoOperativo.jornada']);

        $empresaId = (int) $rendicion->empresa_id;
        $nroAnterior = (int) ($rendicion->nro_oper_anita
            ?? RendicionEstacionamientoCabeceraAnitaMapper::nroOperDesdeCodigo($rendicion->codigo));
        if ($nroAnterior <= 0) {
            throw new \InvalidArgumentException('La rendición #'.$rendicion->id.' no tiene nro_oper Anita.');
        }

        if (RendicionEstacionamientoNroOperPisoSupport::enRango($nroAnterior)) {
            throw new \RuntimeException(
                'El nro_oper '.$nroAnterior.' ya está en el rango dedicado (>= '
                .RendicionEstacionamientoNroOperPisoSupport::piso().').',
            );
        }

        $api = new ApiAnita;
        $sistema = (string) config('rendicion_estacionamiento_anita.sistema', 'caja');
        $tipoOper = (string) config('rendicion_estacionamiento_anita.tipo_oper', 'F');
        $fechaJornada = optional($rendicion->turnoOperativo?->jornada)->fecha_jornada
            ?? substr((string) $rendicion->fecharendicion, 0, 10);
        $fechaEntera = (int) str_replace('-', '', (string) $fechaJornada);

        $maquina = ApiAnita::decodificarListaFilas($api->apiCall([
            'acc' => 'list',
            'sistema' => $sistema,
            'tabla' => 'rendmaquina',
            'campos' => 'rendm_nro_oper,rendm_fecha,rendm_empresa,rendm_turno',
            'whereArmado' => " WHERE rendm_nro_oper = '".$nroAnterior."' ",
        ]));
        $bingo = ApiAnita::decodificarListaFilas($api->apiCall([
            'acc' => 'list',
            'sistema' => $sistema,
            'tabla' => 'rendbingo',
            'campos' => 'rendb_nro_oper,rendb_fecha,rendb_empresa',
            'whereArmado' => " WHERE rendb_nro_oper = '".$nroAnterior."' ",
        ]));

        $valoresAnteriores = ApiAnita::decodificarListaFilas($api->apiCall([
            'acc' => 'list',
            'sistema' => $sistema,
            'tabla' => 'rendvalor',
            'campos' => 'rendv_nro_oper,rendv_codigo,rendv_total,rendv_fecha',
            'whereArmado' => RendicionEstacionamientoValorAnitaMapper::wherePorOperacion($nroAnterior, $tipoOper),
            'orderBy' => 'rendv_fecha, rendv_codigo',
        ]));

        $valoresEstac = 0;
        $valoresOtrasFechas = 0;
        foreach ($valoresAnteriores as $fila) {
            if ((int) ($fila->rendv_fecha ?? 0) === $fechaEntera) {
                $valoresEstac++;
            } else {
                $valoresOtrasFechas++;
            }
        }

        if ($dryRun) {
            $calculo = RendicionEstacionamientoSecuenciaSupport::calcularSiguiente(
                $this->anitaSyncService->ultimoNroOperEnAnita($empresaId),
                $this->anitaSyncService->ultimoNroOperEnErp($empresaId),
                RendicionEstacionamientoNroOperPisoSupport::piso(),
                RendicionEstacionamientoNroOperPisoSupport::techo(),
            );
            $nroNuevo = (int) $calculo['siguiente'];
            $fuenteNro = $calculo['fuente'] ?? null;
        } else {
            $propuesta = $this->anitaSyncService->proponerSiguienteNroOper($empresaId);
            $nroNuevo = (int) $propuesta['nro_oper'];
            $fuenteNro = $propuesta['fuente'] ?? null;
        }

        if ($nroNuevo <= 0 || $nroNuevo === $nroAnterior
            || ! RendicionEstacionamientoNroOperPisoSupport::enRango($nroNuevo)) {
            throw new \RuntimeException('No se obtuvo nro_oper libre en rango dedicado para renumerar.');
        }

        $resultado = [
            'rendicion_id' => (int) $rendicion->id,
            'empresa_id' => $empresaId,
            'nro_oper_anterior' => $nroAnterior,
            'nro_oper_nuevo' => $nroNuevo,
            'fecha_jornada' => $fechaJornada,
            'fecha_entera' => $fechaEntera,
            'colision_maquina' => $maquina !== [],
            'colision_bingo' => $bingo !== [],
            'valores_estac' => $valoresEstac,
            'valores_otras_fechas_conservados' => $valoresOtrasFechas,
            'fuente_nro' => $fuenteNro,
            'dry_run' => $dryRun,
            'estado' => $dryRun ? 'simulado' : 'pendiente',
        ];

        if ($dryRun) {
            return $resultado;
        }

        DB::transaction(function () use ($rendicion, $nroNuevo, $fuenteNro) {
            $rendicion->update([
                'codigo' => (string) $nroNuevo,
                'nro_oper_anita' => $nroNuevo,
                'fuente_nro_oper' => $fuenteNro ?? 'rango_850000',
                'anita_sincronizado_en' => null,
            ]);
        });

        $rendicion->refresh();
        $rendicion->load(['movimientos.cuentacaja', 'puntoventaCae', 'puntoventaCaea', 'turnoOperativo.turno', 'turnoOperativo.jornada']);

        $this->anitaSyncService->insertarEnAnita($rendicion);

        $api->apiCallEscritura([
            'acc' => 'delete',
            'tabla' => 'rendvalor',
            'sistema' => $sistema,
            'whereArmado' => RendicionEstacionamientoValorAnitaMapper::wherePorOperacion($nroAnterior, $tipoOper)
                ." AND rendv_fecha = '".$fechaEntera."' ",
        ], 'rendvalor delete fecha estac colision', self::LOG_EVENTO);

        $api->apiCallEscritura([
            'acc' => 'delete',
            'tabla' => 'rendgastro',
            'sistema' => $sistema,
            'whereArmado' => RendicionEstacionamientoCabeceraAnitaMapper::whereClave($nroAnterior, $tipoOper),
        ], 'rendgastro delete colision', self::LOG_EVENTO);

        $rendicion->update(['anita_sincronizado_en' => now()]);

        Log::info(self::LOG_EVENTO, $resultado + ['estado' => 'ok']);
        $resultado['estado'] = 'ok';

        return $resultado;
    }
}
