<?php

namespace App\Support\Caja;

use App\Models\Admin\Permiso;
use App\Models\Caja\TesoreriaCrownBoveda;
use App\Models\Caja\TesoreriaCrownBovedaLinea;
use App\Models\Caja\TesoreriaCrownBovedaTipo;
use App\Models\Caja\TesoreriaCrownComprobante;
use App\Models\Caja\TesoreriaCrownComprobanteLinea;
use App\Models\Caja\TesoreriaCrownDeposito;
use App\Models\Caja\TesoreriaCrownJornada;
use App\Models\Caja\TesoreriaCrownValor;
use App\Models\Seguridad\Usuario;
use App\Support\Database\EloquentAuditDeleteSupport;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class TesoreriaCrownBovedaSupport
{
    /** @var list<string> */
    public const LUGARES = ['BOVEDA', 'PREDEPOSITO', 'CAJA_CHICA'];

    public static function efecto(string $signo): int
    {
        return match ($signo) {
            '0' => 1,
            '1' => -1,
            default => 0,
        };
    }

    /**
     * Efecto del movimiento.
     * 1) mueve_billetaje=false → 0 (p. ej. PROP/Híbrida)
     * 2) si $signoSubtipo está definido → ese signo
     * 3) si no → signo del tipo
     */
    public static function efectoConSubtipo(
        string $signo,
        mixed $mueveBilletaje = null,
        mixed $signoSubtipo = null
    ): int {
        if ($mueveBilletaje !== null && ! (bool) (int) $mueveBilletaje) {
            return 0;
        }
        $efectivo = self::signoEfectivo($signo, $signoSubtipo);

        return self::efecto($efectivo);
    }

    public static function signoEfectivo(string $signoTipo, mixed $signoSubtipo = null): string
    {
        if ($signoSubtipo !== null && $signoSubtipo !== '') {
            return substr(trim((string) $signoSubtipo), 0, 1);
        }

        return substr(trim($signoTipo), 0, 1);
    }

    public static function efectoTexto(string $signo): string
    {
        return match ($signo) {
            '0' => 'Entra',
            '1' => 'Sale',
            default => 'No mueve saldo',
        };
    }

    public static function efectoTextoConSubtipo(
        string $signo,
        mixed $mueveBilletaje = null,
        mixed $signoSubtipo = null
    ): string {
        if ($mueveBilletaje !== null && ! (bool) (int) $mueveBilletaje) {
            return 'Informativo (sin billetaje)';
        }

        return self::efectoTexto(self::signoEfectivo($signo, $signoSubtipo));
    }

    public static function estado(string $codigo): string
    {
        return match ($codigo) {
            TesoreriaCrownBoveda::BORRADOR => 'Borrador',
            TesoreriaCrownBoveda::CONFIRMADO => 'Confirmado',
            TesoreriaCrownBoveda::ANULADO => 'Anulado',
            default => $codigo,
        };
    }

    public static function puedeConfirmar(Usuario $usuario): bool
    {
        $roles = $usuario->roles()->get(['rol.id', 'rol.nombre']);
        if ($roles->contains(fn ($rol) => (string) $rol->nombre === 'administrador')) {
            return true;
        }

        return Permiso::query()
            ->where('slug', 'editar-tesoreria-crown-boveda')
            ->whereHas('roles', fn ($q) => $q->whereIn('rol.id', $roles->pluck('id')))
            ->exists();
    }

    /**
     * @return Collection<int, TesoreriaCrownDeposito>
     */
    public static function lugares(int $salaId): Collection
    {
        return TesoreriaCrownDeposito::query()
            ->with('tipoDeposito')
            ->where('sala_id', $salaId)
            ->where('activo', true)
            ->whereHas('tipoDeposito', fn ($q) => $q->whereIn('codigo', self::LUGARES))
            ->orderBy('orden')
            ->orderBy('codigo')
            ->get();
    }

    /**
     * @return Collection<int, TesoreriaCrownBovedaTipo>
     */
    public static function tiposActivos(int $salaId, null|string|\Carbon\CarbonInterface $fecha = null): Collection
    {
        $fechaYmd = TesoreriaCrownBovedaVigenciaSupport::normalizarFecha($fecha);
        $q = TesoreriaCrownBovedaTipo::query()
            ->where('sala_id', $salaId)
            ->where('activo', true);
        TesoreriaCrownBovedaVigenciaSupport::aplicarVigenteEn($q, $fechaYmd);

        return $q->orderBy('codigo')->get();
    }

    /**
     * Tipos que a-efeboveda deja cargar a mano. La apertura entra aunque esté inactiva.
     * La habilitación y la rendición las graba el conforme del cajero.
     * Filtra por vigencia a la fecha de la jornada.
     *
     * @return Collection<int, TesoreriaCrownBovedaTipo>
     */
    public static function tiposParaCarga(int $salaId, null|string|\Carbon\CarbonInterface $fecha = null): Collection
    {
        $fechaYmd = TesoreriaCrownBovedaVigenciaSupport::normalizarFecha($fecha);
        $q = TesoreriaCrownBovedaTipo::query()
            ->where('sala_id', $salaId)
            ->whereNotIn('abreviatura', ['HAB', 'REN'])
            ->where(function ($q) {
                $q->where('activo', true)->orWhere('abreviatura', 'APER');
            });
        TesoreriaCrownBovedaVigenciaSupport::aplicarVigenteEn($q, $fechaYmd);

        return $q->orderBy('codigo')->get();
    }

    /**
     * @return list<string>
     */
    public static function monedasDelTipo(TesoreriaCrownBovedaTipo $tipo): array
    {
        if ((string) $tipo->operacion !== '2') {
            return ['ARS', 'USD', 'EUR'];
        }
        $abreviatura = strtoupper(trim((string) $tipo->abreviatura));
        if (in_array($abreviatura, ['DEEU', 'INEU'], true)) {
            return ['EUR'];
        }
        if (in_array($abreviatura, ['DEUS', 'INUS', 'DTES'], true)) {
            return ['USD'];
        }

        return ['USD', 'EUR'];
    }

    public static function referenciaDelTipo(TesoreriaCrownBovedaTipo $tipo): ?string
    {
        return match ((string) $tipo->operacion) {
            '6' => 'cliente',
            '3', '4' => 'legajo',
            default => null,
        };
    }

    /**
     * Billetes de pesos, dólares y euros, como BILL_carga_denominacion de a-efeboveda.
     *
     * @return Collection<int, TesoreriaCrownValor>
     */
    public static function filasEfectivo(int $salaId): Collection
    {
        $orden = ['ARS' => 0, 'USD' => 1, 'EUR' => 2];

        return TesoreriaCrownValor::query()
            ->with('tipoValor')
            ->where('sala_id', $salaId)
            ->where('activo', true)
            ->whereHas('tipoValor', fn ($q) => $q->whereIn('codigo', ['ARS', 'USD', 'EUR']))
            ->get()
            ->sort(function (TesoreriaCrownValor $a, TesoreriaCrownValor $b) use ($orden): int {
                $moneda = ($orden[$a->tipoValor->codigo ?? ''] ?? 9) <=> ($orden[$b->tipoValor->codigo ?? ''] ?? 9);

                return $moneda !== 0 ? $moneda : ((float) $b->denominacion <=> (float) $a->denominacion);
            })
            ->values();
    }

    /**
     * Saldo en cantidad por valor, de los movimientos confirmados hasta esa fecha de jornada.
     *
     * @return array<int, float>
     */
    public static function saldoCantidades(int $depositoId, string $hastaYmd): array
    {
        $filas = DB::table('tesoreria_crown_boveda_linea as l')
            ->join('tesoreria_crown_boveda as b', 'b.id', '=', 'l.boveda_id')
            ->join('tesoreria_crown_boveda_tipo as t', 't.id', '=', 'b.tipo_id')
            ->leftJoin('tesoreria_crown_boveda_subtipo as st', 'st.id', '=', 'b.subtipo_id')
            ->join('tesoreria_crown_jornada as j', 'j.id', '=', 'b.jornada_id')
            ->where('b.deposito_id', $depositoId)
            ->where('b.estado', TesoreriaCrownBoveda::CONFIRMADO)
            ->whereDate('j.fecha', '<=', $hastaYmd)
            ->get(['l.valor_id', 'l.cantidad', 't.signo', 'st.mueve_billetaje', 'st.signo as subtipo_signo']);

        $saldos = [];
        foreach ($filas as $fila) {
            $efecto = self::efectoConSubtipo(
                (string) $fila->signo,
                $fila->mueve_billetaje ?? null,
                $fila->subtipo_signo ?? null
            );
            if ($efecto === 0) {
                continue;
            }
            $valorId = (int) $fila->valor_id;
            $saldos[$valorId] = ($saldos[$valorId] ?? 0.0) + ((float) $fila->cantidad * $efecto);
        }

        return $saldos;
    }

    /**
     * Movimientos confirmados que arman el stock de un billete hasta la fecha.
     * La cantidad ya viene con el signo del tipo: entra suma, sale resta.
     *
     * @return array{saldo: float, filas: list<array<string, mixed>>}
     */
    public static function composicionBillete(int $depositoId, string $hastaYmd, int $valorId): array
    {
        $filas = DB::table('tesoreria_crown_boveda_linea as l')
            ->join('tesoreria_crown_boveda as b', 'b.id', '=', 'l.boveda_id')
            ->join('tesoreria_crown_boveda_tipo as t', 't.id', '=', 'b.tipo_id')
            ->leftJoin('tesoreria_crown_boveda_subtipo as st', 'st.id', '=', 'b.subtipo_id')
            ->join('tesoreria_crown_jornada as j', 'j.id', '=', 'b.jornada_id')
            ->where('b.deposito_id', $depositoId)
            ->where('l.valor_id', $valorId)
            ->where('b.estado', TesoreriaCrownBoveda::CONFIRMADO)
            ->whereDate('j.fecha', '<=', $hastaYmd)
            ->orderBy('j.fecha')
            ->orderBy('b.numero')
            ->orderBy('b.id')
            ->get([
                'b.id',
                'b.numero',
                'b.comentario',
                'j.fecha',
                't.abreviatura',
                't.nombre as tipo_nombre',
                't.signo',
                'st.mueve_billetaje',
                'st.signo as subtipo_signo',
                'l.cantidad',
            ]);

        $saldo = 0.0;
        $out = [];
        foreach ($filas as $fila) {
            $efecto = self::efectoConSubtipo(
                (string) $fila->signo,
                $fila->mueve_billetaje ?? null,
                $fila->subtipo_signo ?? null
            );
            if ($efecto === 0 || abs((float) $fila->cantidad) < 0.0001) {
                continue;
            }
            $delta = round((float) $fila->cantidad * $efecto, 2);
            $saldo = round($saldo + $delta, 2);
            $fecha = $fila->fecha instanceof \DateTimeInterface
                ? $fila->fecha->format('d/m/Y')
                : \Carbon\Carbon::parse((string) $fila->fecha)->format('d/m/Y');
            $out[] = [
                'id' => (int) $fila->id,
                'fecha' => $fecha,
                'numero' => TesoreriaCrownNumeradorSupport::visible((int) $fila->numero),
                'tipo' => trim((string) $fila->abreviatura.' '.(string) $fila->tipo_nombre),
                'efecto' => self::efectoTextoConSubtipo(
                    (string) $fila->signo,
                    $fila->mueve_billetaje ?? null,
                    $fila->subtipo_signo ?? null
                ),
                'cantidad' => $delta,
                'saldo' => $saldo,
                'comentario' => trim((string) $fila->comentario),
            ];
        }

        return ['saldo' => $saldo, 'filas' => $out];
    }

    /**
     * @param  array<int, float>  $cantidades
     * @return list<array{titulo: string, filas: list<array{valor: TesoreriaCrownValor, cantidad: float, monto: float}>, monto: float}>
     */
    public static function saldoAgrupado(int $salaId, array $cantidades): array
    {
        if ($cantidades === []) {
            return [];
        }
        $valores = TesoreriaCrownValor::query()
            ->with('tipoValor')
            ->where('sala_id', $salaId)
            ->whereIn('id', array_keys($cantidades))
            ->get()
            ->keyBy('id');
        $grupos = [];
        foreach ($cantidades as $valorId => $cantidad) {
            if (abs($cantidad) < 0.0001) {
                continue;
            }
            $valor = $valores->get((int) $valorId);
            if ($valor === null) {
                continue;
            }
            $titulo = (string) ($valor->tipoValor->nombre ?? 'Valor');
            $monto = $cantidad * (float) $valor->denominacion;
            $grupos[$titulo]['titulo'] = $titulo;
            $grupos[$titulo]['filas'][] = [
                'valor' => $valor,
                'cantidad' => $cantidad,
                'monto' => $monto,
            ];
            $grupos[$titulo]['monto'] = ($grupos[$titulo]['monto'] ?? 0.0) + $monto;
        }

        $ordenMoneda = ['ARS' => 0, 'USD' => 1, 'EUR' => 2, 'Pesos' => 0, 'Dólares' => 1, 'Euros' => 2];
        foreach ($grupos as &$grupo) {
            usort($grupo['filas'], static function (array $a, array $b): int {
                return (float) $b['valor']->denominacion <=> (float) $a['valor']->denominacion;
            });
            $grupo['monto'] = round((float) ($grupo['monto'] ?? 0), 2);
        }
        unset($grupo);

        uasort($grupos, static function (array $a, array $b) use ($ordenMoneda): int {
            $ca = $ordenMoneda[$a['titulo'] ?? ''] ?? 9;
            $cb = $ordenMoneda[$b['titulo'] ?? ''] ?? 9;
            if ($ca !== $cb) {
                return $ca <=> $cb;
            }

            return strcmp((string) ($a['titulo'] ?? ''), (string) ($b['titulo'] ?? ''));
        });

        return array_values($grupos);
    }

    /**
     * Importes del movimiento en la moneda de cada denominación.
     * Entra suma, sale resta. Un tipo que no mueve saldo queda en positivo, solo asentado.
     *
     * @return list<array{codigo: string, monto: float, mueve: bool}>
     */
    public static function importesMovimiento(TesoreriaCrownBoveda $movimiento): array
    {
        if ($movimiento->relationLoaded('subtipo') === false && $movimiento->subtipo_id) {
            $movimiento->load('subtipo');
        }
        $mueveBilletaje = $movimiento->subtipo?->mueve_billetaje;
        $efecto = self::efectoConSubtipo(
            (string) ($movimiento->tipo->signo ?? '2'),
            $mueveBilletaje,
            $movimiento->subtipo?->signo
        );
        $porCodigo = [];
        foreach ($movimiento->lineas as $linea) {
            $valor = $linea->valor;
            if ($valor === null) {
                continue;
            }
            $codigo = trim((string) ($valor->tipoValor->codigo ?? ''));
            if ($codigo === '') {
                $codigo = 'VAL';
            }
            $monto = (float) $linea->cantidad * (float) $valor->denominacion;
            if ($efecto !== 0) {
                $monto *= $efecto;
            }
            $porCodigo[$codigo] = ($porCodigo[$codigo] ?? 0.0) + $monto;
        }
        $filas = [];
        foreach ($porCodigo as $codigo => $monto) {
            if (abs($monto) < 0.001) {
                continue;
            }
            $filas[] = ['codigo' => $codigo, 'monto' => $monto, 'mueve' => $efecto !== 0];
        }

        return $filas;
    }

    /**
     * @return array{lugares: Collection<int, TesoreriaCrownDeposito>, saldos: array<int, list<array{titulo: string, monto: float}>>, movimientos: Collection<int, TesoreriaCrownBoveda>}
     */
    public static function resumenJornada(TesoreriaCrownJornada $jornada): array
    {
        $lugares = self::lugares((int) $jornada->sala_id);
        $fecha = $jornada->fecha->format('Y-m-d');
        $saldos = [];
        foreach ($lugares as $lugar) {
            if ((string) ($lugar->tipoDeposito->codigo ?? '') !== 'BOVEDA') {
                continue;
            }
            $grupos = self::saldoAgrupado((int) $jornada->sala_id, self::saldoCantidades((int) $lugar->id, $fecha));
            $saldos[(int) $lugar->id] = array_map(
                fn (array $grupo) => ['titulo' => $grupo['titulo'], 'monto' => $grupo['monto']],
                $grupos
            );
        }

        return [
            'lugares' => $lugares,
            'saldos' => $saldos,
            'movimientos' => TesoreriaCrownBoveda::query()
                ->with(['tipo', 'deposito'])
                ->where('jornada_id', $jornada->id)
                ->where('estado', '<>', TesoreriaCrownBoveda::ANULADO)
                ->orderBy('numero')
                ->get(),
        ];
    }

    /**
     * Origen C de Anita (a-transfefe.c graba rendmbill con RENMB_CAJERO; l-estboveda.c):
     * la habilitación saca billetes de la bóveda y la rendición los devuelve.
     * Las fichas no están en rendmbill.
     *
     * Crown: el movimiento se refleja en bóveda desde borrador (ya es efectivo preparado),
     * no recién al quedar conforme. Si no hay líneas ARS/USD/EUR, se anula el asiento.
     */
    public static function asentarCajero(TesoreriaCrownComprobante $comprobante): void
    {
        if (! in_array($comprobante->estado, [
            TesoreriaCrownComprobanteSupport::BORRADOR,
            TesoreriaCrownComprobanteSupport::PENDIENTE,
            TesoreriaCrownComprobanteSupport::CONFORME,
        ], true)) {
            self::anularAsientoCajero($comprobante);

            return;
        }
        if (! in_array($comprobante->tipo, [
            TesoreriaCrownComprobanteSupport::HABILITACION,
            TesoreriaCrownComprobanteSupport::RENDICION,
        ], true)) {
            return;
        }

        DB::transaction(function () use ($comprobante) {
            $abreviatura = $comprobante->tipo === TesoreriaCrownComprobanteSupport::HABILITACION ? 'HAB' : 'REN';
            $tipo = TesoreriaCrownBovedaTipo::query()
                ->where('sala_id', $comprobante->sala_id)
                ->where('abreviatura', $abreviatura)
                ->first();
            $deposito = self::lugares((int) $comprobante->sala_id)
                ->first(fn (TesoreriaCrownDeposito $lugar) => (string) ($lugar->tipoDeposito->codigo ?? '') === 'BOVEDA');
            if ($tipo === null || $deposito === null) {
                return;
            }

            $lineas = TesoreriaCrownComprobanteLinea::query()
                ->with('valor.tipoValor')
                ->where('comprobante_id', $comprobante->id)
                ->get()
                ->filter(function (TesoreriaCrownComprobanteLinea $linea): bool {
                    $codigo = strtoupper(trim((string) ($linea->valor->tipoValor->codigo ?? '')));
                    if (! in_array($codigo, ['ARS', 'USD', 'EUR'], true)) {
                        return false;
                    }

                    return abs((float) $linea->cantidad) >= 0.0001;
                });

            $movimiento = TesoreriaCrownBoveda::query()
                ->where('comprobante_id', $comprobante->id)
                ->where('estado', '<>', TesoreriaCrownBoveda::ANULADO)
                ->lockForUpdate()
                ->first();

            if ($lineas->isEmpty()) {
                if ($movimiento !== null) {
                    $movimiento->estado = TesoreriaCrownBoveda::ANULADO;
                    $movimiento->save();
                    self::invalidarAnexo8((int) $comprobante->jornada_id);
                }

                return;
            }

            $comprobante->loadMissing('cajero', 'asignacion.cajero');
            $cajero = trim((string) ($comprobante->cajeroOperativo()->nombre ?? ''));
            $etiqueta = $comprobante->tipo === TesoreriaCrownComprobanteSupport::HABILITACION
                ? 'Habilitación'
                : 'Rendición';
            if ($comprobante->revierte_id !== null) {
                $etiqueta = 'Reversa de '.mb_strtolower($etiqueta);
            }
            $comentario = trim($etiqueta.' '.TesoreriaCrownNumeradorSupport::visible((int) $comprobante->numero).' '.$cajero);
            $usuarioId = $comprobante->recibe_usuario_id
                ?: $comprobante->entrega_usuario_id
                ?: (auth()->id() ?: null);

            if ($movimiento === null) {
                $movimiento = TesoreriaCrownBoveda::query()->create([
                    'sala_id' => $comprobante->sala_id,
                    'empresa_id' => $comprobante->empresa_id,
                    'jornada_id' => $comprobante->jornada_id,
                    'deposito_id' => $deposito->id,
                    'tipo_id' => $tipo->id,
                    'numero' => TesoreriaCrownNumeradorSupport::siguiente((int) $comprobante->sala_id, 'V'),
                    'estado' => TesoreriaCrownBoveda::CONFIRMADO,
                    'comentario' => mb_substr($comentario, 0, 80),
                    'comprobante_id' => $comprobante->id,
                    'usuario_id' => $usuarioId,
                    'confirmado_at' => now(),
                ]);
            } else {
                $movimiento->comentario = mb_substr($comentario, 0, 80);
                $movimiento->usuario_id = $usuarioId ?: $movimiento->usuario_id;
                $movimiento->estado = TesoreriaCrownBoveda::CONFIRMADO;
                if ($movimiento->confirmado_at === null) {
                    $movimiento->confirmado_at = now();
                }
                $movimiento->save();
                EloquentAuditDeleteSupport::each(
                    TesoreriaCrownBovedaLinea::query()->where('boveda_id', $movimiento->id)
                );
            }

            foreach ($lineas as $linea) {
                TesoreriaCrownBovedaLinea::query()->create([
                    'boveda_id' => $movimiento->id,
                    'valor_id' => $linea->valor_id,
                    'cantidad' => $linea->cantidad,
                ]);
            }
            self::invalidarAnexo8((int) $comprobante->jornada_id);
        });
    }

    /** Anula el movimiento de bóveda ligado al comprobante (no conforme / anulado). */
    public static function anularAsientoCajero(TesoreriaCrownComprobante $comprobante): void
    {
        DB::transaction(function () use ($comprobante) {
            $movimiento = TesoreriaCrownBoveda::query()
                ->where('comprobante_id', $comprobante->id)
                ->where('estado', '<>', TesoreriaCrownBoveda::ANULADO)
                ->lockForUpdate()
                ->first();
            if ($movimiento === null) {
                return;
            }
            $movimiento->estado = TesoreriaCrownBoveda::ANULADO;
            $movimiento->save();
            self::invalidarAnexo8((int) $comprobante->jornada_id);
        });
    }

    public static function comprobanteYaAsentado(int $comprobanteId): bool
    {
        return TesoreriaCrownBoveda::query()
            ->where('comprobante_id', $comprobanteId)
            ->where('estado', '<>', TesoreriaCrownBoveda::ANULADO)
            ->exists();
    }

    private static function invalidarAnexo8(int $jornadaId): void
    {
        $jornada = TesoreriaCrownJornada::query()->find($jornadaId);
        if ($jornada) {
            TesoreriaCrownAnexo8Calculo::invalidarCache($jornada);
        }
    }
}
