<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\ApiAnita;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Corrige correlatividad CAEA Rebisco PV 30: FAC B 54044–54053
 * (emitidas con fecha/CAEA de 2Q junio) → fecha 15/06/2026 + CAEA 1Q junio.
 */
class RebiscoCorregirFechaCaeaPv30JunioQ1 extends Command
{
    private const EMPRESA_ID = 3;

    private const PV_CODIGO = 30;

    private const NRO_DESDE = 54044;

    private const NRO_HASTA = 54053;

    private const CAEA_Q2_ACTUAL = '86249486762220';

    private const CAEA_Q1 = '86217217797455';

    private const FECHA_NUEVA = '2026-06-15';

    private const FECHA_NUEVA_ANITA = '20260615';

    private const VTO_CAEA_Q1 = '2026-06-15';

    private const VTO_CAEA_Q1_ANITA = '20260615';

    protected $signature = 'rebisco:corregir-fecha-caea-pv30-junio-q1
                            {--force : Aplicar cambios en anitaERP y Anita}
                            {--sin-anita : Solo actualizar MySQL (anitaERP)}
                            {--yes : Sin confirmación interactiva}';

    protected $description = 'Pasa FAC B PV30 54044–54053 a fecha 15/06/2026 y CAEA 1Q junio (anitaERP + Anita venta/vencae)';

    public function handle(): int
    {
        $dryRun = ! $this->option('force');
        $actualizarAnita = ! $this->option('sin-anita');

        $filas = DB::table('venta')
            ->join('puntoventa', 'puntoventa.id', '=', 'venta.puntoventa_id')
            ->where('puntoventa.empresa_id', self::EMPRESA_ID)
            ->where('puntoventa.codigo', self::PV_CODIGO)
            ->whereBetween('venta.numerocomprobante', [self::NRO_DESDE, self::NRO_HASTA])
            ->where('venta.codigo', 'like', 'FAC B%')
            ->orderBy('venta.numerocomprobante')
            ->get([
                'venta.id',
                'venta.numerocomprobante',
                'venta.fecha',
                'venta.fechajornada',
                'venta.cae',
                'venta.fechavencimientocae',
                'venta.codigo',
            ]);

        if ($filas->count() !== 10) {
            $this->error('Se esperaban 10 FAC B 54044–54053; encontradas: '.$filas->count());

            return self::FAILURE;
        }

        $this->info(($dryRun ? '[PREVIEW] ' : '[APLICAR] ').'Rebisco PV30 FAC B '.self::NRO_DESDE.'–'.self::NRO_HASTA);
        $this->line('Destino: fecha/jornada '.self::FECHA_NUEVA.' | CAEA '.self::CAEA_Q1.' | vto '.self::VTO_CAEA_Q1);
        $this->table(
            ['id', 'nro', 'fecha', 'jornada', 'cae', 'vto_cae'],
            $filas->map(fn ($r) => [
                $r->id,
                $r->numerocomprobante,
                $r->fecha,
                $r->fechajornada,
                $r->cae,
                $r->fechavencimientocae,
            ])->all(),
        );

        foreach ($filas as $fila) {
            if ((string) $fila->cae !== self::CAEA_Q2_ACTUAL && (string) $fila->cae !== self::CAEA_Q1) {
                $this->error('CAEA inesperado en venta id '.$fila->id.': '.$fila->cae);

                return self::FAILURE;
            }
        }

        if ($dryRun) {
            $this->warn('Dry-run: no se modificó nada. Ejecutar con --force --yes para aplicar.');

            return self::SUCCESS;
        }

        if (! $this->option('yes') && ! $this->confirm('¿Aplicar corrección en anitaERP'.($actualizarAnita ? ' y Anita' : '').'?')) {
            $this->warn('Cancelado.');

            return self::SUCCESS;
        }

        $ids = $filas->pluck('id')->map(fn ($id) => (int) $id)->all();

        DB::transaction(function () use ($ids): void {
            $afectadas = DB::table('venta')
                ->whereIn('id', $ids)
                ->update([
                    'fecha' => self::FECHA_NUEVA,
                    'fechajornada' => self::FECHA_NUEVA,
                    'cae' => self::CAEA_Q1,
                    'fechavencimientocae' => self::VTO_CAEA_Q1,
                    'updated_at' => now(),
                ]);

            if ($afectadas !== count($ids)) {
                throw new \RuntimeException('UPDATE venta esperaba '.count($ids).' filas, afectó '.$afectadas);
            }
        });

        $this->info('anitaERP: '.$filas->count().' ventas actualizadas.');

        if ($actualizarAnita) {
            $this->actualizarAnita($filas->pluck('numerocomprobante')->map(fn ($n) => (int) $n)->all());
        } else {
            $this->warn('Anita omitida (--sin-anita).');
        }

        $this->verificarErp();
        if ($actualizarAnita) {
            $this->verificarAnita();
        }

        Log::info('rebisco.corregir_fecha_caea_pv30_junio_q1', [
            'venta_ids' => $ids,
            'caea_nuevo' => self::CAEA_Q1,
            'fecha_nueva' => self::FECHA_NUEVA,
            'anita' => $actualizarAnita,
        ]);

        $this->info('Listo.');

        return self::SUCCESS;
    }

