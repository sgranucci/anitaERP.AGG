<?php

namespace App\Services\Caja;

use App\Models\Caja\RendicionGastronomiaCaja;
use App\Models\Caja\RendicionGastronomiaMovimientoCaja;
use App\Models\Ventas\JornadaGastronomia;
use App\Models\Ventas\TurnoOperativoGastronomia;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Presenta jornadas cerradas de prueba en Caja (rendiciones turno + jornada) sin bridge Anita.
 */
final class RendicionGastronomiaPresentacionPruebaService
{
    private const OBSERVACION = 'Prueba sistema — presentado sin Anita';

    public function __construct(
        private readonly RendicionGastronomiaCajaService $cajaService,
        private readonly RendicionGastronomiaJornadaPresentacionService $jornadaPresentacionService,
    ) {
    }

    /**
     * @return array{
     *   jornadas_presentadas: list<int>,
     *   jornadas_omitidas: list<int>,
     *   turnos_rendidos: list<int>,
     *   errores: list<string>
     * }
     */
    public function presentarJornadasCerradasHasta(
        int $empresaId,
        string $fechaJornadaHasta,
        int $cajaId,
        int $usuarioId,
        bool $dryRun = false,
    ): array {
        if ($empresaId <= 0) {
            throw new InvalidArgumentException('Empresa inválida.');
        }
        if ($cajaId <= 0) {
            throw new InvalidArgumentException('Caja inválida.');
        }
        if ($usuarioId <= 0) {
            throw new InvalidArgumentException('Usuario inválido.');
        }

        $resultado = [
            'jornadas_presentadas' => [],
            'jornadas_omitidas' => [],
            'turnos_rendidos' => [],
            'errores' => [],
        ];

        $jornadas = JornadaGastronomia::query()
            ->where('empresa_id', $empresaId)
            ->where('estado', JornadaGastronomia::ESTADO_CERRADA)
            ->whereNotNull('cierre_en')
            ->whereDate('fecha_jornada', '<=', $fechaJornadaHasta)
            ->orderBy('fecha_jornada')
            ->orderBy('id')
            ->get();

        foreach ($jornadas as $jornada) {
            if ($this->jornadaPresentacionService->jornadaYaRendida((int) $jornada->id)) {
                $resultado['jornadas_omitidas'][] = (int) $jornada->id;

                continue;
            }

            $pendientes = $this->jornadaPresentacionService->turnosCerradosSinRendirEnJornada((int) $jornada->id);
            foreach ($pendientes as $turno) {
                try {
                    if ($dryRun) {
                        $resultado['turnos_rendidos'][] = (int) $turno->id;

                        continue;
                    }
                    $this->rendirTurnoSinAnita((int) $turno->id, $cajaId, $usuarioId);
                    $resultado['turnos_rendidos'][] = (int) $turno->id;
                } catch (\Throwable $e) {
                    $resultado['errores'][] = 'Turno #'.$turno->id.' (jornada #'.$jornada->id.'): '.$e->getMessage();
                }
            }

            if (! $dryRun && $this->jornadaPresentacionService->turnosCerradosSinRendirEnJornada((int) $jornada->id)->isNotEmpty()) {
                $resultado['errores'][] = 'Jornada #'.$jornada->id.': quedaron turnos sin rendir; no se presentó.';

                continue;
            }

            try {
                if ($dryRun) {
                    $resultado['jornadas_presentadas'][] = (int) $jornada->id;

                    continue;
                }
                $this->presentarJornadaSinAnita((int) $jornada->id, $cajaId, $usuarioId);
                $resultado['jornadas_presentadas'][] = (int) $jornada->id;
            } catch (\Throwable $e) {
                $resultado['errores'][] = 'Jornada #'.$jornada->id.': '.$e->getMessage();
            }
        }

        return $resultado;
    }

    public function rendirTurnoSinAnita(int $turnoId, int $cajaId, int $usuarioId): RendicionGastronomiaCaja
    {
        if (RendicionGastronomiaCaja::query()
            ->where('turno_operativo_gastronomia_id', $turnoId)
            ->exists()) {
            throw new InvalidArgumentException('El turno #'.$turnoId.' ya fue rendido.');
        }

        $datos = $this->cajaService->datosDesdeTurno($turnoId);
        $turno = TurnoOperativoGastronomia::query()->findOrFail($turnoId);

        $cabecera = [
            'tipo' => RendicionGastronomiaCaja::TIPO_TURNO,
            'codigo' => $this->codigoPruebaTurno($turnoId),
            'empresa_id' => (int) $datos['empresa_id'],
            'puntoventa_cae_id' => (int) $datos['puntoventa_cae_id'],
            'puntoventa_caea_id' => (int) $datos['puntoventa_caea_id'],
            'caja_id' => $cajaId,
            'fecharendicion' => $turno->cierre_en?->format('Y-m-d H:i:s') ?? now()->format('Y-m-d H:i:s'),
            'iniciodelfondo' => (float) ($datos['iniciodelfondo'] ?? 0),
            'totalfactura' => (float) ($datos['totalfactura'] ?? 0),
            'totalcobrado' => (float) ($datos['totalcobrado'] ?? 0),
            'totalinvitacion' => (float) ($datos['totalinvitacion'] ?? 0),
            'totalnotacredito' => (float) ($datos['totalnotacredito'] ?? 0),
            'totalredondeo' => (float) ($datos['totalredondeo'] ?? 0),
            'totalredondeoinvitacion' => (float) ($datos['totalredondeoinvitacion'] ?? 0),
            'sobrantefaltante' => (float) ($datos['sobrantefaltante'] ?? 0),
            'turno_operativo_gastronomia_id' => $turnoId,
            'jornada_gastronomia_id' => null,
            'observacion' => self::OBSERVACION,
            'nro_oper_anita' => null,
            'fuente_nro_oper' => null,
            'anita_sincronizado_en' => null,
            'creousuario_id' => $usuarioId,
        ];

        $movimientos = $this->movimientosPersistiblesDesdeDatos($datos['movimientos'] ?? []);

        return $this->persistirSinAnita($cabecera, $movimientos);
    }

