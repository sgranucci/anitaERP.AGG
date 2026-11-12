<?php

namespace App\Support\Caja;

use App\Models\Caja\TesoreriaCrownBoveda;
use App\Models\Caja\TesoreriaCrownBovedaLinea;
use App\Models\Caja\TesoreriaCrownBovedaSubtipo;
use App\Models\Caja\TesoreriaCrownBovedaTipo;
use App\Models\Caja\TesoreriaCrownDeposito;
use App\Models\Caja\TesoreriaCrownMesa;
use App\Models\Caja\TesoreriaCrownTipoValor;
use App\Models\Caja\TesoreriaCrownValor;
use App\Models\Seguridad\Usuario;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Estado final de mesa (F) con propina → movimiento bóveda PROPINA / subtipo Mesas.
 * Preferí el billetaje cargado en la mesa; si no hay, descompone en ARS (greedy, imports Anita).
 */
final class TesoreriaCrownMesaPropinaBovedaSupport
{
    /**
     * @return string|null error
     */
    public static function asentarDesdeEstadoFinal(TesoreriaCrownMesa $mesa, ?Usuario $usuario = null): ?string
    {
        if (! Schema::hasColumn('tesoreria_crown_mesa', 'boveda_id')) {
            return null;
        }
        $mesa->loadMissing(['jornada', 'deposito', 'lineas']);
        if ($mesa->tipo !== 'F' || $mesa->estado !== TesoreriaCrownMesa::CONFIRMADO) {
            return null;
        }
        if ((int) ($mesa->boveda_id ?? 0) > 0) {
            return null;
        }
        $propina = round((float) $mesa->propina, 2);
        if ($propina < 0.001) {
            return null;
        }
        if ($mesa->revierte_id !== null) {
            return null;
        }

        $deposito = TesoreriaCrownBovedaSupport::lugares((int) $mesa->sala_id)
            ->first(fn (TesoreriaCrownDeposito $lugar) => (string) ($lugar->tipoDeposito->codigo ?? '') === 'BOVEDA');
        if ($deposito === null) {
            return 'No hay depósito Bóveda en esta sala para asentar la propina.';
        }
        $tipo = TesoreriaCrownBovedaTipo::query()
            ->where('sala_id', $mesa->sala_id)
            ->where('abreviatura', 'PROP')
            ->where('activo', true)
            ->first();
        if ($tipo === null) {
            return 'Falta el tipo PROP (PROPINA) para asentar la propina en bóveda.';
        }
        $subtipo = TesoreriaCrownBovedaSubtipo::query()
            ->where('tipo_id', $tipo->id)
            ->where('codigo', 'MES')
            ->where('activo', true)
            ->first();
        if ($subtipo === null) {
            return 'Falta el subtipo Mesas (MES) del tipo PROPINA.';
        }

        $lineasArs = self::lineasDesdeBilletajeCargado($mesa, $propina);
        if ($lineasArs === null) {
            $lineasArs = self::descomponerEnArs((int) $mesa->sala_id, $propina);
        }
        if ($lineasArs === []) {
            return 'Cargá el billetaje de la propina (o un importe que se pueda descomponer en ARS de la sala).';
        }

        $mesaCodigo = (string) ($mesa->deposito->codigo ?? '');
        $comentario = mb_substr(
            'Propina mesa '.$mesaCodigo
            .' · EF '.TesoreriaCrownNumeradorSupport::visible((int) $mesa->numero)
            .' · $'.number_format($propina, 2, ',', '.'),
            0,
            80
        );

        DB::transaction(function () use ($mesa, $usuario, $deposito, $tipo, $subtipo, $lineasArs, $comentario, $propina) {
            $mov = TesoreriaCrownBoveda::query()->create([
                'sala_id' => $mesa->sala_id,
                'empresa_id' => $mesa->empresa_id,
                'jornada_id' => $mesa->jornada_id,
                'deposito_id' => $deposito->id,
                'tipo_id' => $tipo->id,
                'subtipo_id' => $subtipo->id,
                'numero' => TesoreriaCrownNumeradorSupport::siguiente((int) $mesa->sala_id, 'V'),
                'estado' => TesoreriaCrownBoveda::CONFIRMADO,
                'comentario' => $comentario,
                'hora' => now()->format('H:i:s'),
                'usuario_id' => $usuario?->id,
                'confirmado_at' => now(),
                'importe_pesos' => $propina,
            ]);
            foreach ($lineasArs as $valorId => $cantidad) {
                TesoreriaCrownBovedaLinea::query()->create([
                    'boveda_id' => $mov->id,
                    'valor_id' => $valorId,
                    'cantidad' => $cantidad,
                ]);
            }
            $mesa->boveda_id = $mov->id;
            $mesa->save();
        });

        if ($mesa->jornada) {
            TesoreriaCrownAnexo8Calculo::invalidarCache($mesa->jornada);
        }

        return null;
    }

    /**
     * Anula el PROP/MES generado desde el estado final (si existe y sigue confirmado).
     */
    public static function anularVinculada(TesoreriaCrownMesa $mesa): void
    {
        if (! Schema::hasColumn('tesoreria_crown_mesa', 'boveda_id')) {
            return;
        }
        $bovedaId = (int) ($mesa->boveda_id ?? 0);
        if ($bovedaId <= 0) {
            return;
        }
        $boveda = TesoreriaCrownBoveda::query()->find($bovedaId);
        if ($boveda === null || $boveda->estado !== TesoreriaCrownBoveda::CONFIRMADO) {
            return;
        }
        if ($boveda->reversa !== null || $boveda->revierte_id !== null) {
            return;
        }
        $boveda->estado = TesoreriaCrownBoveda::ANULADO;
        $boveda->save();
        if ($mesa->jornada) {
            TesoreriaCrownAnexo8Calculo::invalidarCache($mesa->jornada);
        }
    }

