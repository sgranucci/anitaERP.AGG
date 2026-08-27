<?php

namespace App\Console\Commands;

use App\ApiAnita;
use App\Models\Compras\Requisicion;
use App\Models\Compras\Requisicion_Estado;
use App\Services\Compras\RequisicionAnitaAprobcompSyncService;
use App\Services\Compras\RequisicionImportarFaltantesDesdeAnitaService;
use App\Support\Compras\AnitaSync\AnitaUsuarioBridgeSupport;
use App\Support\Compras\AnitaSync\Ordencompra\OrdencompraAnitaWhereSupport;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;

class ProyeccionVincularRequisicionesCommand extends Command
{
    protected $signature = 'proyeccion:vincular-requisiciones {--usuario=1}';

    protected $description = 'Vincula OC de proyección a requisición, importa las faltantes y completa creador/aprobador (ERP + Anita aprobcomp)';

    /** OC 2026: requisición ya está en ERP */
    private const PARES_2026 = [
        220046 => 229207,
        220586 => 229592,
        220590 => 229591,
        220748 => 229695,
        220753 => 229700,
        220996 => 229771,
        220997 => 229769,
        221090 => 229928,
        221091 => 229878,
        221092 => 229879,
        221117 => 229968,
        221118 => 229969,
        221186 => 230010,
        221555 => 230294,
        221625 => 230320,
        222124 => 230791,
        222125 => 230790,
        222126 => 230789,
        222385 => 230717,
        222387 => 230718,
        222388 => 230716,
    ];

    /** OC viejas: hay que importar la requisición */
    private const PARES_VIEJAS = [
        38427 => 57893,
        38539 => 57907,
        149433 => 176722,
        155320 => 180278,
        159451 => 183971,
        160656 => 184702,
        161587 => 185535,
        161982 => 31082,
        162409 => 31161,
        162519 => 186019,
        162815 => 186339,
        162855 => 186425,
        162868 => 186469,
        162989 => 185399,
        163058 => 186350,
        163730 => 187290,
        163790 => 187354,
        163943 => 31395,
        164087 => 31434,
        164191 => 187705,
        164355 => 31469,
        164668 => 31523,
        165504 => 31685,
        190843 => 208089,
        201572 => 216171,
        201869 => 216426,
    ];

