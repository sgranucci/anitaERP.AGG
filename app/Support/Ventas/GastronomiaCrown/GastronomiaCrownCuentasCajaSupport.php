<?php

namespace App\Support\Ventas\GastronomiaCrown;

use App\Models\Caja\Cuentacaja;
use App\Models\Caja\Usocuentacaja;
use App\Support\Ventas\GastronomiaCuentacajaIconoSupport;
use App\Support\Ventas\GastronomiaCuentacajaSoloAutomaticaSupport;
use Illuminate\Support\Facades\Schema;

/**
 * Cuentas de caja del POS Crown (mismo uso «Gastronomia» que AGG) con icono/etiqueta.
 */
final class GastronomiaCrownCuentasCajaSupport
{
    /**
     * @return list<array{
     *   id:int,
     *   codigo:string,
     *   nombre:string,
     *   moneda_id:int,
     *   moneda_abreviatura:?string,
     *   icono:string,
     *   icono_color:string,
     *   etiqueta_boton:string
     * }>
     */
    public static function listarParaEmpresa(int $empresaId): array
    {
        return self::listarParaPos($empresaId, null);
    }

    /**
     * Medios F7: uso Gastronomia + cuentas exclusivas de la sala (mapa master-detail).
     *
     * @return list<array{
     *   id:int,
     *   codigo:string,
     *   nombre:string,
     *   moneda_id:int,
     *   moneda_abreviatura:?string,
     *   icono:string,
     *   icono_color:string,
     *   etiqueta_boton:string
     * }>
     */
    public static function listarParaPos(int $empresaId, ?int $salaId = null): array
    {
        if ($empresaId <= 0) {
            return [];
        }

        $porId = [];
        $usoId = self::usoCuentacajaGastronomiaId();
        if ($usoId !== null) {
            $excluidas = GastronomiaCuentacajaSoloAutomaticaSupport::idsParaEmpresa($empresaId);
            Cuentacaja::query()
                ->paraEmpresa($empresaId)
                ->operativa()
                ->whereHas('usocuentacajas', fn ($r) => $r->whereKey($usoId))
                ->when($excluidas !== [], fn ($q) => $q->whereNotIn('id', $excluidas))
                ->with('monedas:id,abreviatura,nombre')
                ->orderBy('nombre')
                ->get(['id', 'nombre', 'codigo', 'moneda_id'])
                ->each(function (Cuentacaja $c) use (&$porId) {
                    $porId[(int) $c->id] = self::presentar($c);
                });
        }

        foreach (GastronomiaCrownCuentacajaEfectivoSalaSupport::cuentasParaSala($salaId, $empresaId) as $fila) {
            $id = (int) ($fila['id'] ?? 0);
            if ($id <= 0 || isset($porId[$id])) {
                continue;
            }
            $presentacion = GastronomiaCuentacajaIconoSupport::presentacion(
                (string) $fila['nombre'],
                (string) $fila['codigo'],
            );
            $porId[$id] = [
                'id' => $id,
                'codigo' => (string) $fila['codigo'],
                'nombre' => (string) $fila['nombre'],
                'moneda_id' => (int) ($fila['moneda_id'] ?? 0),
                'moneda_abreviatura' => $fila['moneda_abreviatura'] ?? null,
                'icono' => $presentacion['icono'],
                'icono_color' => $presentacion['color'],
                'etiqueta_boton' => $presentacion['etiqueta_boton'],
            ];
        }

        return array_values($porId);
    }

    /**
     * @return array{
     *   id:int,
     *   codigo:string,
     *   nombre:string,
     *   moneda_id:int,
     *   moneda_abreviatura:?string,
     *   icono:string,
     *   icono_color:string,
     *   etiqueta_boton:string
     * }
     */
    private static function presentar(Cuentacaja $c): array
    {
        $presentacion = GastronomiaCuentacajaIconoSupport::presentacion(
            (string) $c->nombre,
            (string) $c->codigo,
        );

        return [
            'id' => (int) $c->id,
            'codigo' => (string) $c->codigo,
            'nombre' => (string) $c->nombre,
            'moneda_id' => (int) ($c->moneda_id ?? 0),
            'moneda_abreviatura' => $c->monedas->abreviatura ?? null,
            'icono' => $presentacion['icono'],
            'icono_color' => $presentacion['color'],
            'etiqueta_boton' => $presentacion['etiqueta_boton'],
        ];
    }

    /**
     * Efectivo del POS Crown: por sala (no por empresa AGG).
     *
     * @return array{id:int,nombre:string,codigo:string,moneda_id:int,moneda_abreviatura:?string,sala_id:int}|null
     */
    public static function efectivoParaSala(?int $salaId, int $empresaId = 0): ?array
    {
        return GastronomiaCrownCuentacajaEfectivoSalaSupport::cuentaParaSala($salaId, $empresaId);
    }

    public static function usoCuentacajaGastronomiaId(): ?int
    {
        $configured = config('gastronomia.usocuentacaja_id');
        if ($configured !== null && $configured !== '') {
            return (int) $configured;
        }

        if (! Schema::hasTable('usocuentacaja')) {
            return null;
        }

        $id = Usocuentacaja::query()->where('nombre', 'Gastronomia')->value('id');

        return $id ? (int) $id : null;
    }
}