    /**
     * @param  list<int>  $numeros
     */
    private function actualizarAnita(array $numeros): void
    {
        $api = new ApiAnita;
        $okVenta = 0;
        $okVencae = 0;

        foreach ($numeros as $nro) {
            $whereVenta = " WHERE ven_tipo = 'FAC' AND ven_letra = 'B' AND ven_sucursal = 30"
                ." AND ven_nro = '".$nro."' AND ven_empresa = '3' ";
            $respVenta = $api->apiCallEscritura([
                'acc' => 'update',
                'tabla' => 'venta',
                'sistema' => 'ventas',
                'valores' => " ven_fecha = '".self::FECHA_NUEVA_ANITA."', "
                    ."ven_fecha_vto = '".self::FECHA_NUEVA_ANITA."' ",
                'whereArmado' => $whereVenta,
            ], 'venta update fecha CAEA Rebisco PV30 '.$nro);
            if (! ApiAnita::respuestaBridgeEscrituraExitosa($respVenta)) {
                throw new \RuntimeException(
                    'Anita venta nro '.$nro.': '.(ApiAnita::extraerMensajeError($respVenta) ?? 'fallo escritura'),
                );
            }
            $okVenta++;

            $whereVencae = " WHERE venc_tipo = 'FAC' AND venc_letra = 'B' AND venc_sucursal = 30"
                ." AND venc_nro = '".$nro."' ";
            $respVencae = $api->apiCallEscritura([
                'acc' => 'update',
                'tabla' => 'vencae',
                'sistema' => 'ventas',
                'valores' => " venc_nro_cae = '".self::CAEA_Q1."', "
                    ."venc_fecha_vto = '".self::VTO_CAEA_Q1_ANITA."' ",
                'whereArmado' => $whereVencae,
            ], 'vencae update CAEA Rebisco PV30 '.$nro);
            if (! ApiAnita::respuestaBridgeEscrituraExitosa($respVencae)) {
                throw new \RuntimeException(
                    'Anita vencae nro '.$nro.': '.(ApiAnita::extraerMensajeError($respVencae) ?? 'fallo escritura'),
                );
            }
            $okVencae++;
        }

        $this->info("Anita: venta={$okVenta}, vencae={$okVencae}.");
    }

    private function verificarErp(): void
    {
        $mal = DB::table('venta')
            ->join('puntoventa', 'puntoventa.id', '=', 'venta.puntoventa_id')
            ->where('puntoventa.empresa_id', self::EMPRESA_ID)
            ->where('puntoventa.codigo', self::PV_CODIGO)
            ->whereBetween('venta.numerocomprobante', [self::NRO_DESDE, self::NRO_HASTA])
            ->where('venta.codigo', 'like', 'FAC B%')
            ->where(function ($q): void {
                $q->where('venta.fecha', '!=', self::FECHA_NUEVA)
                    ->orWhere('venta.fechajornada', '!=', self::FECHA_NUEVA)
                    ->orWhere('venta.cae', '!=', self::CAEA_Q1)
                    ->orWhere('venta.fechavencimientocae', '!=', self::VTO_CAEA_Q1);
            })
            ->count();

        if ($mal > 0) {
            throw new \RuntimeException("Verificación ERP falló: {$mal} filas sin el estado esperado.");
        }
        $this->info('Verificación anitaERP OK.');
    }

    private function verificarAnita(): void
    {
        $api = new ApiAnita;
        $venta = ApiAnita::parsearRespuestaLista($api->apiCall([
            'tabla' => 'venta',
            'acc' => 'list',
            'campos' => 'ven_nro,ven_fecha,ven_fecha_vto',
            'whereArmado' => " WHERE ven_tipo = 'FAC' AND ven_letra = 'B' AND ven_sucursal = 30"
                .' AND ven_nro >= '.self::NRO_DESDE.' AND ven_nro <= '.self::NRO_HASTA
                ." AND ven_empresa = '3' ",
        ]));
        $vencae = ApiAnita::parsearRespuestaLista($api->apiCall([
            'tabla' => 'vencae',
            'acc' => 'list',
            'campos' => 'venc_nro,venc_nro_cae,venc_fecha_vto',
            'whereArmado' => " WHERE venc_tipo = 'FAC' AND venc_letra = 'B' AND venc_sucursal = 30"
                .' AND venc_nro >= '.self::NRO_DESDE.' AND venc_nro <= '.self::NRO_HASTA,
        ]));

        if (count($venta['filas']) !== 10 || count($vencae['filas']) !== 10) {
            throw new \RuntimeException(
                'Verificación Anita: venta='.count($venta['filas']).' vencae='.count($vencae['filas']).' (esperaba 10).',
            );
        }

        foreach ($venta['filas'] as $f) {
            if ((string) $f->ven_fecha !== self::FECHA_NUEVA_ANITA
                || (string) $f->ven_fecha_vto !== self::FECHA_NUEVA_ANITA) {
                throw new \RuntimeException('Anita venta nro '.$f->ven_nro.' fecha no actualizada.');
            }
        }
        foreach ($vencae['filas'] as $f) {
            if ((string) $f->venc_nro_cae !== self::CAEA_Q1
                || (string) $f->venc_fecha_vto !== self::VTO_CAEA_Q1_ANITA) {
                throw new \RuntimeException('Anita vencae nro '.$f->venc_nro.' CAEA no actualizado.');
            }
        }

        $this->info('Verificación Anita OK.');
    }
}