    public function handle(RequisicionImportarFaltantesDesdeAnitaService $importador): int
    {
        $usuarioId = (int) $this->option('usuario');
        if ($usuarioId <= 0 || ! Auth::loginUsingId($usuarioId)) {
            $this->error("No se pudo autenticar usuario id {$usuarioId}.");

            return self::FAILURE;
        }

        $esperado = self::PARES_2026 + self::PARES_VIEJAS;
        $this->info('0) Verificar penmp_requisicion en Anita ('.count($esperado).' OC)...');
        $pares = $this->paresDesdeAnita(array_keys($esperado));
        foreach ($esperado as $oc => $req) {
            $anita = $pares[$oc] ?? 0;
            if ($anita <= 0) {
                $this->warn("   OC {$oc}: Anita sin requisición, uso mapa {$req}");
                $pares[$oc] = $req;
            } elseif ($anita !== $req) {
                $this->warn("   OC {$oc}: Anita={$anita} mapa={$req} → uso Anita");
            }
        }

        $nrosTodos = array_values(array_unique(array_filter($pares)));
        $yaEnErp = Requisicion::query()
            ->whereIn('numerorequisicion', $nrosTodos)
            ->pluck('id', 'numerorequisicion')
            ->mapWithKeys(static fn ($id, $nro) => [(int) $nro => (int) $id])
            ->all();

        $paresYa = [];
        $paresFaltan = [];
        foreach ($pares as $oc => $req) {
            if (isset($yaEnErp[$req])) {
                $paresYa[$oc] = $req;
            } else {
                $paresFaltan[$oc] = $req;
            }
        }

        $this->info('1) Vincular '.count($paresYa).' OC con requisición ya en ERP...');
        $r1 = $importador->vincularParesOcReq($paresYa);
        $this->line('   vinculadas='.$r1['vinculadas'].' omitidas='.$r1['omitidas']);
        foreach ($r1['errores'] as $e) {
            $this->warn('   '.$e);
        }

        $nrosFaltan = array_values(array_unique(array_values($paresFaltan)));
        $this->info('2) Importar '.count($nrosFaltan).' requisiciones faltantes...');
        $r2 = $importador->importarPorNumeros($nrosFaltan, $usuarioId);
        $this->line('   importadas='.$r2['importadas'].' omitidas='.$r2['omitidas']);
        foreach ($r2['errores'] as $e) {
            $this->warn('   '.$e);
        }

        $this->info('3) Vincular '.count($paresFaltan).' OC de requisiciones importadas...');
        $r3 = $importador->vincularParesOcReq($paresFaltan);
        $this->line('   vinculadas='.$r3['vinculadas'].' omitidas='.$r3['omitidas']);
        foreach ($r3['errores'] as $e) {
            $this->warn('   '.$e);
        }

        $this->info('4) Diagnóstico Anita reqmae/aprobcomp ('.count($nrosTodos).' req)...');
        $diag = $this->diagnosticarAnita($nrosTodos);
        $sinUsuario = 0;
        $sinAprob = 0;
        foreach ($diag as $nro => $d) {
            if (! $d['reqmae']) {
                $this->warn("   req {$nro}: no está en reqmae");

                continue;
            }
            if ((int) $d['reqm_usuario'] <= 0) {
                $sinUsuario++;
                $this->warn("   req {$nro}: reqm_usuario=0 (confeccionó vacío en l-proy Anita)");
            }
            if (! $d['aprobcomp']) {
                $sinAprob++;
                $this->warn("   req {$nro}: sin fila aprobcomp REQ (aprobación vacía en l-proy Anita)");
            }
        }
        $this->line("   sin reqm_usuario={$sinUsuario} sin aprobcomp={$sinAprob}");

        $this->info('5) Copiar creador/aprobador Anita → ERP (para l-proy ERP)...');
        $enriquecidas = $this->enriquecerErpDesdeAnita($nrosTodos, $diag, $usuarioId);
        $this->line('   creousuario='.$enriquecidas['creousuario'].' historia_aprobada='.$enriquecidas['aprobada']);

        $this->info('6) Snapshot aprobcomp ERP→Anita donde falte...');
        $sync = app(RequisicionAnitaAprobcompSyncService::class);
        $ins = 0;
        $omit = 0;
        $err = 0;
        foreach ($nrosTodos as $nro) {
            if (($diag[$nro]['aprobcomp'] ?? false) === true) {
                $omit++;

                continue;
            }
            $req = Requisicion::query()->where('numerorequisicion', $nro)->first();
            if ($req === null) {
                $this->warn("   req {$nro}: no está en ERP, no se escribe aprobcomp");

                continue;
            }
            $res = $sync->asegurarSnapshot($req);
            if ($res === RequisicionAnitaAprobcompSyncService::RESULTADO_OMITIDO) {
                $usuAnita = (int) ($diag[$nro]['reqm_usuario'] ?? 0);
                $res = $sync->insertarSnapshotHistorico(
                    $req,
                    $usuAnita,
                    '',
                    (int) ($diag[$nro]['aprobc_fecha'] ?? 0)
                );
            }
            $this->line("   req {$nro}: {$res}");
            if ($res === RequisicionAnitaAprobcompSyncService::RESULTADO_INSERTADO) {
                $ins++;
            } elseif ($res === RequisicionAnitaAprobcompSyncService::RESULTADO_ERROR) {
                $err++;
            } else {
                $omit++;
            }
        }
        $this->info("Listo. OC vinculadas=".($r1['vinculadas'] + $r3['vinculadas'])." req importadas={$r2['importadas']} aprobcomp insertados={$ins} omitidos={$omit} errores={$err}");

        return self::SUCCESS;
    }

    /**
     * @param  list<int>  $ocs
     * @return array<int, int>
     */
    private function paresDesdeAnita(array $ocs): array
    {
        $api = new ApiAnita;
        $in = implode(',', array_map('intval', $ocs));
        $clave = OrdencompraAnitaWhereSupport::claveDesdeConfig();
        $tipo = addslashes($clave['tipo']);
        $letra = addslashes($clave['letra']);
        $suc = (int) $clave['sucursal'];
        $raw = $api->apiCall([
            'acc' => 'list',
            'sistema' => 'compras',
            'tabla' => 'pendmaep',
            'campos' => 'penmp_nro, penmp_requisicion',
            'whereArmado' => " WHERE penmp_tipo='{$tipo}' AND penmp_letra='{$letra}' AND penmp_sucursal={$suc} AND penmp_nro IN ({$in})",
        ]);
        $out = [];
        foreach (ApiAnita::decodificarListaFilas($raw) as $fila) {
            $oc = (int) ($fila->penmp_nro ?? 0);
            $req = (int) ($fila->penmp_requisicion ?? 0);
            if ($oc > 0) {
                $out[$oc] = $req;
            }
        }

        return $out;
    }

