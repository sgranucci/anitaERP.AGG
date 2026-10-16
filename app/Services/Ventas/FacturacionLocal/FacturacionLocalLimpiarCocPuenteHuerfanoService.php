<?php

declare(strict_types=1);

namespace App\Services\Ventas\FacturacionLocal;

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Contable\AsientoEloquentDeleteSupport;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Borra asientos ERP importados del subdiario Anita (29–30/09) que son COC
 * MercadoLibre contra CUENTA PUENTE TFC sin FAC en la puente (sistema viejo).
 * Cada asiento tiene 2 líneas: Debe L-MERCADOLIBRE / Haber puente.
 */
final class FacturacionLocalLimpiarCocPuenteHuerfanoService
{
    private const DESDE = '2026-09-29';

    private const HASTA = '2026-09-30';

    private const CUENTA_PUENTE = '114110007';

    private const CUENTA_MERCADOLIBRE = '113101009';

    /**
     * @return array{
     *   desde: string,
     *   hasta: string,
     *   asientos: int,
     *   monto_puente: float,
     *   monto_mercadolibre: float,
     *   por_fecha: array<string,int>,
     *   muestras: list<array{id:int,numeroasiento:int|string|null,fecha:string,observacion:string,monto_puente:float}>
     * }
     */
    public function previsualizar(): array
    {
        $this->assertFerli();
        $filas = $this->candidatos();
        $porFecha = [];
        $montoPuente = 0.0;
        $montoMl = 0.0;
        $muestras = [];

        foreach ($filas as $fila) {
            $fecha = (string) $fila->fecha;
            $porFecha[$fecha] = ($porFecha[$fecha] ?? 0) + 1;
            $montoPuente += (float) $fila->monto_puente;
            $montoMl += (float) $fila->monto_ml;
            if (count($muestras) < 10) {
                $muestras[] = [
                    'id' => (int) $fila->id,
                    'numeroasiento' => $fila->numeroasiento,
                    'fecha' => $fecha,
                    'observacion' => (string) $fila->observacion,
                    'monto_puente' => round((float) $fila->monto_puente, 2),
                ];
            }
        }

        return [
            'desde' => self::DESDE,
            'hasta' => self::HASTA,
            'asientos' => $filas->count(),
            'monto_puente' => round($montoPuente, 2),
            'monto_mercadolibre' => round($montoMl, 2),
            'por_fecha' => $porFecha,
            'muestras' => $muestras,
            'ids' => $filas->pluck('id')->map(static fn ($id) => (int) $id)->all(),
        ];
    }

    /**
     * @return array{asientos:int,monto_puente:float}
     */
    public function aplicar(): array
    {
        $this->assertFerli();
        $previa = $this->previsualizar();
        if ($previa['asientos'] === 0) {
            return ['asientos' => 0, 'monto_puente' => 0.0];
        }

        // Revalidar criterios asiento por asiento antes de borrar.
        $borrados = 0;
        DB::transaction(function () use ($previa, &$borrados) {
            foreach ($previa['ids'] as $asientoId) {
                if (! $this->esCandidatoValido((int) $asientoId)) {
                    throw new RuntimeException(
                        'El asiento '.$asientoId.' ya no cumple el criterio de COC puente huérfano. Abortado.'
                    );
                }
                AsientoEloquentDeleteSupport::eliminarPorId((int) $asientoId);
                $borrados++;
            }
        });

        return [
            'asientos' => $borrados,
            'monto_puente' => $previa['monto_puente'],
        ];
    }

    private function assertFerli(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            throw new RuntimeException('Solo aplica a EMPRESA=Calzados Ferli.');
        }
    }

    private function candidatos(): Collection
    {
        $puenteId = (int) (DB::table('cuentacontable')->where('codigo', self::CUENTA_PUENTE)->value('id') ?? 0);
        $mlId = (int) (DB::table('cuentacontable')->where('codigo', self::CUENTA_MERCADOLIBRE)->value('id') ?? 0);
        if ($puenteId <= 0 || $mlId <= 0) {
            throw new RuntimeException('Faltan cuentas '.self::CUENTA_PUENTE.' o '.self::CUENTA_MERCADOLIBRE.'.');
        }

        return DB::table('asiento as a')
            ->join('asiento_movimiento as m', 'm.asiento_id', '=', 'a.id')
            ->where('a.anita_origen', 'subdiario')
            ->whereBetween('a.fecha', [self::DESDE, self::HASTA])
            ->whereNull('a.venta_id')
            ->whereNull('a.cobranza_id')
            ->where(function ($q) {
                $q->where('a.observacion', 'like', '%FAC B 21 %')
                    ->orWhere('a.observacion', 'like', '%FAC B 27 %')
                    ->orWhere('a.observacion', 'like', '%NCD B 21 %')
                    ->orWhere('a.observacion', 'like', '%NCD B 27 %');
            })
            ->groupBy('a.id', 'a.numeroasiento', 'a.fecha', 'a.observacion')
            ->havingRaw('COUNT(m.id) = 2')
            ->havingRaw('SUM(CASE WHEN m.cuentacontable_id = ? THEN 1 ELSE 0 END) = 1', [$puenteId])
            ->havingRaw('SUM(CASE WHEN m.cuentacontable_id = ? THEN 1 ELSE 0 END) = 1', [$mlId])
            ->orderBy('a.fecha')
            ->orderBy('a.numeroasiento')
            ->get([
                'a.id',
                'a.numeroasiento',
                'a.fecha',
                'a.observacion',
                DB::raw('ROUND(SUM(CASE WHEN m.cuentacontable_id = '.$puenteId.' THEN m.monto ELSE 0 END), 2) as monto_puente'),
                DB::raw('ROUND(SUM(CASE WHEN m.cuentacontable_id = '.$mlId.' THEN m.monto ELSE 0 END), 2) as monto_ml'),
            ]);
    }

    private function esCandidatoValido(int $asientoId): bool
    {
        $puenteId = (int) (DB::table('cuentacontable')->where('codigo', self::CUENTA_PUENTE)->value('id') ?? 0);
        $mlId = (int) (DB::table('cuentacontable')->where('codigo', self::CUENTA_MERCADOLIBRE)->value('id') ?? 0);

        $a = DB::table('asiento')->where('id', $asientoId)->first();
        if (! $a
            || (string) $a->anita_origen !== 'subdiario'
            || $a->venta_id !== null
            || $a->cobranza_id !== null
            || (string) $a->fecha < self::DESDE
            || (string) $a->fecha > self::HASTA
        ) {
            return false;
        }

        $obs = (string) $a->observacion;
        $obsOk = str_contains($obs, 'FAC B 21 ')
            || str_contains($obs, 'FAC B 27 ')
            || str_contains($obs, 'NCD B 21 ')
            || str_contains($obs, 'NCD B 27 ');
        if (! $obsOk) {
            return false;
        }

        $movs = DB::table('asiento_movimiento')->where('asiento_id', $asientoId)->get(['cuentacontable_id']);
        if ($movs->count() !== 2) {
            return false;
        }
        $ids = $movs->pluck('cuentacontable_id')->map(static fn ($id) => (int) $id)->sort()->values()->all();

        return $ids === [min($puenteId, $mlId), max($puenteId, $mlId)];
    }
}
