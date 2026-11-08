<?php

namespace App\Support\Caja;

use App\Models\Caja\TesoreriaCrownBoveda;
use App\Models\Caja\TesoreriaCrownBovedaArqueo;
use App\Models\Caja\TesoreriaCrownBovedaArqueoLinea;
use App\Models\Caja\TesoreriaCrownDeposito;
use App\Models\Caja\TesoreriaCrownJornada;
use App\Models\Caja\TesoreriaCrownValor;
use App\Models\Seguridad\Usuario;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Arqueo de cambio de turno (relevamiento JL):
 * Inicial + Ingresos − Egresos = Actual, con doble firma de tesorería.
 */
final class TesoreriaCrownBovedaArqueoSupport
{
    public const NUMERADOR = 'A';

    /**
     * @return array{
     *   anterior: ?TesoreriaCrownBovedaArqueo,
     *   desde_at: Carbon,
     *   corte_at: Carbon,
     *   lineas: list<array{
     *     valor_id: int,
     *     valor: TesoreriaCrownValor,
     *     inicial: float,
     *     ingresos: float,
     *     egresos: float,
     *     actual: float,
     *     monto_inicial: float,
     *     monto_ingresos: float,
     *     monto_egresos: float,
     *     monto_actual: float
     *   }>,
     *   totales: array<string, array{inicial: float, ingresos: float, egresos: float, actual: float}>
     * }
     */
    public static function calcular(
        TesoreriaCrownDeposito $deposito,
        TesoreriaCrownJornada $jornada,
        ?Carbon $corteAt = null
    ): array {
        $corteAt = ($corteAt ?? now())->copy();
        $anterior = TesoreriaCrownBovedaArqueo::query()
            ->with('lineas')
            ->where('deposito_id', $deposito->id)
            ->where('estado', TesoreriaCrownBovedaArqueo::CONFORME)
            ->where('corte_at', '<', $corteAt)
            ->orderByDesc('corte_at')
            ->orderByDesc('id')
            ->first();

        $inicial = [];
        if ($anterior !== null) {
            foreach ($anterior->lineas as $linea) {
                $inicial[(int) $linea->valor_id] = (float) $linea->actual;
            }
            $desdeAt = Carbon::parse($anterior->corte_at);
        } else {
            $inicial = self::cantidadesAperturaJornada((int) $jornada->id, (int) $deposito->id);
            if ($inicial === []) {
                $diaAnterior = $jornada->fecha->copy()->subDay()->format('Y-m-d');
                $inicial = TesoreriaCrownBovedaSupport::saldoCantidades((int) $deposito->id, $diaAnterior);
            }
            $desdeAt = $jornada->fecha->copy()->startOfDay();
        }

        $periodo = self::movimientosPeriodo((int) $deposito->id, $desdeAt, $corteAt, $anterior === null);
        $ingresos = [];
        $egresos = [];
        foreach ($periodo as $fila) {
            $valorId = (int) $fila->valor_id;
            $cantidad = abs((float) $fila->cantidad);
            if ($cantidad < 0.0001) {
                continue;
            }
            $efecto = TesoreriaCrownBovedaSupport::efecto((string) $fila->signo);
            if ($efecto > 0) {
                $ingresos[$valorId] = ($ingresos[$valorId] ?? 0.0) + $cantidad;
            } elseif ($efecto < 0) {
                $egresos[$valorId] = ($egresos[$valorId] ?? 0.0) + $cantidad;
            }
        }

        $valorIds = array_unique(array_merge(
            array_keys($inicial),
            array_keys($ingresos),
            array_keys($egresos)
        ));
        sort($valorIds);

        $valores = TesoreriaCrownValor::query()
            ->with('tipoValor')
            ->whereIn('id', $valorIds ?: [0])
            ->get()
            ->keyBy('id');

        $ordenMoneda = ['ARS' => 0, 'USD' => 1, 'EUR' => 2];
        $lineas = [];
        $totales = [];
        foreach ($valorIds as $valorId) {
            $valor = $valores->get($valorId);
            if ($valor === null) {
                continue;
            }
            $ini = round((float) ($inicial[$valorId] ?? 0), 2);
            $ing = round((float) ($ingresos[$valorId] ?? 0), 2);
            $egr = round((float) ($egresos[$valorId] ?? 0), 2);
            $act = round($ini + $ing - $egr, 2);
            if (abs($ini) < 0.0001 && abs($ing) < 0.0001 && abs($egr) < 0.0001 && abs($act) < 0.0001) {
                continue;
            }
            $den = (float) $valor->denominacion;
            $codigo = (string) ($valor->tipoValor->codigo ?? 'VAL');
            $lineas[] = [
                'valor_id' => $valorId,
                'valor' => $valor,
                'inicial' => $ini,
                'ingresos' => $ing,
                'egresos' => $egr,
                'actual' => $act,
                'monto_inicial' => round($ini * $den, 2),
                'monto_ingresos' => round($ing * $den, 2),
                'monto_egresos' => round($egr * $den, 2),
                'monto_actual' => round($act * $den, 2),
            ];
            $totales[$codigo]['inicial'] = ($totales[$codigo]['inicial'] ?? 0.0) + ($ini * $den);
            $totales[$codigo]['ingresos'] = ($totales[$codigo]['ingresos'] ?? 0.0) + ($ing * $den);
            $totales[$codigo]['egresos'] = ($totales[$codigo]['egresos'] ?? 0.0) + ($egr * $den);
            $totales[$codigo]['actual'] = ($totales[$codigo]['actual'] ?? 0.0) + ($act * $den);
        }

        usort($lineas, function (array $a, array $b) use ($ordenMoneda): int {
            $ca = $ordenMoneda[$a['valor']->tipoValor->codigo ?? ''] ?? 9;
            $cb = $ordenMoneda[$b['valor']->tipoValor->codigo ?? ''] ?? 9;
            if ($ca !== $cb) {
                return $ca <=> $cb;
            }

            return (float) $b['valor']->denominacion <=> (float) $a['valor']->denominacion;
        });

        foreach ($totales as $codigo => $fila) {
            $totales[$codigo] = [
                'inicial' => round($fila['inicial'], 2),
                'ingresos' => round($fila['ingresos'], 2),
                'egresos' => round($fila['egresos'], 2),
                'actual' => round($fila['actual'], 2),
            ];
        }

        return [
            'anterior' => $anterior,
            'desde_at' => $desdeAt,
            'corte_at' => $corteAt,
            'lineas' => $lineas,
            'totales' => $totales,
        ];
    }

