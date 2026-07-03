<?php

namespace App\Services\Ventas;

use App\Models\Configuracion\Empresa;
use App\Models\Stock\Articulo;
use App\Models\Ventas\ViandaTipoMenu;
use App\Models\Ventas\ViandaTipoMenuArticulo;
use App\Services\Stock\ArticuloAnitaSyncService;
use App\Support\Stock\ArticuloSkuMatchSupport;
use App\Support\Ventas\ViandaDiaSemanaSupport;
use App\Support\Ventas\ViandaTipoMenuAnitaBridgeSupport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ViandaTipoMenuAnitaSyncService
{
    /**
     * @return array{
     *   en_anita:int,
     *   importados:int,
     *   actualizados:int,
     *   omitidos:int,
     *   articulos_lineas:int,
     *   errores:list<string>
     * }
     */
    public function sincronizarConAnita(?int $empresaId = null): array
    {
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $empresaId = $empresaId ?? (int) config('vianda_anita.empresa_sync', 1);
        if ($empresaId <= 0 || ! Empresa::query()->whereKey($empresaId)->exists()) {
            throw new \InvalidArgumentException("empresa_id {$empresaId} inexistente para sync viandas.");
        }

        return $this->sincronizarEmpresa($empresaId);
    }

    /**
     * @return array{en_anita:int, importados:int, actualizados:int, omitidos:int, articulos_lineas:int, errores:list<string>}
     */
    private function sincronizarEmpresa(int $empresaId): array
    {
        $ret = [
            'en_anita' => 0,
            'importados' => 0,
            'actualizados' => 0,
            'omitidos' => 0,
            'articulos_lineas' => 0,
            'errores' => [],
        ];

        $tiposAnita = ViandaTipoMenuAnitaBridgeSupport::listarTiposMenu($empresaId);
        $articulosAnita = ViandaTipoMenuAnitaBridgeSupport::listarArticulos($empresaId);

        $ret['en_anita'] = count($tiposAnita);

        $articulosPorTipo = [];
        foreach ($articulosAnita as $row) {
            $codigoTipo = (int) ($row->artm_codigo ?? 0);
            if ($codigoTipo <= 0) {
                continue;
            }
            $articulosPorTipo[$codigoTipo][] = $row;
        }

        foreach ($tiposAnita as $row) {
            $codigoAnita = (int) ($row->tipom_codigo ?? 0);
            if ($codigoAnita <= 0) {
                $ret['omitidos']++;
                continue;
            }

            $nombre = trim((string) ($row->tipom_desc ?? ''));
            if ($nombre === '') {
                $nombre = 'Tipo menú '.$codigoAnita;
            }

            try {
                DB::transaction(function () use ($codigoAnita, $nombre, $articulosPorTipo, $empresaId, &$ret) {
                    $tipoMenu = ViandaTipoMenu::query()->where('codigo_anita', $codigoAnita)->first();
                    $esNuevo = $tipoMenu === null;

                    if ($esNuevo) {
                        $tipoMenu = ViandaTipoMenu::create([
                            'codigo_anita' => $codigoAnita,
                            'nombre' => $nombre,
                            'estado' => 'A',
                        ]);
                        $ret['importados']++;
                    } else {
                        $tipoMenu->update([
                            'nombre' => $nombre,
                        ]);
                        $ret['actualizados']++;
                    }

                    ViandaTipoMenuArticulo::query()
                        ->where('vianda_tipo_menu_id', $tipoMenu->id)
                        ->delete();

                    $lineasTipo = $articulosPorTipo[$codigoAnita] ?? [];
                    $ordenPorDia = [];

                    foreach ($lineasTipo as $lineaAnita) {
                        $dia = (int) ($lineaAnita->artm_dia ?? 0);
                        if (! ViandaDiaSemanaSupport::diaValido($dia)) {
                            continue;
                        }

                        $sku = trim((string) ($lineaAnita->artm_articulo ?? ''));
                        if ($sku === '') {
                            continue;
                        }

                        $articulo = $this->resolverArticuloPorSkuAnita($sku, $empresaId);
                        if ($articulo === null) {
                            $ret['errores'][] = "Tipo {$codigoAnita} día {$dia}: SKU «{$sku}» no encontrado en ERP.";

                            continue;
                        }

                        $ordenPorDia[$dia] = ($ordenPorDia[$dia] ?? 0) + 1;
                        ViandaTipoMenuArticulo::create([
                            'vianda_tipo_menu_id' => $tipoMenu->id,
                            'dia_semana' => $dia,
                            'articulo_id' => (int) $articulo->id,
                            'orden' => $ordenPorDia[$dia],
                        ]);
                        $ret['articulos_lineas']++;
                    }

                });
            } catch (\Throwable $e) {
                Log::warning('ViandaTipoMenu sync Anita tipo '.$codigoAnita.': '.$e->getMessage(), ['exception' => $e]);
                $ret['errores'][] = 'Tipo Anita '.$codigoAnita.': '.$e->getMessage();
                $ret['omitidos']++;
            }
        }

        return $ret;
    }

    private function resolverArticuloPorSkuAnita(string $sku, int $empresaIdBridge): ?Articulo
    {
        $sku = trim($sku);
        if ($sku === '') {
            return null;
        }

        $variantes = array_values(array_unique(array_filter([
            $sku,
            ltrim($sku, '0'),
            ArticuloSkuMatchSupport::normalizar($sku),
            ArticuloSkuMatchSupport::normalizar(ltrim($sku, '0')),
        ])));

        foreach ($variantes as $candidato) {
            $canonico = ArticuloSkuMatchSupport::resolverCanonico($candidato);
            if ($canonico !== null) {
                return $canonico;
            }
        }

        foreach ($variantes as $candidato) {
            try {
                app(ArticuloAnitaSyncService::class)->sincronizarSkuDesdeAnita($candidato, $empresaIdBridge);
            } catch (\Throwable) {
                continue;
            }

            $canonico = ArticuloSkuMatchSupport::resolverCanonico($candidato);
            if ($canonico !== null) {
                return $canonico;
            }
        }

        return null;
    }
}
