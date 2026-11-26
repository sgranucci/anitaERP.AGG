<?php

namespace App\Support\Contable\ConciliacionBancaria;

use App\Models\Contable\ConciliacionBancariaGrupo;
use App\Models\Contable\ConciliacionBancariaGrupoLinea;
use App\Models\Contable\ConciliacionBancariaPar;
use App\Models\Seguridad\Usuario;
use Carbon\CarbonInterface;

/**
 * Reporte de lo conciliado en una cuenta de caja, filtrado por la fecha en que se concilió
 * (no por la fecha de los movimientos). Una fila por cruce: par 1:1 o grupo N:M.
 */
class ConciliacionBancariaConciliadoReporteSupport
{
    /**
     * @return array{
     *   filas: list<array<string, mixed>>,
     *   totales: array{n: int, importe: float, diferencia: float, n_cierre: int, importe_cierre: float, n_banco: int, importe_banco: float}
     * }
     */
    public static function armar(int $empresaId, int $cuentacajaId, ?CarbonInterface $desde = null, ?CarbonInterface $hasta = null): array
    {
        // Sin fecha = sin límite de ese lado (ambas vacías = todo lo conciliado de la cuenta).
        $inicio = $desde?->copy()->startOfDay();
        $fin = $hasta?->copy()->endOfDay();
        $acotar = static function ($q) use ($inicio, $fin) {
            if ($inicio !== null) {
                $q->where('conciliado_at', '>=', $inicio);
            }
            if ($fin !== null) {
                $q->where('conciliado_at', '<=', $fin);
            }

            return $q;
        };

        $pares = $acotar(ConciliacionBancariaPar::query()
            ->where('empresa_id', $empresaId)
            ->where('cuentacaja_id', $cuentacajaId))
            ->orderBy('conciliado_at')
            ->orderBy('id')
            ->get();

        $grupos = $acotar(ConciliacionBancariaGrupo::query()
            ->with('lineas')
            ->where('empresa_id', $empresaId)
            ->where('cuentacaja_id', $cuentacajaId))
            ->orderBy('conciliado_at')
            ->orderBy('id')
            ->get();

        $usuarioIds = $pares->pluck('conciliado_por_usuario_id')
            ->merge($grupos->pluck('conciliado_por_usuario_id'))
            ->filter()
            ->unique()
            ->values()
            ->all();
        // Auditoría histórica: se muestra quien concilió aunque hoy esté suspendido.
        $usuarios = $usuarioIds === []
            ? collect()
            : Usuario::query()->whereIn('id', $usuarioIds)->pluck('nombre', 'id');

        $filas = [];
        foreach ($pares as $par) {
            $filas[] = self::filaPar($par, (string) ($usuarios[$par->conciliado_por_usuario_id] ?? ''));
        }
        foreach ($grupos as $grupo) {
            $filas[] = self::filaGrupo($grupo, (string) ($usuarios[$grupo->conciliado_por_usuario_id] ?? ''));
        }

        usort($filas, static fn (array $a, array $b): int => [$a['_orden'], $a['_id']] <=> [$b['_orden'], $b['_id']]);

        $importe = 0.0;
        $diferencia = 0.0;
        $nCierre = 0;
        $importeCierre = 0.0;
        foreach ($filas as &$fila) {
            unset($fila['_orden'], $fila['_id']);
            $importe += (float) $fila['importe'];
            $diferencia += (float) $fila['diferencia'];
            if (! empty($fila['sintetico'])) {
                $nCierre++;
                $importeCierre += (float) $fila['importe'];
            }
        }
        unset($fila);

        return [
            'filas' => $filas,
            'totales' => [
                'n' => count($filas),
                'importe' => round($importe, 2),
                'diferencia' => round($diferencia, 2),
                'n_cierre' => $nCierre,
                'importe_cierre' => round($importeCierre, 2),
                'n_banco' => count($filas) - $nCierre,
                'importe_banco' => round($importe - $importeCierre, 2),
            ],
        ];
    }

    /**
     * Par sintético de la alineación contra el Excel de cierre de Contaduría: no corresponde a un movimiento real del banco.
     */
    private static function esParCierreExcel(ConciliacionBancariaPar $par): bool
    {
        return (string) $par->hash_banco === hash('sha256', 'cierre_excel|banco|'.$par->hash_contable);
    }