    /**
     * @return array<int, float>
     */
    public static function cantidadesAperturaJornada(int $jornadaId, int $depositoId): array
    {
        $apertura = TesoreriaCrownBoveda::query()
            ->with('lineas')
            ->where('jornada_id', $jornadaId)
            ->where('deposito_id', $depositoId)
            ->where('estado', TesoreriaCrownBoveda::CONFIRMADO)
            ->whereNull('revierte_id')
            ->whereHas('tipo', fn ($q) => $q->where('abreviatura', 'APER'))
            ->orderByDesc('numero')
            ->first();
        if ($apertura === null) {
            return [];
        }
        $out = [];
        foreach ($apertura->lineas as $linea) {
            $out[(int) $linea->valor_id] = ($out[(int) $linea->valor_id] ?? 0.0) + (float) $linea->cantidad;
        }

        return $out;
    }

    /**
     * Movimientos confirmados entre dos cortes (excluido el desde, incluido el hasta).
     * Si $excluirApertura, no cuenta el APER (ya está en el Inicial).
     *
     * @return \Illuminate\Support\Collection<int, object>
     */
    public static function movimientosPeriodo(
        int $depositoId,
        Carbon $desdeExclusive,
        Carbon $hastaInclusive,
        bool $excluirApertura = false
    ) {
        $q = DB::table('tesoreria_crown_boveda_linea as l')
            ->join('tesoreria_crown_boveda as b', 'b.id', '=', 'l.boveda_id')
            ->join('tesoreria_crown_boveda_tipo as t', 't.id', '=', 'b.tipo_id')
            ->where('b.deposito_id', $depositoId)
            ->where('b.estado', TesoreriaCrownBoveda::CONFIRMADO)
            ->where('b.confirmado_at', '>', $desdeExclusive)
            ->where('b.confirmado_at', '<=', $hastaInclusive);
        if ($excluirApertura) {
            $q->where('t.abreviatura', '<>', 'APER');
        }

        return $q->get(['l.valor_id', 'l.cantidad', 't.signo', 't.abreviatura', 'b.id', 'b.numero']);
    }

    public static function persistirDesdeCalculo(
        TesoreriaCrownJornada $jornada,
        TesoreriaCrownDeposito $deposito,
        array $calculo,
        ?Usuario $usuario,
        ?string $observacion = null
    ): TesoreriaCrownBovedaArqueo {
        return DB::transaction(function () use ($jornada, $deposito, $calculo, $usuario, $observacion) {
            $arqueo = TesoreriaCrownBovedaArqueo::query()->create([
                'sala_id' => $jornada->sala_id,
                'empresa_id' => $jornada->empresa_id,
                'jornada_id' => $jornada->id,
                'deposito_id' => $deposito->id,
                'numero' => TesoreriaCrownNumeradorSupport::siguiente((int) $jornada->sala_id, self::NUMERADOR),
                'estado' => TesoreriaCrownBovedaArqueo::BORRADOR,
                'corte_at' => $calculo['corte_at'],
                'arqueo_anterior_id' => $calculo['anterior']?->id,
                'observacion' => $observacion ? mb_substr(trim($observacion), 0, 120) : null,
                'usuario_id' => $usuario?->id,
            ]);
            foreach ($calculo['lineas'] as $linea) {
                TesoreriaCrownBovedaArqueoLinea::query()->create([
                    'arqueo_id' => $arqueo->id,
                    'valor_id' => $linea['valor_id'],
                    'inicial' => $linea['inicial'],
                    'ingresos' => $linea['ingresos'],
                    'egresos' => $linea['egresos'],
                    'actual' => $linea['actual'],
                ]);
            }

            return $arqueo->fresh(['lineas.valor.tipoValor', 'deposito', 'jornada', 'entregaUsuario', 'recibeUsuario']);
        });
    }

