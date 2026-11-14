<?php

use App\Support\Caja\TesoreriaCrownBovedaVigenciaSupport;
use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogo unificado bóveda: cierra tipos absorbidos al 31/10/2026,
 * crea ECCH/BGO y subtipos desde 01/11/2026.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! EntornoEmpresaSupport::esCrown()
            || ! Schema::hasTable('tesoreria_crown_boveda_tipo')
            || ! Schema::hasColumn('tesoreria_crown_boveda_tipo', 'vigente_desde')) {
            return;
        }

        $desde = TesoreriaCrownBovedaVigenciaSupport::VIGENCIA_LEGADO_DESDE;
        $hasta = TesoreriaCrownBovedaVigenciaSupport::VIGENCIA_LEGADO_HASTA;
        $corte = TesoreriaCrownBovedaVigenciaSupport::CORTE_NUEVO_CATALOGO;
        $ahora = now();

        // Baseline: todos los tipos con desde legado si aún no tienen vigencia.
        DB::table('tesoreria_crown_boveda_tipo')
            ->whereNull('vigente_desde')
            ->update(['vigente_desde' => $desde, 'updated_at' => $ahora]);

        if (Schema::hasTable('tesoreria_crown_boveda_subtipo')
            && Schema::hasColumn('tesoreria_crown_boveda_subtipo', 'vigente_desde')) {
            DB::table('tesoreria_crown_boveda_subtipo')
                ->whereNull('vigente_desde')
                ->update(['vigente_desde' => $desde, 'updated_at' => $ahora]);
        }

        $cierran = [3, 4, 7, 11, 12, 13, 15, 18, 19, 20, 23, 24, 26, 29, 31, 32, 33, 34, 37, 38, 40, 41];
        DB::table('tesoreria_crown_boveda_tipo')
            ->whereIn('codigo', $cierran)
            ->update([
                'vigente_desde' => $desde,
                'vigente_hasta' => $hasta,
                'updated_at' => $ahora,
            ]);

        // Renombres Crown del Excel (códigos que continúan).
        $renombres = [
            6 => ['abreviatura' => 'ICCH', 'nombre' => 'Ingreso caja chica'],
            25 => ['abreviatura' => 'IFON', 'nombre' => 'Ingreso fondo a tesorería'],
            30 => ['abreviatura' => 'RFON', 'nombre' => 'Egreso fondo a tesorería'],
        ];
        foreach ($renombres as $codigo => $datos) {
            DB::table('tesoreria_crown_boveda_tipo')
                ->where('codigo', $codigo)
                ->update($datos + ['updated_at' => $ahora]);
        }

        // Subtipos OEGR: ENV/OTR cierran al 31/10. DEV continúa (mismo código, único).
        if (Schema::hasTable('tesoreria_crown_boveda_subtipo')) {
            $oegrTipoIds = DB::table('tesoreria_crown_boveda_tipo')
                ->where('codigo', 14)
                ->pluck('id');
            DB::table('tesoreria_crown_boveda_subtipo')
                ->whereIn('tipo_id', $oegrTipoIds)
                ->whereIn('codigo', ['ENV', 'OTR'])
                ->update([
                    'vigente_desde' => $desde,
                    'vigente_hasta' => $hasta,
                    'updated_at' => $ahora,
                ]);
        }

        $salas = DB::table('sala')
            ->whereRaw('UPPER(codigo) <> ?', ['AC'])
            ->orderBy('id')
            ->get(['id']);

        foreach ($salas as $sala) {
            $salaId = (int) $sala->id;
            $this->asegurarTipo($salaId, 43, 'ECCH', 'Egreso caja chica', '0', '0', $corte, $ahora);
            $this->asegurarTipo($salaId, 44, 'BGO', 'Bingo', '2', '0', $corte, $ahora);

            $mapaTipos = DB::table('tesoreria_crown_boveda_tipo')
                ->where('sala_id', $salaId)
                ->whereIn('codigo', [1, 6, 14, 25, 27, 28, 30, 42, 43, 44])
                ->pluck('id', 'codigo');

            $this->seedSubtipos($salaId, $mapaTipos, $corte, $ahora);
        }
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esCrown()
            || ! Schema::hasTable('tesoreria_crown_boveda_tipo')) {
            return;
        }

        // No borra movimientos: solo revierte vigencias nuevas y tipos 43/44 sin uso.
        $ahora = now();
        DB::table('tesoreria_crown_boveda_tipo')
            ->whereIn('codigo', [3, 4, 7, 11, 12, 13, 15, 18, 19, 20, 23, 24, 26, 29, 31, 32, 33, 34, 37, 38, 40, 41])
            ->update(['vigente_hasta' => null, 'updated_at' => $ahora]);

        if (Schema::hasTable('tesoreria_crown_boveda_subtipo')) {
            DB::table('tesoreria_crown_boveda_subtipo')
                ->whereDate('vigente_desde', TesoreriaCrownBovedaVigenciaSupport::CORTE_NUEVO_CATALOGO)
                ->whereNotIn('codigo', ['HIB', 'MES', 'PUB'])
                ->delete();

            $oegrTipoIds = DB::table('tesoreria_crown_boveda_tipo')->where('codigo', 14)->pluck('id');
            DB::table('tesoreria_crown_boveda_subtipo')
                ->whereIn('tipo_id', $oegrTipoIds)
                ->whereIn('codigo', ['ENV', 'OTR'])
                ->update(['vigente_hasta' => null, 'updated_at' => $ahora]);
        }

        $sinUso = DB::table('tesoreria_crown_boveda_tipo as t')
            ->leftJoin('tesoreria_crown_boveda as b', 'b.tipo_id', '=', 't.id')
            ->whereIn('t.codigo', [43, 44])
            ->groupBy('t.id')
            ->havingRaw('COUNT(b.id) = 0')
            ->pluck('t.id');
        if ($sinUso->isNotEmpty()) {
            if (Schema::hasTable('tesoreria_crown_boveda_subtipo')) {
                DB::table('tesoreria_crown_boveda_subtipo')->whereIn('tipo_id', $sinUso)->delete();
            }
            DB::table('tesoreria_crown_boveda_tipo')->whereIn('id', $sinUso)->delete();
        }
    }

    private function asegurarTipo(
        int $salaId,
        int $codigo,
        string $abrev,
        string $nombre,
        string $signo,
        string $operacion,
        string $desde,
        $ahora
    ): void {
        $existe = DB::table('tesoreria_crown_boveda_tipo')
            ->where('sala_id', $salaId)
            ->where('codigo', $codigo)
            ->first();
        if ($existe) {
            DB::table('tesoreria_crown_boveda_tipo')->where('id', $existe->id)->update([
                'abreviatura' => $abrev,
                'nombre' => $nombre,
                'signo' => $signo,
                'operacion' => $operacion,
                'activo' => true,
                'vigente_desde' => $desde,
                'vigente_hasta' => null,
                'updated_at' => $ahora,
            ]);

            return;
        }
        DB::table('tesoreria_crown_boveda_tipo')->insert([
            'sala_id' => $salaId,
            'codigo' => $codigo,
            'abreviatura' => $abrev,
            'nombre' => $nombre,
            'signo' => $signo,
            'operacion' => $operacion,
            'activo' => true,
            'vigente_desde' => $desde,
            'vigente_hasta' => null,
            'created_at' => $ahora,
            'updated_at' => $ahora,
        ]);
    }

    /**
     * @param  \Illuminate\Support\Collection<int|string, mixed>  $mapaTipos
     */
    private function seedSubtipos(int $salaId, $mapaTipos, string $desde, $ahora): void
    {
        if (! Schema::hasTable('tesoreria_crown_boveda_subtipo')) {
            return;
        }

        $defs = [
            1 => [ // INGR
                ['ENC', 'Valores encontrados', true, null],
                ['AGE', 'Agencias', true, null],
                ['TOR', 'Torneos / eventos', true, null],
            ],
            14 => [ // OEGR: TOR/VOU nuevos; DEV continúa
                ['TOR', 'Torneo / evento', true, null],
                ['DEV', 'Devoluciones', true, null],
                ['VOU', 'Voucher', true, null],
            ],
            6 => [ // ICCH
                ['ADM', 'Fondo caja chica adm.', true, null],
                ['HOT', 'Fondo caja chica hotel', true, null],
            ],
            43 => [ // ECCH
                ['ADM', 'Fondo caja chica adm.', true, null],
                ['HOT', 'Fondo caja chica hotel', true, null],
            ],
            27 => [ // ICAM
                ['PBS', 'Prueba bill / slot', true, null],
                ['CAM', 'Cambio', true, null],
                ['TOR', 'Torneo / evento', true, null],
            ],
            28 => [ // ECAM
                ['PBS', 'Prueba bill / slot', true, null],
                ['CAM', 'Cambio', true, null],
                ['TOR', 'Torneo / evento', true, null],
            ],
            44 => [ // BGO
                ['VCA', 'Venta de cartones', true, '0'],
                ['REN', 'Rendición cajero', true, '0'],
                ['PRE', 'Pago de premios', true, '1'],
                ['HAB', 'Habilitación cajero', true, '1'],
                ['SOL', 'Bingo solidario / RSE', true, '1'],
            ],
        ];

        foreach ($defs as $codigoTipo => $subs) {
            $tipoId = (int) ($mapaTipos[$codigoTipo] ?? 0);
            if ($tipoId <= 0) {
                continue;
            }
            foreach ($subs as [$cod, $nombre, $mueve, $signo]) {
                // OEGR DEV: misma fila continua (abrirSinHasta). TOR/VOU: desde corte.
                $abrirDev = $codigoTipo === 14 && $cod === 'DEV';
                $this->asegurarSubtipo(
                    $salaId,
                    $tipoId,
                    $cod,
                    $nombre,
                    (bool) $mueve,
                    $signo,
                    $desde,
                    $ahora,
                    $abrirDev
                );
            }
        }

        // PROP: asegurar HIB/PUB/MES abiertos (sin hasta).
        $propId = (int) ($mapaTipos[42] ?? 0);
        if ($propId > 0) {
            foreach ([
                ['HIB', 'Híbrida', false],
                ['PUB', 'Pública', true],
                ['MES', 'Mesas', false],
            ] as [$cod, $nombre, $mueve]) {
                $this->asegurarSubtipo($salaId, $propId, $cod, $nombre, $mueve, null, $desde, $ahora, true);
            }
        }

        // Fondos IFON/RFON: destinos.
        $destinos = [
            ['CIP', 'Cipolletti'],
            ['ROC', 'Roca'],
            ['VIE', 'Viedma'],
            ['LGR', 'Las Grutas'],
            ['CHO', 'Choele Choel'],
            ['CAT', 'Catriel'],
            ['VRE', 'Villa Regina'],
            ['CSA', 'Cinco Saltos'],
            ['EFA', 'El Faro'],
            ['BAN', 'Banco'],
            ['ADM', 'Administración central'],
        ];
        foreach ([25, 30] as $codigoTipo) {
            $tipoId = (int) ($mapaTipos[$codigoTipo] ?? 0);
            if ($tipoId <= 0) {
                continue;
            }
            foreach ($destinos as [$cod, $nombre]) {
                $this->asegurarSubtipo($salaId, $tipoId, $cod, $nombre, true, null, $desde, $ahora);
            }
        }
    }

    private function asegurarSubtipo(
        int $salaId,
        int $tipoId,
        string $codigo,
        string $nombre,
        bool $mueveBilletaje,
        ?string $signo,
        string $desde,
        $ahora,
        bool $abrirSinHasta = false
    ): void {
        $existe = DB::table('tesoreria_crown_boveda_subtipo')
            ->where('tipo_id', $tipoId)
            ->where('codigo', $codigo)
            ->first();

        if ($existe) {
            $update = [
                'nombre' => $nombre,
                'mueve_billetaje' => $mueveBilletaje,
                'signo' => $signo,
                'activo' => true,
                'updated_at' => $ahora,
            ];
            if ($abrirSinHasta || $existe->vigente_desde === null || (string) $existe->vigente_desde >= $desde) {
                $update['vigente_desde'] = $existe->vigente_desde ?: $desde;
            }
            if ($abrirSinHasta) {
                $update['vigente_hasta'] = null;
                if (! $existe->vigente_desde) {
                    $update['vigente_desde'] = TesoreriaCrownBovedaVigenciaSupport::VIGENCIA_LEGADO_DESDE;
                }
            } elseif ($existe->vigente_hasta === null && (string) ($existe->vigente_desde ?? '') < $desde
                && ! in_array($codigo, ['HIB', 'MES', 'PUB'], true)) {
                // Subtipo preexistente sin hasta que no es PROP: si es nuevo catálogo, fijar desde.
                if (in_array($codigo, ['ENC', 'AGE', 'TOR', 'VOU', 'ADM', 'HOT', 'PBS', 'CAM', 'VCA', 'REN', 'PRE', 'HAB', 'SOL', 'CIP', 'ROC', 'VIE', 'LGR', 'CHO', 'CAT', 'VRE', 'CSA', 'EFA', 'BAN'], true)) {
                    $update['vigente_desde'] = $desde;
                }
            }
            DB::table('tesoreria_crown_boveda_subtipo')->where('id', $existe->id)->update($update);

            return;
        }

        DB::table('tesoreria_crown_boveda_subtipo')->insert([
            'sala_id' => $salaId,
            'tipo_id' => $tipoId,
            'codigo' => $codigo,
            'nombre' => $nombre,
            'activo' => true,
            'mueve_billetaje' => $mueveBilletaje,
            'signo' => $signo,
            'vigente_desde' => $abrirSinHasta
                ? TesoreriaCrownBovedaVigenciaSupport::VIGENCIA_LEGADO_DESDE
                : $desde,
            'vigente_hasta' => null,
            'created_at' => $ahora,
            'updated_at' => $ahora,
        ]);
    }
};
