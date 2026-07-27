<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\ApiAnita;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Biyemas PV20 julio Q1: corrige inversiones fecha &lt; último informado (ARCA 704).
 *
 * Bloque A (freno actual): #184425–184428 fecha 01/07 → 02/07
 * Bloque B (próximo freno): #184585–184586 fecha 01/07 → 08/07
 *
 * No cambia CAEA ni numeración: solo fecha (= CbteFch ARCA).
 * No toca fechajornada (jornada operativa / cierre contable).
 */
class BiyemasCorregirFechaCaeaPv20JulioQ1 extends Command
{
    private const EMPRESA_ID = 1;

    private const PV_CODIGO = 20;

    private const CAEA_Q1 = '86261221203188';

    /**
     * @var list<array{nro_desde:int,nro_hasta:int,fecha_nueva:string,ids:list<int>}>
     */
    private const BLOQUES = [
        [
            'nro_desde' => 184425,
            'nro_hasta' => 184428,
            'fecha_nueva' => '2026-07-02',
            'ids' => [57332, 57333, 57334, 57335],
            'motivo' => 'Freno ARCA 704: #184424 (02/07) ya informado; estos llevan fecha 01/07',
        ],
        [
            'nro_desde' => 184585,
            'nro_hasta' => 184586,
            'fecha_nueva' => '2026-07-08',
            'ids' => [50920, 55949],
            'motivo' => 'Próximo 704: #184584 (08/07) antecede a #184585–184586 con fecha 01/07',
        ],
    ];

    protected $signature = 'biyemas:corregir-fecha-caea-pv20-julio-q1
                            {--bloque= : Solo A (184425-428) o B (184585-586); vacío = ambos}
                            {--force : Aplicar cambios en anitaERP y Anita}
                            {--sin-anita : Solo actualizar MySQL (anitaERP)}
                            {--yes : Sin confirmación interactiva}';

    protected $description = 'Biyemas PV20: corrige fechas CAEA julio Q1 que rompen correlatividad ARCA 704';