    public static function pasarAPendiente(TesoreriaCrownBovedaArqueo $arqueo): void
    {
        if (! $arqueo->estaBorrador()) {
            return;
        }
        $arqueo->estado = TesoreriaCrownBovedaArqueo::PENDIENTE;
        $arqueo->entrega_usuario_id = null;
        $arqueo->entrega_at = null;
        $arqueo->recibe_usuario_id = null;
        $arqueo->recibe_at = null;
        $arqueo->save();
    }

    public static function aplicarFirma(TesoreriaCrownBovedaArqueo $arqueo, string $lado, Usuario $usuario): void
    {
        if ($lado === 'entrega' && $arqueo->entrega_at === null) {
            $arqueo->entrega_usuario_id = $usuario->id;
            $arqueo->entrega_at = now();
        }
        if ($lado === 'recibe' && $arqueo->recibe_at === null) {
            $arqueo->recibe_usuario_id = $usuario->id;
            $arqueo->recibe_at = now();
        }
        if ($arqueo->firmasCompletas()) {
            $arqueo->estado = TesoreriaCrownBovedaArqueo::CONFORME;
        }
        $arqueo->save();
        if ($arqueo->estaConforme()) {
            TesoreriaCrownBovedaStockDiaSupport::sincronizarDesdeArqueo($arqueo->fresh(['lineas', 'jornada']));
        }
    }

    public static function puedeFirmar(Usuario $usuario): bool
    {
        if (TesoreriaCrownFirmaSupport::puedeFirmarTesoreria($usuario)) {
            return true;
        }
        $roles = $usuario->roles()->pluck('rol.id');
        if ($roles->isEmpty()) {
            return false;
        }

        return DB::table('permiso')
            ->join('permiso_rol', 'permiso_rol.permiso_id', '=', 'permiso.id')
            ->where('permiso.slug', 'firmar-tesoreria-crown-boveda-arqueo')
            ->whereIn('permiso_rol.rol_id', $roles)
            ->exists();
    }

    /**
     * @return list<array{titulo: string, filas: list<array>, totales: array{inicial: float, ingresos: float, egresos: float, actual: float}}>
     */
    public static function lineasAgrupadas(TesoreriaCrownBovedaArqueo $arqueo): array
    {
        $arqueo->loadMissing('lineas.valor.tipoValor');
        $grupos = [];
        foreach ($arqueo->lineas as $linea) {
            $valor = $linea->valor;
            if ($valor === null) {
                continue;
            }
            $titulo = (string) ($valor->tipoValor->nombre ?? 'Valor');
            $den = (float) $valor->denominacion;
            $grupos[$titulo]['titulo'] = $titulo;
            $grupos[$titulo]['filas'][] = [
                'valor' => $valor,
                'inicial' => (float) $linea->inicial,
                'ingresos' => (float) $linea->ingresos,
                'egresos' => (float) $linea->egresos,
                'actual' => (float) $linea->actual,
                'monto_inicial' => round((float) $linea->inicial * $den, 2),
                'monto_ingresos' => round((float) $linea->ingresos * $den, 2),
                'monto_egresos' => round((float) $linea->egresos * $den, 2),
                'monto_actual' => round((float) $linea->actual * $den, 2),
            ];
            $grupos[$titulo]['totales']['inicial'] = ($grupos[$titulo]['totales']['inicial'] ?? 0.0)
                + ((float) $linea->inicial * $den);
            $grupos[$titulo]['totales']['ingresos'] = ($grupos[$titulo]['totales']['ingresos'] ?? 0.0)
                + ((float) $linea->ingresos * $den);
            $grupos[$titulo]['totales']['egresos'] = ($grupos[$titulo]['totales']['egresos'] ?? 0.0)
                + ((float) $linea->egresos * $den);
            $grupos[$titulo]['totales']['actual'] = ($grupos[$titulo]['totales']['actual'] ?? 0.0)
                + ((float) $linea->actual * $den);
        }

        return array_values($grupos);
    }

    public static function etiquetaEstado(string $estado): string
    {
        return match ($estado) {
            TesoreriaCrownBovedaArqueo::BORRADOR => 'Borrador',
            TesoreriaCrownBovedaArqueo::PENDIENTE => 'Pendiente de firmas',
            TesoreriaCrownBovedaArqueo::CONFORME => 'Conforme',
            default => $estado,
        };
    }
}
