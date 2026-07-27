<?php

namespace App\Console\Commands;

use App\ApiAnita;
use App\Models\Seguridad\Usuario;
use App\Models\Solicitudpago\Solicitudpago;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Backfill solicitudpago.centrocosto_id:
 * Anita solpagomae.solpm_usuario_umod → Anita usuario.usu_usuario/usu_logname
 * → ERP usuario.usuario → usuario.centrocosto_id.
 *
 * El DDL de referencia Anita está en /home/sergio/tmp/usuario.sql (usu_usuario, usu_logname).
 */
class SolicitudpagoSeedCentrocostoCabecera extends Command
{
    protected $signature = 'solicitudpago:seed-centrocosto-cabecera
                            {--dry-run : Solo reportar, no actualizar}
                            {--solo-nulos : Solo filas con centrocosto_id null}';

    protected $description = 'Seed centrocosto_id de cabecera SP desde Anita solpm_usuario_umod + usuario Anita';

    public function handle(): int
    {
        if (! Schema::hasColumn('solicitudpago', 'centrocosto_id')) {
            $this->error('Falta columna solicitudpago.centrocosto_id. Correr la migración primero.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $soloNulos = (bool) $this->option('solo-nulos');

        $this->info('Leyendo usuarios Anita (usu_usuario, usu_logname)…');
        $mapaAnitaAErp = $this->mapaAnitaUsuarioAErp();
        $this->info('Usuarios Anita mapeados a ERP: '.count($mapaAnitaAErp));

        $ccPorUsuarioErp = Usuario::query()
            ->whereNotNull('centrocosto_id')
            ->where('centrocosto_id', '>', 0)
            ->pluck('centrocosto_id', 'id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $ccPorAnita = [];
        foreach ($mapaAnitaAErp as $anitaId => $erpId) {
            $cc = $ccPorUsuarioErp[$erpId] ?? null;
            if ($cc !== null && $cc > 0) {
                $ccPorAnita[(int) $anitaId] = $cc;
            }
        }
        $this->info('Anita usuarios con CC ERP: '.count($ccPorAnita));

        $this->info('Leyendo solpagomae (solpm_id, solpm_usuario_umod)…');
        $filas = $this->listarSolpagomae();
        $this->info('Filas Anita: '.count($filas));

        $porCodigo = Solicitudpago::query()->pluck('id', 'codigo')->all();
        $actualizados = 0;
        $sinMatch = 0;
        $sinSp = 0;
        $omitidos = 0;

        $updates = [];
        foreach ($filas as $row) {
            $codigo = (int) ($row->solpm_id ?? 0);
            $anitaUmod = (int) ($row->solpm_usuario_umod ?? 0);
            if ($codigo <= 0) {
                continue;
            }
            $spId = $porCodigo[$codigo] ?? null;
            if ($spId === null) {
                $sinSp++;

                continue;
            }

            $ccId = $anitaUmod > 0 ? ($ccPorAnita[$anitaUmod] ?? null) : null;
            if ($ccId === null || $ccId <= 0) {
                $sinMatch++;

                continue;
            }

            $updates[(int) $spId] = $ccId;
        }

        // Fallback: SP ya con usuario_umod_id ERP pero sin match Anita
        $qFallback = Solicitudpago::query()
            ->whereNotNull('usuario_umod_id')
            ->where('usuario_umod_id', '>', 0);
        if ($soloNulos) {
            $qFallback->whereNull('centrocosto_id');
        }
        foreach ($qFallback->get(['id', 'usuario_umod_id', 'centrocosto_id']) as $sp) {
            $spId = (int) $sp->id;
            if (isset($updates[$spId])) {
                continue;
            }
            if ($soloNulos && $sp->centrocosto_id) {
                $omitidos++;

                continue;
            }
            $cc = $ccPorUsuarioErp[(int) $sp->usuario_umod_id] ?? null;
            if ($cc !== null && $cc > 0) {
                $updates[$spId] = $cc;
            }
        }

        $this->info('SP a actualizar: '.count($updates)." (dry-run=".($dryRun ? 'sí' : 'no').')');

        if (! $dryRun && $updates !== []) {
            DB::transaction(function () use ($updates, $soloNulos, &$actualizados, &$omitidos) {
                foreach (array_chunk($updates, 500, true) as $chunk) {
                    foreach ($chunk as $spId => $ccId) {
                        $q = DB::table('solicitudpago')->where('id', $spId);
                        if ($soloNulos) {
                            $q->whereNull('centrocosto_id');
                        }
                        $n = $q->update([
                            'centrocosto_id' => $ccId,
                            'updated_at' => now(),
                        ]);
                        if ($n > 0) {
                            $actualizados += $n;
                        } else {
                            $omitidos++;
                        }
                    }
                }
            });
        } elseif ($dryRun) {
            $actualizados = count($updates);
        }

        $this->table(
            ['Métrica', 'Valor'],
            [
                ['Actualizados / previstos', $actualizados],
                ['Anita sin SP en ERP', $sinSp],
                ['Anita sin match CC', $sinMatch],
                ['Omitidos (ya tenían CC)', $omitidos],
                ['Quedan null', Solicitudpago::query()->whereNull('centrocosto_id')->count()],
            ]
        );

        return self::SUCCESS;
    }

    /**
     * @return array<int, int> Anita usu_usuario => ERP usuario.id
     */
    private function mapaAnitaUsuarioAErp(): array
    {
        $api = new ApiAnita();
        $parsed = ApiAnita::parsearRespuestaLista($api->apiCall([
            'acc' => 'list',
            'sistema' => 'compras',
            'tabla' => 'usuario',
            'campos' => 'usu_usuario, usu_logname',
        ]));

        if ($parsed['error_lectura'] !== null) {
            Log::warning('solicitudpago.seed_cc.usuario_anita', ['error' => $parsed['error_lectura']]);
            $this->warn('Error lectura usuario Anita: '.$parsed['error_lectura']);
        }

        $porLogname = Usuario::query()->get(['id', 'usuario'])
            ->keyBy(fn (Usuario $u) => mb_strtolower(trim((string) $u->usuario)));

        $mapa = [];
        foreach ($parsed['filas'] as $fila) {
            $anitaId = (int) ($fila->usu_usuario ?? 0);
            $logname = mb_strtolower(trim((string) ($fila->usu_logname ?? '')));
            if ($anitaId <= 0 || $logname === '') {
                continue;
            }
            $erp = $porLogname->get($logname);
            if ($erp) {
                $mapa[$anitaId] = (int) $erp->id;
            }
        }

        return $mapa;
    }

    /** @return list<object> */
    private function listarSolpagomae(): array
    {
        $api = new ApiAnita();
        $sistema = (string) config('solicitudpago.anita_sistema', 'che_ban');
        $parsed = ApiAnita::parsearRespuestaLista($api->apiCall([
            'acc' => 'list',
            'sistema' => $sistema,
            'tabla' => 'solpagomae',
            'campos' => 'solpm_id, solpm_usuario_umod',
        ]));

        if ($parsed['error_lectura'] !== null) {
            Log::warning('solicitudpago.seed_cc.solpagomae', ['error' => $parsed['error_lectura']]);
            $this->warn('Error lectura solpagomae: '.$parsed['error_lectura']);
        }

        return $parsed['filas'];
    }
}