    public function presentarJornadaSinAnita(int $jornadaId, int $cajaId, int $usuarioId): RendicionGastronomiaCaja
    {
        if ($this->jornadaPresentacionService->jornadaYaRendida($jornadaId)) {
            throw new InvalidArgumentException('La jornada #'.$jornadaId.' ya fue presentada.');
        }

        $jornada = JornadaGastronomia::query()->findOrFail($jornadaId);
        $errores = $this->jornadaPresentacionService->erroresAntesDeRendir($jornada);
        if ($errores !== []) {
            throw new InvalidArgumentException(implode(' ', $errores));
        }

        $datos = $this->cajaService->datosDesdeJornada($jornadaId);
        $marcadores = $this->jornadaPresentacionService->resolverMarcadoresAuditoria($jornada);

        $cabecera = [
            'tipo' => RendicionGastronomiaCaja::TIPO_JORNADA,
            'codigo' => $this->jornadaPresentacionService->proponerCodigoInterno((int) $jornada->empresa_id, $jornadaId),
            'empresa_id' => (int) $datos['empresa_id'],
            'puntoventa_cae_id' => (int) $datos['puntoventa_cae_id'],
            'puntoventa_caea_id' => (int) $datos['puntoventa_caea_id'],
            'caja_id' => $cajaId,
            'fecharendicion' => $jornada->cierre_en?->format('Y-m-d H:i:s') ?? now()->format('Y-m-d H:i:s'),
            'iniciodelfondo' => 0.0,
            'totalfactura' => (float) ($datos['totalfactura'] ?? 0),
            'totalcobrado' => (float) ($datos['totalcobrado'] ?? 0),
            'totalinvitacion' => (float) ($datos['totalinvitacion'] ?? 0),
            'totalnotacredito' => (float) ($datos['totalnotacredito'] ?? 0),
            'totalredondeo' => (float) ($datos['totalredondeo'] ?? 0),
            'totalredondeoinvitacion' => (float) ($datos['totalredondeoinvitacion'] ?? 0),
            'sobrantefaltante' => (float) ($datos['sobrantefaltante'] ?? 0),
            'turno_operativo_gastronomia_id' => null,
            'jornada_gastronomia_id' => $jornadaId,
            'waitry_order_id_hasta' => (int) ($marcadores['waitry_order_id_hasta'] ?? 0),
            'cierre_totem_jornada_gastronomia_id' => $marcadores['cierre_totem_jornada_gastronomia_id'] ?? null,
            'numeracion_comprobantes_json' => $marcadores['numeracion_comprobantes_json'] ?? null,
            'observacion' => self::OBSERVACION,
            'nro_oper_anita' => null,
            'fuente_nro_oper' => null,
            'anita_sincronizado_en' => null,
            'creousuario_id' => $usuarioId,
        ];

        $movimientos = $this->movimientosPersistiblesDesdeDatos($datos['movimientos'] ?? []);

        return $this->persistirSinAnita($cabecera, $movimientos);
    }

    private function codigoPruebaTurno(int $turnoId): string
    {
        return sprintf('PRU-T-%d', $turnoId);
    }

    /**
     * @param  list<array<string, mixed>>  $movimientosDatos
     * @return list<array{cuentacaja_id:int, monto:float, cotizacion:float}>
     */
    private function movimientosPersistiblesDesdeDatos(array $movimientosDatos): array
    {
        $raw = [];
        foreach ($movimientosDatos as $row) {
            if (! empty($row['es_nota_credito'])) {
                continue;
            }
            $raw[] = [
                'cuentacaja_id' => (int) ($row['cuentacaja_id'] ?? 0),
                'monto' => (float) ($row['monto'] ?? 0),
                'cotizacion' => (float) ($row['cotizacion'] ?? 1),
            ];
        }

        return $this->cajaService->normalizarMovimientosRequest($raw);
    }

    /**
     * @param  array<string, mixed>  $cabecera
     * @param  list<array{cuentacaja_id:int, monto:float, cotizacion:float}>  $movimientos
     */
    private function persistirSinAnita(array $cabecera, array $movimientos): RendicionGastronomiaCaja
    {
        return DB::transaction(function () use ($cabecera, $movimientos) {
            $rendicion = RendicionGastronomiaCaja::create($cabecera);

            foreach ($movimientos as $row) {
                RendicionGastronomiaMovimientoCaja::create([
                    'rendicion_gastronomia_caja_id' => $rendicion->id,
                    'cuentacaja_id' => $row['cuentacaja_id'],
                    'monto' => $row['monto'],
                    'cotizacion' => $row['cotizacion'],
                ]);
            }

            return $rendicion->fresh(['movimientos.cuentacaja']);
        });
    }
}