    /**
     * Compensa el PROP/MES del estado final original al revertir la mesa.
     *
     * @return string|null error
     */
    public static function compensarAlRevertir(
        TesoreriaCrownMesa $original,
        TesoreriaCrownMesa $reversa,
        Usuario $usuario,
        string $motivo
    ): ?string {
        if (! Schema::hasColumn('tesoreria_crown_mesa', 'boveda_id')) {
            return null;
        }
        $bovedaId = (int) ($original->boveda_id ?? 0);
        if ($bovedaId <= 0) {
            return null;
        }
        $boveda = TesoreriaCrownBoveda::query()->with('lineas')->find($bovedaId);
        if ($boveda === null || $boveda->estado !== TesoreriaCrownBoveda::CONFIRMADO) {
            return null;
        }
        if ($boveda->revierte_id !== null || $boveda->reversa !== null) {
            return null;
        }
        $lineas = $boveda->lineas->filter(fn ($l) => abs((float) $l->cantidad) >= 0.0001);
        if ($lineas->isEmpty()) {
            $boveda->estado = TesoreriaCrownBoveda::ANULADO;
            $boveda->save();

            return null;
        }

        DB::transaction(function () use ($boveda, $lineas, $usuario, $motivo, $reversa) {
            $ahora = now();
            $nuevo = TesoreriaCrownBoveda::query()->create([
                'sala_id' => $boveda->sala_id,
                'empresa_id' => $boveda->empresa_id,
                'jornada_id' => $boveda->jornada_id,
                'deposito_id' => $boveda->deposito_id,
                'tipo_id' => $boveda->tipo_id,
                'subtipo_id' => $boveda->subtipo_id,
                'numero' => TesoreriaCrownNumeradorSupport::siguiente((int) $boveda->sala_id, 'V'),
                'estado' => TesoreriaCrownBoveda::CONFIRMADO,
                'comentario' => 'Reversa de '.TesoreriaCrownNumeradorSupport::visible((int) $boveda->numero),
                'motivo' => $motivo,
                'hora' => $ahora->format('H:i:s'),
                'revierte_id' => $boveda->id,
                'usuario_id' => $usuario->id,
                'confirmado_at' => $ahora,
                'importe_pesos' => $boveda->importe_pesos !== null
                    ? round((float) $boveda->importe_pesos * -1, 2)
                    : null,
            ]);
            foreach ($lineas as $linea) {
                TesoreriaCrownBovedaLinea::query()->create([
                    'boveda_id' => $nuevo->id,
                    'valor_id' => $linea->valor_id,
                    'cantidad' => round((float) $linea->cantidad * -1, 2),
                ]);
            }
            $reversa->boveda_id = $nuevo->id;
            $reversa->save();
        });

        if ($original->jornada) {
            TesoreriaCrownAnexo8Calculo::invalidarCache($original->jornada);
        }

        return null;
    }

    /**
     * @return array<int, float>|null null = sin billetaje cargado; [] = inválido
     */
    private static function lineasDesdeBilletajeCargado(TesoreriaCrownMesa $mesa, float $propina): ?array
    {
        if (! Schema::hasColumn('tesoreria_crown_mesa', 'propina_billetes')) {
            return null;
        }
        $raw = $mesa->propina_billetes;
        if (! is_array($raw) || $raw === []) {
            return null;
        }
        [$lineas, $total] = TesoreriaCrownMesaSupport::normalizarPropinaBilletaje((int) $mesa->sala_id, $raw);
        if ($lineas === []) {
            return [];
        }
        if (abs($total - $propina) > 0.009) {
            return [];
        }
        $out = [];
        foreach ($lineas as $valorId => $cantidad) {
            $out[(int) $valorId] = (float) $cantidad;
        }

        return $out;
    }

    /**
     * @return array<int, float> valor_id => cantidad
     */
    private static function descomponerEnArs(int $salaId, float $importe): array
    {
        $tipoArsId = TesoreriaCrownTipoValor::query()
            ->where('sala_id', $salaId)
            ->where('codigo', 'ARS')
            ->value('id');
        if (! $tipoArsId) {
            return [];
        }
        $valores = TesoreriaCrownValor::query()
            ->where('sala_id', $salaId)
            ->where('tipo_valor_id', $tipoArsId)
            ->where('activo', true)
            ->where('denominacion', '>', 0)
            ->orderByDesc('denominacion')
            ->get(['id', 'denominacion']);
        if ($valores->isEmpty()) {
            return [];
        }

        $restante = (int) round($importe * 100);
        $lineas = [];
        foreach ($valores as $valor) {
            $centavos = (int) round((float) $valor->denominacion * 100);
            if ($centavos <= 0) {
                continue;
            }
            $cant = intdiv($restante, $centavos);
            if ($cant <= 0) {
                continue;
            }
            $lineas[(int) $valor->id] = (float) $cant;
            $restante -= $cant * $centavos;
            if ($restante <= 0) {
                break;
            }
        }

        return $restante === 0 ? $lineas : [];
    }
}