    public function handle(): int
    {
        $dryRun = ! $this->option('force');
        $actualizarAnita = ! $this->option('sin-anita');
        $solo = strtoupper(trim((string) $this->option('bloque')));

        $bloques = self::BLOQUES;
        if ($solo === 'A') {
            $bloques = [self::BLOQUES[0]];
        } elseif ($solo === 'B') {
            $bloques = [self::BLOQUES[1]];
        } elseif ($solo !== '') {
            $this->error(' --bloque debe ser A, B o vacío.');

            return self::FAILURE;
        }

        $this->info(($dryRun ? '[PREVIEW] ' : '[APLICAR] ').'Biyemas PV20 FAC B — corrección fecha CAEA julio Q1');
        $this->line('Empresa '.self::EMPRESA_ID.' | PV '.self::PV_CODIGO.' | CAEA '.self::CAEA_Q1.' (sin cambio)');
        $this->newLine();

        $todasFilas = collect();
        foreach ($bloques as $bloque) {
            $filas = $this->cargarBloque($bloque);
            if ($filas === null) {
                return self::FAILURE;
            }

            $etiqueta = $bloque['nro_desde'].'–'.$bloque['nro_hasta'];
            $this->warn("Bloque {$etiqueta}: {$bloque['motivo']}");
            $this->line('Destino fecha (CbteFch; fechajornada no se toca): '.$bloque['fecha_nueva']);
            $this->table(
                ['id', 'nro', 'fecha_actual', 'jornada', 'inf', 'total', 'codigo'],
                $filas->map(fn ($r) => [
                    $r->id,
                    $r->numerocomprobante,
                    $r->fecha,
                    $r->fechajornada,
                    $r->caea_informado_estado ?? 'null',
                    number_format((float) $r->total, 2, ',', '.'),
                    $r->codigo,
                ])->all(),
            );
            $this->newLine();
            $todasFilas = $todasFilas->concat($filas);
        }

        if ($dryRun) {
            $this->warn('Dry-run: no se modificó nada.');
            $this->line('Aplicar solo bloque A (destrabar ahora): php artisan biyemas:corregir-fecha-caea-pv20-julio-q1 --bloque=A --force --yes');
            $this->line('Aplicar ambos: php artisan biyemas:corregir-fecha-caea-pv20-julio-q1 --force --yes');
            $this->line('Solo ERP (sin Anita): agregar --sin-anita');

            return self::SUCCESS;
        }

        if (! $this->option('yes') && ! $this->confirm('¿Aplicar corrección en anitaERP'.($actualizarAnita ? ' y Anita' : '').'?')) {
            $this->warn('Cancelado.');

            return self::SUCCESS;
        }

        foreach ($bloques as $bloque) {
            $filas = $this->cargarBloque($bloque);
            if ($filas === null) {
                return self::FAILURE;
            }
            $ids = $filas->pluck('id')->map(fn ($id) => (int) $id)->all();
            $fecha = $bloque['fecha_nueva'];

            DB::transaction(function () use ($ids, $fecha): void {
                $afectadas = DB::table('venta')
                    ->whereIn('id', $ids)
                    ->update([
                        'fecha' => $fecha,
                        // fechajornada NO se modifica: es la jornada operativa / cierre contable.
                        // Reintentar informe: limpiar error 704 del #184425.
                        'caea_informado_estado' => null,
                        'caea_informado_at' => null,
                        'caea_informado_codigo_error' => null,
                        'caea_informado_mensaje' => null,
                        'updated_at' => now(),
                    ]);

                if ($afectadas !== count($ids)) {
                    throw new \RuntimeException('UPDATE venta esperaba '.count($ids).' filas, afectó '.$afectadas);
                }
            });

            $this->info('anitaERP OK: '.count($ids).' ventas → '.$fecha);

            if ($actualizarAnita) {
                $this->actualizarAnita(
                    $filas->pluck('numerocomprobante')->map(fn ($n) => (int) $n)->all(),
                    $fecha,
                );
            }

            $this->verificarErp($bloque);
        }

        if ($actualizarAnita) {
            foreach ($bloques as $bloque) {
                $this->verificarAnita(
                    (int) $bloque['nro_desde'],
                    (int) $bloque['nro_hasta'],
                    $bloque['fecha_nueva'],
                );
            }
        } else {
            $this->warn('Anita omitida (--sin-anita).');
        }

        Log::info('biyemas.corregir_fecha_caea_pv20_julio_q1', [
            'bloques' => $bloques,
            'anita' => $actualizarAnita,
            'venta_ids' => $todasFilas->pluck('id')->all(),
        ]);

        $this->info('Listo. Reencolar presentación CAEA Biyemas 202607/Q1 (calculadora + avión).');

        return self::SUCCESS;
    }

