<?php

namespace App\Services\Ventas;

use App\Models\Contable\Centrocosto;
use App\Models\Ventas\ViandaTipoMenu;
use App\Models\Ventas\ViandaUsuario;
use App\Support\Ventas\ViandaTipoMenuAnitaBridgeSupport;
use App\Support\Ventas\ViandaUsuarioTipoSupport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ViandaUsuarioAnitaSyncService
{
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

        $ret = [
            'en_anita' => 0,
            'importados' => 0,
            'actualizados' => 0,
            'omitidos' => 0,
            'errores' => [],
        ];

        $usuariosAnita = ViandaTipoMenuAnitaBridgeSupport::listarUsuarios($empresaId);
        $ret['en_anita'] = count($usuariosAnita);

        $mapTipoMenu = ViandaTipoMenu::query()
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
                        $codigoUsuario,
                        $nombre,
                        $password,
                        $tipoUsuario,
                        $tipoMenuId,
                        $centrocostoId,
                        &$ret
                    ) {
                        $usuario = ViandaUsuario::query()->where('codigo_usuario', $codigoUsuario)->first();
                        $payload = [
                            'nombre' => $nombre,
                            'password' => $password !== '' ? $password : (string) $codigoUsuario,
                            'tipo_usuario' => $tipoUsuario,
                            'vianda_tipo_menu_id' => $tipoMenuId,
                            'centrocosto_id' => $centrocostoId,
                        ];

                        if ($usuario === null) {
                            ViandaUsuario::create(array_merge($payload, [
                                'codigo_usuario' => $codigoUsuario,
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
}
