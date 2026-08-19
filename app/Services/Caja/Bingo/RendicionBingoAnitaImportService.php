<?php

declare(strict_types=1);

namespace App\Services\Caja\Bingo;

use App\ApiAnita;
use App\Models\Caja\Bingo\BingoCarton;
use App\Models\Caja\Bingo\BingoConceptoRendicion;
use App\Models\Caja\Bingo\ConfiguracionPuntoventaBingo;
use App\Models\Caja\Bingo\JornadaBingo;
use App\Models\Caja\Bingo\RendicionBingoCaja;
use App\Models\Caja\Bingo\TurnoOperativoBingo;
use App\Models\Configuracion\Empresa;
use App\Models\Seguridad\Usuario;
use App\Support\Caja\AnitaSync\RendicionBingoAnitaImportMapper;
use App\Support\Caja\Bingo\BingoRendicionCalculoSupport;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Importa rendiciones bingo cargadas en Anita nativo (rendbingo) hacia el ERP.
 */
final class RendicionBingoAnitaImportService
{
    /**
     * @param  callable(string):void|null  $log
     * @return array{
     *   leidos: int,
     *   creados: int,
     *   omitidos: int,
     *   errores: list<string>,
     *   importados: list<array<string, mixed>>
     * }
     */
    public function importarRango(
        string $fechaDesde,
        string $fechaHasta,
        ?callable $log = null,
        bool $dryRun = false,
    ): array {
        $desde = Carbon::parse($fechaDesde)->startOfDay();
        $hasta = Carbon::parse($fechaHasta)->startOfDay();
        if ($desde->gt($hasta)) {
            [$desde, $hasta] = [$hasta, $desde];
        }

        $reportar = static function (string $mensaje) use ($log): void {
            if ($log !== null) {
                $log($mensaje);
            }
        };

        $filas = $this->listarCabecerasAnita((int) $desde->format('Ymd'), (int) $hasta->format('Ymd'));
        $empresas = Empresa::query()->get(['id', 'codigo', 'nombre']);
        $nrosErp = RendicionBingoCaja::query()
            ->whereNotNull('nro_oper_anita')
            ->pluck('nro_oper_anita')
            ->map(fn ($n) => (int) $n)
            ->filter(fn (int $n) => $n > 0)
            ->all();
        $nrosErp = array_fill_keys($nrosErp, true);

        $leidos = 0;
        $creados = 0;
        $omitidos = 0;
        $errores = [];
        $importados = [];

        foreach ($filas as $cabecera) {
            $leidos++;
            $nroOper = (int) ($cabecera->rendb_nro_oper ?? 0);
            $empresaAnita = (int) ($cabecera->rendb_empresa ?? 0);
            $fechaEntera = (int) ($cabecera->rendb_fecha ?? 0);
            $etiqueta = sprintf(
                'nro %d empAnita %d fecha %d',
                $nroOper,
                $empresaAnita,
                $fechaEntera
            );

            if ($nroOper <= 0 || $fechaEntera < 10000101) {
                $omitidos++;
                $reportar('Omitida (clave inválida): '.$etiqueta);

                continue;
            }

            if (isset($nrosErp[$nroOper])) {
                $omitidos++;

                continue;
            }

            $empresaId = RendicionBingoAnitaImportMapper::empresaIdDesdeCodigoAnita($empresaAnita, $empresas);
            if ($empresaId === null) {
                $omitidos++;
                $msg = 'Empresa Anita '.$empresaAnita.' sin mapear ('.$etiqueta.')';
                $errores[] = $msg;
                $reportar($msg);

                continue;
            }

            $fechaJornada = RendicionBingoAnitaImportMapper::fechaJornadaDesdeEntera($fechaEntera);
            $empresaNombre = (string) ($empresas->firstWhere('id', $empresaId)?->nombre ?? $empresaId);

            try {
                $detalle = $this->armarPayload($cabecera, $empresaId, $fechaJornada, $nroOper);
            } catch (\Throwable $e) {
                $errores[] = $etiqueta.': '.$e->getMessage();
                $reportar('Error '.$etiqueta.': '.$e->getMessage());

                continue;
            }

            $reportar(sprintf(
                '%s %s %s turnoERP=%s cart=%d tot=%s dep=%s',
                $dryRun ? 'Faltante' : 'Importando',
                $empresaNombre,
                $fechaJornada,
                $detalle['turno_operativo_bingo_id'] !== null ? '#'.$detalle['turno_operativo_bingo_id'] : '—',
                $detalle['cant_cartones'],
                number_format((float) $detalle['total_cartones'], 2, ',', '.'),
                number_format((float) $detalle['deposito'], 2, ',', '.'),
            ));

            if ($dryRun) {
                $creados++;
                $importados[] = [
                    'nro_oper' => $nroOper,
                    'empresa_id' => $empresaId,
                    'fecha_jornada' => $fechaJornada,
                    'dry_run' => true,
                ];

                continue;
            }

            try {
                $rendicion = DB::transaction(fn () => $this->persistir($detalle));
            } catch (\Throwable $e) {
                $errores[] = $etiqueta.': '.$e->getMessage();
                $reportar('Error al grabar '.$etiqueta.': '.$e->getMessage());

                continue;
            }

            $nrosErp[$nroOper] = true;
            $creados++;
            $importados[] = [
                'id' => (int) $rendicion->id,
                'nro_oper' => $nroOper,
                'empresa_id' => $empresaId,
                'fecha_jornada' => $fechaJornada,
            ];
        }

        return [
            'leidos' => $leidos,
            'creados' => $creados,
            'omitidos' => $omitidos,
            'errores' => $errores,
            'importados' => $importados,
        ];
    }