    /**
     * @param  array{nro_desde:int,nro_hasta:int,fecha_nueva:string,ids:list<int>,motivo:string}  $bloque
     * @return \Illuminate\Support\Collection<int, object>|null
     */
    private function cargarBloque(array $bloque): ?\Illuminate\Support\Collection
    {
        $filas = DB::table('venta')
            ->join('puntoventa', 'puntoventa.id', '=', 'venta.puntoventa_id')
            ->where('puntoventa.empresa_id', self::EMPRESA_ID)
            ->where('puntoventa.codigo', self::PV_CODIGO)
            ->whereIn('venta.id', $bloque['ids'])
            ->whereBetween('venta.numerocomprobante', [$bloque['nro_desde'], $bloque['nro_hasta']])
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
                'venta.total',
                'venta.caea_informado_estado',
            ]);

        if ($filas->count() !== count($bloque['ids'])) {
            $this->error(sprintf(
                'Bloque %d–%d: se esperaban %d filas; encontradas %d.',
                $bloque['nro_desde'],
                $bloque['nro_hasta'],
                count($bloque['ids']),
                $filas->count(),
            ));

            return null;
        }

        foreach ($filas as $fila) {
            if ((string) $fila->cae !== self::CAEA_Q1) {
                $this->error('CAEA inesperado en venta id '.$fila->id.': '.$fila->cae);

                return null;
            }
        }

        return $filas;
    }

    /**
     * @param  list<int>  $numeros
     */
    private function actualizarAnita(array $numeros, string $fechaYmd): void
    {
        $fechaAnita = str_replace('-', '', $fechaYmd);
        $api = new ApiAnita;
        $ok = 0;

        foreach ($numeros as $nro) {
            $whereVenta = " WHERE ven_tipo = 'FAC' AND ven_letra = 'B' AND ven_sucursal = ".self::PV_CODIGO
                ." AND ven_nro = '".$nro."' AND ven_empresa = '".self::EMPRESA_ID."' ";
            $respVenta = $api->apiCallEscritura([
                'acc' => 'update',
                'tabla' => 'venta',
                'sistema' => 'ventas',
                'valores' => " ven_fecha = '".$fechaAnita."', "
                    ."ven_fecha_vto = '".$fechaAnita."' ",
                'whereArmado' => $whereVenta,
            ], 'venta update fecha CAEA Biyemas PV20 '.$nro);

            if (! ApiAnita::respuestaBridgeEscrituraExitosa($respVenta)) {
                throw new \RuntimeException(
                    'Anita venta nro '.$nro.': '.(ApiAnita::extraerMensajeError($respVenta) ?? 'fallo escritura'),
                );
            }
            $ok++;
        }

        $this->info("Anita venta actualizadas: {$ok}.");
    }

    /**
     * @param  array{nro_desde:int,nro_hasta:int,fecha_nueva:string,ids:list<int>}  $bloque
     */
    private function verificarErp(array $bloque): void
    {
        $mal = DB::table('venta')
            ->join('puntoventa', 'puntoventa.id', '=', 'venta.puntoventa_id')
            ->where('puntoventa.empresa_id', self::EMPRESA_ID)
            ->where('puntoventa.codigo', self::PV_CODIGO)
            ->whereIn('venta.id', $bloque['ids'])
            ->where(function ($q) use ($bloque): void {
                $q->where('venta.fecha', '!=', $bloque['fecha_nueva'])
                    ->orWhereNotNull('venta.caea_informado_estado');
            })
            ->count();

        if ($mal > 0) {
            throw new \RuntimeException("Verificación ERP falló: {$mal} filas sin el estado esperado.");
        }
        $this->info('Verificación anitaERP OK ('.$bloque['nro_desde'].'–'.$bloque['nro_hasta'].').');
    }

    private function verificarAnita(int $nroDesde, int $nroHasta, string $fechaYmd): void
    {
        $fechaAnita = str_replace('-', '', $fechaYmd);
        $api = new ApiAnita;
        $venta = ApiAnita::parsearRespuestaLista($api->apiCall([
            'tabla' => 'venta',
            'acc' => 'list',
            'campos' => 'ven_nro,ven_fecha,ven_fecha_vto',
            'whereArmado' => " WHERE ven_tipo = 'FAC' AND ven_letra = 'B' AND ven_sucursal = ".self::PV_CODIGO
                .' AND ven_nro >= '.$nroDesde.' AND ven_nro <= '.$nroHasta
                ." AND ven_empresa = '".self::EMPRESA_ID."' ",
        ]));

        $esperados = $nroHasta - $nroDesde + 1;
        if (count($venta['filas']) !== $esperados) {
            throw new \RuntimeException(
                'Verificación Anita: venta='.count($venta['filas']).' (esperaba '.$esperados.').',
            );
        }

        foreach ($venta['filas'] as $f) {
            if ((string) $f->ven_fecha !== $fechaAnita || (string) $f->ven_fecha_vto !== $fechaAnita) {
                throw new \RuntimeException('Anita venta nro '.$f->ven_nro.' fecha no actualizada.');
            }
        }

        $this->info("Verificación Anita OK ({$nroDesde}–{$nroHasta}).");
    }
}