    /**
     * @param  list<int>  $nros
     * @return array<int, array{reqmae: bool, reqm_usuario: int, aprobcomp: bool, aprobc_usuario: int, aprobc_fecha: int}>
     */
    private function diagnosticarAnita(array $nros): array
    {
        $api = new ApiAnita;
        $in = implode(',', $nros);
        $out = [];
        foreach ($nros as $n) {
            $out[$n] = [
                'reqmae' => false,
                'reqm_usuario' => 0,
                'aprobcomp' => false,
                'aprobc_usuario' => 0,
                'aprobc_fecha' => 0,
            ];
        }

        $rawMae = $api->apiCall([
            'acc' => 'list',
            'sistema' => 'compras',
            'tabla' => 'reqmae',
            'campos' => 'reqm_nro, reqm_usuario, reqm_estado',
            'whereArmado' => ' WHERE reqm_nro IN ('.$in.')',
        ]);
        foreach (ApiAnita::decodificarListaFilas($rawMae) as $fila) {
            $nro = (int) ($fila->reqm_nro ?? 0);
            if ($nro > 0) {
                $out[$nro]['reqmae'] = true;
                $out[$nro]['reqm_usuario'] = (int) ($fila->reqm_usuario ?? 0);
            }
        }

        $rawAp = $api->apiCall([
            'acc' => 'list',
            'sistema' => 'compras',
            'tabla' => 'aprobcomp',
            'campos' => 'aprobc_nro, aprobc_cod_usuario, aprobc_estado, aprobc_motivo, aprobc_fecha_modif, aprobc_fecha_envio',
            'whereArmado' => " WHERE aprobc_tipo MATCHES 'R*' AND aprobc_nro IN (".$in.')',
        ]);
        foreach (ApiAnita::decodificarListaFilas($rawAp) as $fila) {
            $nro = (int) ($fila->aprobc_nro ?? 0);
            if ($nro <= 0) {
                continue;
            }
            $estado = (int) ($fila->aprobc_estado ?? 0);
            $usuario = (int) ($fila->aprobc_cod_usuario ?? 0);
            $fecha = (int) ($fila->aprobc_fecha_modif ?? $fila->aprobc_fecha_envio ?? 0);
            if (! $out[$nro]['aprobcomp'] || $estado === 3) {
                $out[$nro]['aprobcomp'] = true;
                $out[$nro]['aprobc_usuario'] = $usuario;
                $out[$nro]['aprobc_fecha'] = $fecha;
            }
        }

        return $out;
    }

    /**
     * @param  list<int>  $nros
     * @param  array<int, array{reqmae: bool, reqm_usuario: int, aprobcomp: bool, aprobc_usuario: int, aprobc_fecha: int}>  $diag
     * @return array{creousuario: int, aprobada: int}
     */
    private function enriquecerErpDesdeAnita(array $nros, array $diag, int $usuarioFallback): array
    {
        $codigos = [];
        foreach ($diag as $d) {
            if ((int) $d['reqm_usuario'] > 0) {
                $codigos[] = (int) $d['reqm_usuario'];
            }
            if ((int) $d['aprobc_usuario'] > 0) {
                $codigos[] = (int) $d['aprobc_usuario'];
            }
        }
        $mapa = AnitaUsuarioBridgeSupport::mapaErpIdPorUsuUsuario($codigos);

        $stats = ['creousuario' => 0, 'aprobada' => 0];
        $reqs = Requisicion::query()->whereIn('numerorequisicion', $nros)->get();
        foreach ($reqs as $req) {
            $nro = (int) $req->numerorequisicion;
            $d = $diag[$nro] ?? null;
            if ($d === null) {
                continue;
            }

            $creoAnita = (int) $d['reqm_usuario'];
            $creoErp = $mapa[$creoAnita] ?? 0;
            if ($creoErp > 0 && (int) $req->creousuario_id !== $creoErp) {
                $req->forceFill(['creousuario_id' => $creoErp])->save();
                $stats['creousuario']++;
            }

            $aproAnita = (int) $d['aprobc_usuario'];
            $aproErp = $mapa[$aproAnita] ?? ($creoErp ?: $usuarioFallback);
            $tieneAprobada = Requisicion_Estado::query()
                ->where('requisicion_id', $req->id)
                ->where('estado', 'APROBADA')
                ->exists();
            if (! $tieneAprobada && $d['aprobcomp'] && $aproErp > 0) {
                $fecha = $this->ymdAFecha((int) $d['aprobc_fecha']);
                Requisicion_Estado::query()->create([
                    'requisicion_id' => $req->id,
                    'fecha' => $fecha,
                    'estado' => 'APROBADA',
                    'usuario_id' => $aproErp,
                    'observacion' => 'Aprobación histórica desde Anita aprobcomp (proyección l-proy)',
                ]);
                $stats['aprobada']++;
            }
        }

        return $stats;
    }

    private function ymdAFecha(int $ymd): Carbon
    {
        $s = (string) $ymd;
        if (strlen($s) === 8 && (int) $s >= 19900101) {
            try {
                return Carbon::createFromFormat('Ymd', $s)->startOfDay();
            } catch (\Throwable $e) {
            }
        }

        return now();
    }
}