    /**
     * @return list<object>
     */
    private function listarCabecerasAnita(int $desdeEntera, int $hastaEntera): array
    {
        $campos = implode(',', [
            'rendb_nro_oper',
            'rendb_tipo_oper',
            'rendb_caja',
            'rendb_cajero',
            'rendb_fecha',
            'rendb_hora',
            'rendb_usuario',
            'rendb_sobrante',
            'rendb_vales',
            'rendb_redondeo',
            'rendb_deposito',
            'rendb_cant_carton',
            'rendb_total_carton',
            'rendb_observacion',
            'rendb_empresa',
            'rendb_estado',
            'rendb_refuer_prest',
            'rendb_turno',
            'rendb_fecha_carga',
            'rendb_hora_carga',
        ]);

        $parsed = ApiAnita::parsearRespuestaLista((new ApiAnita)->apiCall([
            'acc' => 'list',
            'sistema' => (string) config('rendicion_bingo_anita.sistema', 'caja'),
            'tabla' => (string) config('rendicion_bingo_anita.tabla_cabecera', 'rendbingo'),
            'campos' => $campos,
            'orderBy' => 'rendb_fecha,rendb_empresa,rendb_nro_oper',
            'whereArmado' => ' WHERE rendb_fecha >= '.$desdeEntera
                .' AND rendb_fecha <= '.$hastaEntera,
        ]));

        if ($parsed['error_lectura'] !== null) {
            throw new RuntimeException('No se pudo listar rendbingo Anita: '.$parsed['error_lectura']);
        }

        return $parsed['filas'];
    }

    /**
     * @return list<object>
     */
    private function listarDetalleAnita(string $tabla, string $campos, string $where): array
    {
        $parsed = ApiAnita::parsearRespuestaLista((new ApiAnita)->apiCall([
            'acc' => 'list',
            'sistema' => (string) config('rendicion_bingo_anita.sistema', 'caja'),
            'tabla' => $tabla,
            'campos' => $campos,
            'whereArmado' => $where,
        ]));

        if ($parsed['error_lectura'] !== null) {
            throw new RuntimeException('No se pudo listar '.$tabla.': '.$parsed['error_lectura']);
        }

        return $parsed['filas'];
    }

    /**
     * @return array<string, mixed>
     */
    private function armarPayload(object $cabecera, int $empresaId, string $fechaJornada, int $nroOper): array
    {
        $jornada = $this->buscarJornada($empresaId, $fechaJornada);
        $turno = $this->buscarTurnoPendiente($empresaId, $jornada, $cabecera);

        if ($turno !== null) {
            return $this->payloadDesdeTurno($turno, $cabecera, $nroOper, $fechaJornada);
        }

        return $this->payloadDesdeAnita($cabecera, $empresaId, $fechaJornada, $nroOper, $jornada);
    }