    /**
     * @return array<string, mixed>
     */
    private static function filaPar(ConciliacionBancariaPar $par, string $usuario): array
    {
        $cont = is_array($par->contable_json) ? $par->contable_json : [];
        $banco = is_array($par->banco_json) ? $par->banco_json : [];
        $impC = ConciliacionBancariaHashSupport::importeFirmadoContable($cont);
        $impB = ConciliacionBancariaHashSupport::importeFirmadoBanco($banco);
        $sintetico = self::esParCierreExcel($par);

        return [
            '_orden' => $par->conciliado_at?->timestamp ?? 0,
            '_id' => 'p'.str_pad((string) $par->id, 12, '0', STR_PAD_LEFT),
            'conciliado' => $par->conciliado_at?->format('d/m/Y H:i') ?? '',
            'usuario' => $usuario,
            'origen' => $sintetico ? 'cierre Excel' : (string) $par->origen,
            'sintetico' => $sintetico,
            'cruce' => '1:1',
            'fecha_contable' => $par->fecha_contable?->format('d/m/Y') ?? '',
            'comprobante_contable' => self::textoContable($cont),
            'detalle_contable' => (string) ($cont['descripcion'] ?? ''),
            'fecha_banco' => $par->fecha_banco?->format('d/m/Y') ?? '',
            'concepto_banco' => self::textoConceptoBanco($banco),
            'comprobante_banco' => self::textoVoucher($banco),
            'importe' => round((float) $par->importe, 2),
            'diferencia' => round($impC - $impB, 2),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function filaGrupo(ConciliacionBancariaGrupo $grupo, string $usuario): array
    {
        $contables = $grupo->lineas->where('lado', ConciliacionBancariaGrupoLinea::LADO_CONTABLE)->values();
        $bancos = $grupo->lineas->where('lado', ConciliacionBancariaGrupoLinea::LADO_BANCO)->values();

        $json = static fn ($l): array => is_array($l->movimiento_json) ? $l->movimiento_json : [];

        return [
            '_orden' => $grupo->conciliado_at?->timestamp ?? 0,
            '_id' => 'g'.str_pad((string) $grupo->id, 12, '0', STR_PAD_LEFT),
            'conciliado' => $grupo->conciliado_at?->format('d/m/Y H:i') ?? '',
            'usuario' => $usuario,
            'origen' => (string) $grupo->modo,
            'sintetico' => false,
            'cruce' => $contables->count().':'.$bancos->count(),
            'fecha_contable' => $contables->map(fn ($l) => $l->fecha?->format('d/m/Y'))->filter()->unique()->implode(' / '),
            'comprobante_contable' => $contables->map(fn ($l) => self::textoContable($json($l)))->filter()->implode(' / '),
            'detalle_contable' => $contables->map(fn ($l) => (string) ($json($l)['descripcion'] ?? ''))->filter()->implode(' / '),
            'fecha_banco' => $bancos->map(fn ($l) => $l->fecha?->format('d/m/Y'))->filter()->unique()->implode(' / '),
            'concepto_banco' => $bancos->map(fn ($l) => self::textoConceptoBanco($json($l)))->filter()->implode(' / '),
            'comprobante_banco' => $bancos->map(fn ($l) => self::textoVoucher($json($l)))->filter()->implode(' / '),
            'importe' => round((float) $grupo->importe_contable, 2),
            'diferencia' => round((float) $grupo->importe_contable - (float) $grupo->importe_banco, 2),
        ];
    }

    /**
     * @param  array<string, mixed>  $mov
     */
    private static function textoContable(array $mov): string
    {
        $partes = array_filter([
            trim((string) ($mov['tipo_comp'] ?? '')),
            trim((string) ($mov['nro_asiento_fmt'] ?? '')),
        ], static fn (string $p): bool => $p !== '');
        $texto = implode(' ', $partes);
        $nro = trim((string) ($mov['nro'] ?? ''));
        if ($nro !== '') {
            $texto .= ($texto !== '' ? ' · Nro ' : 'Nro ').$nro;
        }

        return $texto;
    }

    /**
     * @param  array<string, mixed>  $mov
     */
    private static function textoConceptoBanco(array $mov): string
    {
        $ib = trim((string) ($mov['code_description_ib'] ?? ''));
        $banco = trim((string) ($mov['code_description_bank'] ?? ''));

        return $ib !== '' ? $ib : $banco;
    }

    /**
     * @param  array<string, mixed>  $mov
     */
    private static function textoVoucher(array $mov): string
    {
        $v = trim((string) ($mov['voucher_number'] ?? ''));

        return ($v === '' || preg_match('/^0+$/', $v) === 1) ? '' : $v;
    }
}
