<?php

namespace App\Services\Ventas;

use App\Models\Configuracion\Empresa;
use App\Models\Contable\Centrocosto;
use App\Models\Ventas\ViandaConsumo;
use App\Models\Ventas\ViandaTipoMenu;
use App\Models\Ventas\ViandaUsuario;
use App\Support\Ventas\ViandaTipoMenuAnitaBridgeSupport;
use App\Support\Ventas\ViandaUsuarioTipoSupport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ViandaUsuarioAnitaSyncService
{
    /**
     * Recorre los bridges Anita de todas las empresas configuradas (Biyemas/Kandiko/Rebisco)
     * y agrega los resultados. Cada usuario queda asociado a su empresa (empresa_id).
     *
     * @param  list<int>|null  $empresaIds  Empresas a recorrer; null → config('vianda_anita.empresas_sync').
     * @return array{
     *   en_anita:int,
     *   importados:int,
     *   actualizados:int,
     *   omitidos:int,
     *   errores:list<string>,
     *   por_empresa:array<int, array<string, mixed>>
     * }
     */
    public function sincronizarEmpresas(?array $empresaIds = null): array
    {
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $empresaIds = $empresaIds ?? (array) config('vianda_anita.empresas_sync', [1]);
        $empresaIds = array_values(array_unique(array_filter(array_map(
            static fn ($valor): int => (int) $valor,
            $empresaIds
        ), static fn (int $valor): bool => $valor > 0)));

        if ($empresaIds === []) {
            $empresaIds = [(int) config('vianda_anita.empresa_sync', 1)];
        }

        $agg = [
            'en_anita' => 0,
            'importados' => 0,
            'actualizados' => 0,
            'omitidos' => 0,
            'errores' => [],
            'por_empresa' => [],
        ];

        foreach ($empresaIds as $empresaId) {
            try {
                $ret = $this->sincronizarConAnita($empresaId);
            } catch (\Throwable $e) {
                Log::warning("ViandaUsuario sync empresa {$empresaId}: ".$e->getMessage(), ['exception' => $e]);
                $agg['errores'][] = "Empresa {$empresaId}: ".$e->getMessage();
                $agg['por_empresa'][$empresaId] = ['error' => $e->getMessage()];

                continue;
            }

            foreach (['en_anita', 'importados', 'actualizados', 'omitidos'] as $clave) {
                $agg[$clave] += $ret[$clave];
            }
            foreach ($ret['errores'] as $err) {
                $agg['errores'][] = "E{$empresaId}: ".$err;
            }
            $agg['por_empresa'][$empresaId] = $ret;
        }

        if (count($agg['errores']) > 20) {
            $extra = count($agg['errores']) - 20;
            $agg['errores'] = array_merge(
                array_slice($agg['errores'], 0, 20),
                ["… y {$extra} avisos más."]
            );
        }

        return $agg;
    }

    /**
     * @return array{
     *   en_anita:int,
     *   importados:int,
     *   actualizados:int,
     *   omitidos:int,
     *   errores:list<string>
     * }
     */
    public function sincronizarConAnita(?int $empresaId = null): array
    {
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $empresaId = $empresaId ?? (int) config('vianda_anita.empresa_sync', 1);
        if ($empresaId <= 0 || ! Empresa::query()->whereKey($empresaId)->exists()) {
            throw new \InvalidArgumentException("empresa_id {$empresaId} inexistente para sync usuarios vianda.");
        }

        $ret = [
            'en_anita' => 0,
            'importados' => 0,
            'actualizados' => 0,
            'omitidos' => 0,
            'errores' => [],
        ];

        $usuariosAnita = ViandaTipoMenuAnitaBridgeSupport::listarUsuarios($empresaId);
        $ret['en_anita'] = count($usuariosAnita);

        // Mapa de tipos de menú de ESTA empresa (el código Anita se repite entre empresas).
        $mapTipoMenu = ViandaTipoMenu::query()
            ->where('empresa_id', $empresaId)
            ->whereNotNull('codigo_anita')
            ->pluck('id', 'codigo_anita')
            ->all();

        $mapCentrocosto = Centrocosto::query()
            ->pluck('id', 'codigo')
            ->all();

        foreach (array_chunk($usuariosAnita, 200) as $lote) {
            foreach ($lote as $row) {
                $codigoUsuario = (int) ($row->usuv_usuario ?? 0);
                if ($codigoUsuario <= 0) {
                    $ret['omitidos']++;

                    continue;
                }

                $nombre = trim((string) ($row->usuv_nombre ?? ''));
                if ($nombre === '') {
                    $nombre = 'Usuario '.$codigoUsuario;
                }

                $password = trim((string) ($row->usuv_password ?? ''));
                $tipoUsuario = strtoupper(trim((string) ($row->usuv_tipo_usuario ?? 'L')));
                if (! ViandaUsuarioTipoSupport::tipoValido($tipoUsuario)) {
                    $tipoUsuario = 'L';
                }

                $codigoTipoMenu = (int) ($row->usuv_tipo_menu ?? 0);
                $tipoMenuId = $codigoTipoMenu > 0 ? ($mapTipoMenu[$codigoTipoMenu] ?? null) : null;
                if ($codigoTipoMenu > 0 && $tipoMenuId === null) {
                    $ret['errores'][] = "Usuario {$codigoUsuario}: tipo menú Anita {$codigoTipoMenu} no existe en ERP.";
                }

                $codigoCc = trim((string) ($row->usuv_ccosto ?? ''));
                $centrocostoId = null;
                if ($codigoCc !== '' && $codigoCc !== '0' && (int) $codigoCc > 0) {
                    $centrocostoId = $mapCentrocosto[$codigoCc] ?? null;
                    if ($centrocostoId === null) {
                        $centrocostoId = $mapCentrocosto[ltrim($codigoCc, '0')] ?? null;
                    }
                    if ($centrocostoId === null) {
                        $ret['errores'][] = "Usuario {$codigoUsuario}: centro de costo Anita {$codigoCc} no encontrado en ERP.";
                    }
                }

                try {
                    DB::transaction(function () use (
                        $empresaId,
                        $codigoUsuario,
                        $nombre,
                        $password,
                        $tipoUsuario,
                        $tipoMenuId,
                        $centrocostoId,
                        &$ret
                    ) {
                        $usuario = ViandaUsuario::query()
                            ->where('empresa_id', $empresaId)
                            ->where('codigo_usuario', $codigoUsuario)
                            ->first();
                        $payload = [
                            'nombre' => $nombre,
                            'password' => $password !== '' ? $password : (string) $codigoUsuario,
                            'tipo_usuario' => $tipoUsuario,
                            'vianda_tipo_menu_id' => $tipoMenuId,
                            'centrocosto_id' => $centrocostoId,
                        ];

                        if ($usuario === null) {
                            $usuario = ViandaUsuario::create(array_merge($payload, [
                                'codigo_usuario' => $codigoUsuario,
                                'empresa_id' => $empresaId,
                                'estado' => 'A',
                            ]));
                            $ret['importados']++;
                        } else {
                            if ($usuario->estado === 'I') {
                                $ret['omitidos']++;

                                return;
                            }
                            $usuario->update($payload);
                            $ret['actualizados']++;
                        }

                        if ($centrocostoId !== null) {
                            $this->completarCentrocostoConsumosPendientes((int) $usuario->id, (int) $centrocostoId);
                        }
                    });
                } catch (\Throwable $e) {
                    Log::warning('ViandaUsuario sync Anita '.$codigoUsuario.': '.$e->getMessage(), ['exception' => $e]);
                    $ret['errores'][] = 'Usuario Anita '.$codigoUsuario.': '.$e->getMessage();
                    $ret['omitidos']++;
                }
            }
        }

        if (count($ret['errores']) > 20) {
            $extra = count($ret['errores']) - 20;
            $ret['errores'] = array_merge(
                array_slice($ret['errores'], 0, 20),
                ["… y {$extra} avisos más."]
            );
        }

        return $ret;
    }

    /**
     * Consumos marchados antes de que Anita/ERP tuvieran CC en el usuario quedaban con centrocosto_id null.
     */
    private function completarCentrocostoConsumosPendientes(int $viandaUsuarioId, int $centrocostoId): void
    {
        ViandaConsumo::query()
            ->where('vianda_usuario_id', $viandaUsuarioId)
            ->whereNull('centrocosto_id')
            ->update(['centrocosto_id' => $centrocostoId]);
    }
}