    /**
     * @return array<string, mixed>
     */
    private function payloadDesdeTurno(
        TurnoOperativoBingo $turno,
        object $cabecera,
        int $nroOper,
        string $fechaJornada,
    ): array {
        $turno->loadMissing(['jornada', 'configuracionPuntoventa']);
        $cartones = is_array($turno->cartones_rendicion_json) ? $turno->cartones_rendicion_json : [];
        $conceptosPayload = $turno->conceptos_rendicion_json ?? [];
        $lineasConcepto = is_array($conceptosPayload['lineas'] ?? null)
            ? $conceptosPayload['lineas']
            : (is_array($conceptosPayload) && array_is_list($conceptosPayload) ? $conceptosPayload : []);

        $cantCartones = 0;
        $totalCartones = 0.0;
        foreach ($cartones as $linea) {
            if (! empty($linea['anulado'])) {
                continue;
            }
            $cantidad = (int) ($linea['cantidad'] ?? 0);
            $cantCartones += $cantidad;
            $totalCartones = round($totalCartones + $cantidad * (float) ($linea['precio_unitario'] ?? 0), 2);
        }

        $cajero = $this->resolverUsuario((int) ($cabecera->rendb_cajero ?? 0), (int) ($turno->usuario_cierre_id ?? 0));

        return [
            'codigo' => (string) $nroOper,
            'nro_oper_anita' => $nroOper,
            'fuente_nro_oper' => RendicionBingoAnitaImportMapper::FUENTE_NRO_OPER,
            'empresa_id' => (int) $turno->empresa_id,
            'cuentacaja_id' => $turno->configuracionPuntoventa?->cuentacaja_id,
            'turno_operativo_bingo_id' => (int) $turno->id,
            'jornada_bingo_id' => (int) $turno->jornada_bingo_id,
            'creousuario_id' => $cajero,
            'fecharendicion' => RendicionBingoAnitaImportMapper::fechaHoraRendicion($cabecera),
            'fecha_jornada' => $fechaJornada,
            'cant_cartones' => $cantCartones > 0 ? $cantCartones : (int) ($cabecera->rendb_cant_carton ?? 0),
            'total_cartones' => $totalCartones > 0
                ? $totalCartones
                : round((float) ($cabecera->rendb_total_carton ?? 0), 2),
            'deposito' => round((float) ($turno->deposito ?? $cabecera->rendb_deposito ?? 0), 2),
            'saldo_final' => round((float) ($turno->monto_rendicion_turno ?? $turno->deposito ?? 0), 2),
            'sobrante_faltante' => round((float) ($turno->sobrante_faltante ?? 0), 2),
            'vales' => round((float) ($turno->vales ?? 0), 2),
            'redondeo' => round((float) ($turno->redondeo ?? 0), 2),
            'refuerzo_prestamo' => 0.0,
            'cartones_json' => $cartones,
            'conceptos_json' => $lineasConcepto,
            'medios_contado_json' => $turno->medios_contado_cierre_json,
            'observacion' => $this->observacionImportacion($cabecera),
            'cerro_turno' => false,
            'marcar_turno_presentado' => true,
            'crear_jornada' => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function payloadDesdeAnita(
        object $cabecera,
        int $empresaId,
        string $fechaJornada,
        int $nroOper,
        ?JornadaBingo $jornada,
    ): array {
        $tipoOper = substr((string) ($cabecera->rendb_tipo_oper ?? config('rendicion_bingo_anita.tipo_oper', 'F')), 0, 1);
        $tipoOperSql = addslashes($tipoOper);
        $filasCarton = $this->listarDetalleAnita(
            (string) config('rendicion_bingo_anita.tabla_carton', 'rendcarton'),
            'rendc_nro_oper,rendc_tipo_oper,rendc_carton,rendc_valor,rendc_cantidad,rendc_total,rendc_fecha',
            " WHERE rendc_nro_oper = {$nroOper} AND rendc_tipo_oper = '{$tipoOperSql}'",
        );
        $filasPremio = $this->listarDetalleAnita(
            (string) config('rendicion_bingo_anita.tabla_premio', 'rendpremio'),
            'rendp_nro_oper,rendp_tipo_oper,rendp_concepto,rendp_porcentaje,rendp_pagado,rendp_fecha,rendp_real',
            " WHERE rendp_nro_oper = {$nroOper} AND rendp_tipo_oper = '{$tipoOperSql}'",
        );

        $cartones = BingoCarton::query()->where('empresa_id', $empresaId)->get();
        $conceptos = BingoConceptoRendicion::query()
            ->where('empresa_id', $empresaId)
            ->where('estado', BingoConceptoRendicion::ESTADO_ACTIVO)
            ->orderBy('orden')
            ->orderBy('id')
            ->get();

        $lineasCarton = RendicionBingoAnitaImportMapper::lineasCarton($filasCarton, $cartones);
        $montos = RendicionBingoAnitaImportMapper::montosManuales($filasPremio, $conceptos, $cabecera);

        $calculoSinAjuste = BingoRendicionCalculoSupport::calcular($lineasCarton, $conceptos, $montos);
        $deposito = round((float) ($cabecera->rendb_deposito ?? 0), 2);
        $montos = RendicionBingoAnitaImportMapper::aplicarAjusteDeposito(
            $montos,
            $conceptos,
            (float) $calculoSinAjuste['saldo_final'],
            $deposito,
        );
        $calculo = BingoRendicionCalculoSupport::calcular($lineasCarton, $conceptos, $montos);

        $cajero = $this->resolverUsuario((int) ($cabecera->rendb_cajero ?? 0));

        return [
            'codigo' => (string) $nroOper,
            'nro_oper_anita' => $nroOper,
            'fuente_nro_oper' => RendicionBingoAnitaImportMapper::FUENTE_NRO_OPER,
            'empresa_id' => $empresaId,
            'cuentacaja_id' => $this->resolverCuentacaja($empresaId),
            'turno_operativo_bingo_id' => null,
            'jornada_bingo_id' => $jornada?->id,
            'creousuario_id' => $cajero,
            'fecharendicion' => RendicionBingoAnitaImportMapper::fechaHoraRendicion($cabecera),
            'fecha_jornada' => $fechaJornada,
            'cant_cartones' => (int) ($calculo['cant_cartones'] ?? $cabecera->rendb_cant_carton ?? 0),
            'total_cartones' => round((float) ($calculo['total_cartones'] ?? $cabecera->rendb_total_carton ?? 0), 2),
            'deposito' => $deposito,
            'saldo_final' => round((float) ($calculo['saldo_final'] ?? $deposito), 2),
            'sobrante_faltante' => round((float) ($cabecera->rendb_sobrante ?? 0), 2),
            'vales' => round((float) ($cabecera->rendb_vales ?? 0), 2),
            'redondeo' => round((float) ($cabecera->rendb_redondeo ?? 0), 2),
            'refuerzo_prestamo' => round((float) ($cabecera->rendb_refuer_prest ?? 0), 2),
            'cartones_json' => $lineasCarton,
            'conceptos_json' => $calculo['lineas_concepto'] ?? [],
            'medios_contado_json' => null,
            'observacion' => $this->observacionImportacion($cabecera),
            'cerro_turno' => false,
            'marcar_turno_presentado' => false,
            'crear_jornada' => $jornada === null,
            'usuario_jornada_id' => $cajero,
        ];
    }

    /**
     * @param  array<string, mixed>  $detalle
     */
    private function persistir(array $detalle): RendicionBingoCaja
    {
        $jornadaId = $detalle['jornada_bingo_id'] ?? null;
        if (($detalle['crear_jornada'] ?? false) === true && (int) $jornadaId <= 0) {
            $jornada = JornadaBingo::query()->create([
                'empresa_id' => (int) $detalle['empresa_id'],
                'fecha_jornada' => $detalle['fecha_jornada'],
                'estado' => JornadaBingo::ESTADO_CERRADA,
                'usuario_apertura_id' => (int) $detalle['usuario_jornada_id'],
                'usuario_cierre_id' => (int) $detalle['usuario_jornada_id'],
                'apertura_en' => $detalle['fecharendicion'],
                'cierre_en' => $detalle['fecharendicion'],
                'observacion_apertura' => 'Importada desde Anita nativo',
                'observacion_cierre' => 'Importada desde Anita nativo',
            ]);
            $jornadaId = (int) $jornada->id;
        }

        $rendicion = RendicionBingoCaja::query()->create([
            'codigo' => $detalle['codigo'],
            'nro_oper_anita' => $detalle['nro_oper_anita'],
            'fuente_nro_oper' => $detalle['fuente_nro_oper'],
            'anita_sincronizado_en' => now(),
            'empresa_id' => $detalle['empresa_id'],
            'cuentacaja_id' => $detalle['cuentacaja_id'],
            'turno_operativo_bingo_id' => $detalle['turno_operativo_bingo_id'],
            'jornada_bingo_id' => $jornadaId,
            'creousuario_id' => $detalle['creousuario_id'],
            'fecharendicion' => $detalle['fecharendicion'],
            'fecha_jornada' => $detalle['fecha_jornada'],
            'cant_cartones' => $detalle['cant_cartones'],
            'total_cartones' => $detalle['total_cartones'],
            'deposito' => $detalle['deposito'],
            'saldo_final' => $detalle['saldo_final'],
            'sobrante_faltante' => $detalle['sobrante_faltante'],
            'vales' => $detalle['vales'],
            'redondeo' => $detalle['redondeo'],
            'refuerzo_prestamo' => $detalle['refuerzo_prestamo'] ?? 0,
            'cartones_json' => $detalle['cartones_json'],
            'conceptos_json' => $detalle['conceptos_json'],
            'medios_contado_json' => $detalle['medios_contado_json'],
            'observacion' => $detalle['observacion'],
            'cerro_turno' => false,
        ]);

        if (($detalle['marcar_turno_presentado'] ?? false) === true
            && (int) ($detalle['turno_operativo_bingo_id'] ?? 0) > 0) {
            TurnoOperativoBingo::query()
                ->whereKey((int) $detalle['turno_operativo_bingo_id'])
                ->update(['rendicion_presentada' => true]);
        }

        return $rendicion;
    }

    private function buscarJornada(int $empresaId, string $fechaJornada): ?JornadaBingo
    {
        return JornadaBingo::query()
            ->where('empresa_id', $empresaId)
            ->whereDate('fecha_jornada', $fechaJornada)
            ->orderByDesc('id')
            ->first();
    }

    private function buscarTurnoPendiente(int $empresaId, ?JornadaBingo $jornada, object $cabecera): ?TurnoOperativoBingo
    {
        if ($jornada === null) {
            return null;
        }

        $candidatos = TurnoOperativoBingo::query()
            ->with(['configuracionPuntoventa'])
            ->where('empresa_id', $empresaId)
            ->where('jornada_bingo_id', $jornada->id)
            ->where('estado', TurnoOperativoBingo::ESTADO_CERRADO)
            ->where('rendicion_presentada', false)
            ->whereNotNull('cierre_en')
            ->get();

        if ($candidatos->isEmpty()) {
            return null;
        }

        $deposito = round((float) ($cabecera->rendb_deposito ?? 0), 2);
        $cant = (int) ($cabecera->rendb_cant_carton ?? 0);
        $porDeposito = $candidatos->first(
            fn (TurnoOperativoBingo $t) => abs(round((float) ($t->deposito ?? 0), 2) - $deposito) < 0.02
        );
        if ($porDeposito !== null) {
            return $porDeposito;
        }

        return $candidatos->first(function (TurnoOperativoBingo $t) use ($cant) {
            $suma = 0;
            foreach ($t->cartones_rendicion_json ?? [] as $linea) {
                if (! empty($linea['anulado'])) {
                    continue;
                }
                $suma += (int) ($linea['cantidad'] ?? 0);
            }

            return $cant > 0 && $suma === $cant;
        });
    }

    private function resolverCuentacaja(int $empresaId): ?int
    {
        $desdePv = ConfiguracionPuntoventaBingo::query()
            ->where('empresa_id', $empresaId)
            ->whereNotNull('cuentacaja_id')
            ->value('cuentacaja_id');
        if ((int) $desdePv > 0) {
            return (int) $desdePv;
        }

        $desdeRendicion = RendicionBingoCaja::query()
            ->where('empresa_id', $empresaId)
            ->whereNotNull('cuentacaja_id')
            ->orderByDesc('id')
            ->value('cuentacaja_id');

        return (int) $desdeRendicion > 0 ? (int) $desdeRendicion : null;
    }

    private function resolverUsuario(int $preferido, int $alternativo = 0): int
    {
        foreach ([$preferido, $alternativo] as $id) {
            if ($id > 0 && Usuario::query()->whereKey($id)->exists()) {
                return $id;
            }
        }

        $admin = (int) (Usuario::query()->where('usuario', 'admin')->value('id') ?? 0);

        return $admin > 0 ? $admin : 1;
    }

    private function observacionImportacion(object $cabecera): ?string
    {
        $obs = trim((string) ($cabecera->rendb_observacion ?? ''));

        return $obs !== '' ? $obs : 'Importada desde Anita nativo';
    }
}
