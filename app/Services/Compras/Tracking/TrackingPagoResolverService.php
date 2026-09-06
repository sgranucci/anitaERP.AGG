<?php

namespace App\Services\Compras\Tracking;

use App\ApiAnita;
use App\Models\Compras\Comprobante_Proveedor;
use App\Support\Compras\AnitaImport\ComprobanteProveedorAnitaImportAplmovpSupport as AplmovpSupport;
use App\Support\Compras\Tracking\TrackingPagoEstado;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Estado de pago de un lote de comprobantes, con dos fuentes en cascada:
 *
 *   1. cuenta corriente del ERP (deuda menos aplicaciones),
 *   2. `promov` del Anita, por `prov_nro_interno`.
 *
 * La segunda es la que importa en volumen: la importación trajo los
 * comprobantes históricos sin cuenta corriente, así que sin el puente la
 * búsqueda «sin pagar» sólo vería los comprobantes nativos.
 *
 * La orden de pago que canceló el comprobante sale de `pagoproveedor` cuando el
 * pago es del ERP y de `aplmovp` cuando es histórico.
 */
class TrackingPagoResolverService
{
    /** Tope de números internos por consulta al puente. */
    private const MAX_INTERNOS_POR_CONSULTA = 300;

    /**
     * @param  iterable<Comprobante_Proveedor>  $comprobantes
     * @return array<int, TrackingPagoEstado> indexado por comprobante_proveedor_id
     */
    public function resolverLote(iterable $comprobantes): array
    {
        $comprobantes = $comprobantes instanceof Collection
            ? $comprobantes
            : new Collection(is_array($comprobantes) ? $comprobantes : iterator_to_array($comprobantes));

        if ($comprobantes->isEmpty()) {
            return [];
        }

        $ids = $comprobantes->pluck('id')->map(fn ($id) => (int) $id)->filter()->values()->all();
        $resueltos = $this->resolverEnErp($ids);

        $internosPorId = [];
        foreach ($comprobantes as $comprobante) {
            $id = (int) $comprobante->id;
            $interno = (int) ($comprobante->anita_nro_interno ?? 0);
            if (! isset($resueltos[$id]) && $interno > 0) {
                $internosPorId[$id] = $interno;
            }
        }

        foreach ($this->resolverEnAnita($internosPorId) as $id => $estado) {
            $resueltos[$id] = $estado;
        }

        // La OP de lo importado sale de `aplmovp`. Se pregunta por todo lo que
        // quedó con algo pagado y sin OP del ERP, no sólo por lo que cayó en la
        // rama Anita: la importación le creó cuenta corriente a muchos
        // comprobantes históricos, así que resuelven como ERP y su pago real
        // igual está en el Anita.
        $pendientes = $comprobantes->filter(function ($comprobante) use ($resueltos) {
            $estado = $resueltos[(int) $comprobante->id] ?? null;

            return $estado !== null
                && $estado->opReferencia === null
                && abs($estado->pagado) > 0.01
                && (int) ($comprobante->anita_nro_interno ?? 0) > 0;
        });

        foreach ($this->ordenesPagoAnita($pendientes) as $id => $op) {
            $resueltos[$id] = $resueltos[$id]->conOrdenPago(
                $op['referencia'],
                $op['cantidad'],
                $op['id'] ?? null,
            );
        }

        // Las refs Anita (aplmovp) no traen id ERP: se resuelven contra pagoproveedor
        // importado (OPP/OPA) para que el tracking pueda abrir el link azul.
        $resueltos = $this->completarOpIdsDesdePagoproveedor($resueltos);

        foreach ($ids as $id) {
            $resueltos[$id] ??= TrackingPagoEstado::sinDato();
        }

        return $resueltos;
    }

    public function resolver(Comprobante_Proveedor $comprobante): TrackingPagoEstado
    {
        return $this->resolverLote([$comprobante])[(int) $comprobante->id] ?? TrackingPagoEstado::sinDato();
    }

    /**
     * Saldo del comprobante según la cuenta corriente del ERP.
     *
     * Se replica la fórmula canónica de Proveedor_CuentacorrienteRepository:
     * `cc.total + aplicado` por cuota. Las aplicaciones se guardan con el signo
     * opuesto a la deuda, así que la cancelación se expresa como suma y no como
     * resta. El agregado de aplicaciones va como subconsulta para que el join
     * no multiplique `cc.total` por cada aplicación de la cuota.
     *
     * @param  list<int>  $ids
     * @return array<int, TrackingPagoEstado>
     */
    private function resolverEnErp(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $aplicado = 'coalesce((select sum(a.total) from proveedor_cuentacorriente_aplicacion a'
            .' where a.proveedor_cuentacorriente_id = cc.id), 0)';
        $fechaAplicacion = '(select max(a.fecha) from proveedor_cuentacorriente_aplicacion a'
            .' where a.proveedor_cuentacorriente_id = cc.id)';

        $filas = DB::table('proveedor_cuentacorriente as cc')
            ->whereIn('cc.comprobante_proveedor_id', $ids)
            ->groupBy('cc.comprobante_proveedor_id')
            ->selectRaw('cc.comprobante_proveedor_id as comprobante_id')
            ->selectRaw('sum(cc.total) as monto')
            ->selectRaw('sum(cc.total + '.$aplicado.') as saldo')
            ->selectRaw('max('.$fechaAplicacion.') as fecha_pago')
            ->get();

        $ordenesPago = $this->ordenesPagoErp($ids);

        $out = [];
        foreach ($filas as $fila) {
            $id = (int) $fila->comprobante_id;
            $op = $ordenesPago[$id] ?? null;

            $out[$id] = TrackingPagoEstado::desdeMontos(
                TrackingPagoEstado::ORIGEN_ERP,
                (float) $fila->monto,
                (float) $fila->saldo,
                $fila->fecha_pago !== null ? substr((string) $fila->fecha_pago, 0, 10) : null,
                $op['referencia'] ?? null,
                (int) ($op['cantidad'] ?? 0),
                $op['id'] ?? null,
            );
        }

        return $out;
    }

    /**
     * Órdenes de pago del ERP que cancelaron cada comprobante.
     *
     * El camino es comprobante → cuenta corriente → `pagoproveedor_comprobante`
     * → `pagoproveedor`. Todo local, así que sale en una consulta y no toca el
     * puente. Se queda la OP más reciente para mostrar, más el total, porque un
     * comprobante en cuotas se cancela con varias y en la grilla sólo entra una.
     *
     * @param  list<int>  $ids
     * @return array<int, array{referencia: string, cantidad: int, id: int}>
     */
    private function ordenesPagoErp(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $filas = DB::table('pagoproveedor_comprobante as pc')
            ->join('proveedor_cuentacorriente as cc', 'cc.id', '=', 'pc.proveedor_cuentacorriente_id')
            ->join('pagoproveedor as p', 'p.id', '=', 'pc.pagoproveedor_id')
            ->whereIn('cc.comprobante_proveedor_id', $ids)
            ->orderBy('cc.comprobante_proveedor_id')
            ->orderByDesc('p.fecha')
            ->orderByDesc('p.id')
            ->select(
                'cc.comprobante_proveedor_id as comprobante_id',
                'p.id as pago_id',
                'p.tipocomprobante',
                'p.letra',
                'p.sucursal',
                'p.numerotransaccion',
            )
            ->get();

        $out = [];
        foreach ($filas as $fila) {
            $id = (int) $fila->comprobante_id;

            // La misma OP puede aparecer una vez por cuota: no se cuenta doble.
            $out[$id]['vistas'][(int) $fila->pago_id] = true;

            if (! isset($out[$id]['referencia'])) {
                $out[$id]['referencia'] = self::etiquetaOp(
                    $fila->tipocomprobante,
                    $fila->letra,
                    (int) $fila->sucursal,
                    (int) $fila->numerotransaccion,
                );
                $out[$id]['id'] = (int) $fila->pago_id;
            }
        }

        foreach ($out as $id => $datos) {
            $out[$id] = [
                'referencia' => $datos['referencia'],
                'cantidad' => count($datos['vistas']),
                'id' => $datos['id'],
            ];
        }

        return $out;
    }

    /**
     * Completa `opId` cuando hay etiqueta de OP pero aún no hay FK a `pagoproveedor`.
     *
     * El camino nativo usa `pagoproveedor_comprobante` (casi vacío en lo importado).
     * Tras importar cabeceras OPP/OPA, la etiqueta `OPP A 0001-00123456` alcanza
     * para encontrar el id y armar el link del tracking sin reescribir Anita.
     *
     * @param  array<int, TrackingPagoEstado>  $estados
     * @return array<int, TrackingPagoEstado>
     */
    private function completarOpIdsDesdePagoproveedor(array $estados): array
    {
        $claves = [];
        foreach ($estados as $id => $estado) {
            if (($estado->opId ?? null) !== null && (int) $estado->opId > 0) {
                continue;
            }
            $ref = trim((string) ($estado->opReferencia ?? ''));
            if ($ref === '') {
                continue;
            }
            $parsed = self::parseEtiquetaOp($ref);
            if ($parsed === null) {
                continue;
            }
            $clave = self::clavePagoErp(
                $parsed['tipo'],
                $parsed['letra'],
                $parsed['sucursal'],
                $parsed['numero'],
            );
            $claves[$clave][] = (int) $id;
        }

        if ($claves === []) {
            return $estados;
        }

        $query = DB::table('pagoproveedor')->select([
            'id', 'tipocomprobante', 'letra', 'sucursal', 'numerotransaccion',
        ]);
        $query->where(function ($q) use ($claves) {
            foreach (array_keys($claves) as $clave) {
                [$tipo, $letra, $suc, $nro] = explode('|', $clave);
                $q->orWhere(function ($sub) use ($tipo, $letra, $suc, $nro) {
                    $sub->where('tipocomprobante', $tipo)
                        ->where('letra', $letra)
                        ->where('sucursal', (int) $suc)
                        ->where('numerotransaccion', (int) $nro);
                });
            }
        });

        $mapa = [];
        foreach ($query->get() as $fila) {
            $clave = self::clavePagoErp(
                (string) $fila->tipocomprobante,
                (string) $fila->letra,
                (int) $fila->sucursal,
                (int) $fila->numerotransaccion,
            );
            // Si hubiera duplicados, se queda el de id más bajo (estable).
            if (! isset($mapa[$clave])) {
                $mapa[$clave] = (int) $fila->id;
            }
        }

        // Fallback sin letra: tipo|suc|nro → id solo si hay un único pago.
        $mapaSinLetra = [];
        foreach ($mapa as $clave => $pagoId) {
            [$tipo, , $suc, $nro] = explode('|', $clave);
            $k2 = $tipo.'|'.$suc.'|'.$nro;
            if (! isset($mapaSinLetra[$k2])) {
                $mapaSinLetra[$k2] = $pagoId;
            } else {
                $mapaSinLetra[$k2] = 0; // ambiguo
            }
        }

        foreach ($claves as $clave => $comprobanteIds) {
            $pagoId = $mapa[$clave] ?? 0;
            if ($pagoId <= 0) {
                [$tipo, , $suc, $nro] = explode('|', $clave);
                $pagoId = (int) ($mapaSinLetra[$tipo.'|'.$suc.'|'.$nro] ?? 0);
            }
            if ($pagoId <= 0) {
                continue;
            }
            foreach ($comprobanteIds as $comprobanteId) {
                $estado = $estados[$comprobanteId] ?? null;
                if ($estado === null) {
                    continue;
                }
                $estados[$comprobanteId] = $estado->conOrdenPago(
                    $estado->opReferencia,
                    $estado->opCantidad,
                    $pagoId,
                );
            }
        }

        return $estados;
    }

    /**
     * @return array{tipo: string, letra: string, sucursal: int, numero: int}|null
     */
    private static function parseEtiquetaOp(string $etiqueta): ?array
    {
        $etiqueta = trim(preg_replace('/\s+/', ' ', $etiqueta) ?? '');

        // Anita a veces omite la letra: "OPP  0001-00117622" → se asume A.
        if (preg_match(
            '/^(OPP|OPA|OPV|AOP)\s+([A-Z])\s+(\d+)\s*-\s*(\d+)$/i',
            $etiqueta,
            $m,
        )) {
            return [
                'tipo' => strtoupper($m[1]),
                'letra' => strtoupper($m[2]),
                'sucursal' => (int) $m[3],
                'numero' => (int) $m[4],
            ];
        }

        if (preg_match(
            '/^(OPP|OPA|OPV|AOP)\s+(\d+)\s*-\s*(\d+)$/i',
            $etiqueta,
            $m,
        )) {
            return [
                'tipo' => strtoupper($m[1]),
                'letra' => 'A',
                'sucursal' => (int) $m[2],
                'numero' => (int) $m[3],
            ];
        }

        return null;
    }

    private static function clavePagoErp(string $tipo, string $letra, int $sucursal, int $numero): string
    {
        return strtoupper(trim($tipo)).'|'.strtoupper(trim($letra)).'|'.$sucursal.'|'.$numero;
    }

    /**
     * Etiqueta fiscal de la orden de pago: 'OPA A 0001-00124102'.
     */
    private static function etiquetaOp(
        ?string $tipo,
        ?string $letra,
        int $sucursal,
        int $numero,
    ): string {
        $tipo = strtoupper(trim((string) $tipo));
        $letra = strtoupper(trim((string) $letra));

        return trim(sprintf(
            '%s %s %s-%s',
            $tipo !== '' ? $tipo : 'OP',
            $letra,
            str_pad((string) $sucursal, 4, '0', STR_PAD_LEFT),
            str_pad((string) $numero, 8, '0', STR_PAD_LEFT),
        ));
    }

    /**
     * @param  array<int, int>  $internosPorId  comprobante_id => prov_nro_interno
     * @return array<int, TrackingPagoEstado>
     */
    private function resolverEnAnita(array $internosPorId): array
    {
        if ($internosPorId === []) {
            return [];
        }

        $internos = array_values(array_unique(array_values($internosPorId)));
        $acumulado = [];

        foreach (array_chunk($internos, self::MAX_INTERNOS_POR_CONSULTA) as $chunk) {
            foreach ($this->consultarPromov($chunk) as $fila) {
                $interno = (int) ($fila['prov_nro_interno'] ?? 0);
                if ($interno <= 0) {
                    continue;
                }

                // Una fila por cuota: el estado del comprobante es la suma.
                $acumulado[$interno]['monto'] = ($acumulado[$interno]['monto'] ?? 0.0) + (float) ($fila['prov_monto'] ?? 0);
                $acumulado[$interno]['pagado'] = ($acumulado[$interno]['pagado'] ?? 0.0) + (float) ($fila['prov_t_pagado'] ?? 0);

                $fecha = $this->fechaIso($fila['prov_fecha_pago'] ?? null);
                if ($fecha !== null) {
                    $previa = $acumulado[$interno]['fecha_pago'] ?? null;
                    $acumulado[$interno]['fecha_pago'] = $previa === null || $fecha > $previa ? $fecha : $previa;
                }
            }
        }

        $out = [];
        foreach ($internosPorId as $comprobanteId => $interno) {
            if (! isset($acumulado[$interno])) {
                continue;
            }
            $datos = $acumulado[$interno];
            $monto = (float) ($datos['monto'] ?? 0);
            $out[$comprobanteId] = TrackingPagoEstado::desdeMontos(
                TrackingPagoEstado::ORIGEN_ANITA,
                $monto,
                // En promov `prov_t_pagado` acompaña el signo de `prov_monto`:
                // el saldo es la diferencia y conserva ese signo.
                $monto - (float) ($datos['pagado'] ?? 0),
                $datos['fecha_pago'] ?? null,
            );
        }

        return $out;
    }

    /**
     * @param  list<int>  $internos
     * @return list<array<string, mixed>>
     */
    private function consultarPromov(array $internos): array
    {
        try {
            $raw = (new ApiAnita)->apiCall([
                'acc' => 'list',
                'sistema' => (string) config('comprobante_proveedor.anita_sistema_compras', 'compras'),
                'tabla' => 'promov',
                'campos' => 'prov_nro_interno, prov_nro_cuota, prov_monto, prov_t_pagado, prov_fecha_pago',
                'whereArmado' => ' WHERE prov_nro_interno IN ('.implode(',', $internos).')',
                'orderBy' => 'prov_nro_interno, prov_nro_cuota',
            ]);
        } catch (\Throwable $e) {
            Log::warning('tracking_facturas.promov', ['error' => $e->getMessage()]);

            return [];
        }

        $filas = [];
        foreach (ApiAnita::decodificarListaFilas(is_string($raw) ? $raw : json_encode($raw)) as $fila) {
            $filas[] = (array) $fila;
        }

        return $filas;
    }

    /**
     * Órdenes de pago del Anita que cancelaron cada comprobante importado.
     *
     * `promov.prov_ref_*` no sirve: esos campos los escribe la sincronización
     * del ERP y en el histórico están vacíos en más del noventa por ciento de
     * las cuotas pagadas. El dato real está en `aplmovp`, que aparea el
     * documento de deuda (`aplvp_*`) con el que lo cancela (`aplvp_*_cob`).
     *
     * `aplmovp` no tiene `prov_nro_interno`: se indexa por la identidad fiscal
     * del comprobante. Así que se consulta por número —lo único que entra en un
     * IN— y el apareo fino se hace acá, igual que con `scanfactura`.
     *
     * @param  Collection<int, Comprobante_Proveedor>  $comprobantes
     * @return array<int, array{referencia: string, cantidad: int}>
     */
    private function ordenesPagoAnita(Collection $comprobantes): array
    {
        if ($comprobantes->isEmpty()) {
            return [];
        }

        $comprobantes->loadMissing(['proveedores', 'tipotransaccion_compras']);

        $porClave = [];
        $numeros = [];
        foreach ($comprobantes as $comprobante) {
            $clave = $this->claveAplmovp($comprobante);
            if ($clave === null) {
                continue;
            }
            $porClave[$clave][] = (int) $comprobante->id;
            $numeros[] = (int) $comprobante->numerocomprobante;
        }

        if ($porClave === []) {
            return [];
        }

        $numeros = array_values(array_unique(array_filter($numeros, static fn (int $n) => $n > 0)));
        $acumulado = [];

        foreach (array_chunk($numeros, self::MAX_INTERNOS_POR_CONSULTA) as $chunk) {
            foreach ($this->consultarAplmovp($chunk) as $fila) {
                $clave = $this->armarClaveAplmovp(
                    (string) ($fila['aplvp_proveedor'] ?? ''),
                    (string) ($fila['aplvp_tipo'] ?? ''),
                    (string) ($fila['aplvp_letra'] ?? ''),
                    (int) ($fila['aplvp_sucursal'] ?? 0),
                    (int) ($fila['aplvp_nro'] ?? 0),
                );

                if (! isset($porClave[$clave])) {
                    continue;
                }

                $tipoCredito = strtoupper(trim((string) ($fila['aplvp_tipo_cob'] ?? '')));
                $numeroCredito = (int) ($fila['aplvp_nro_cob'] ?? 0);

                // El crédito puede ser una nota de crédito y no un pago: sólo
                // interesa lo que efectivamente es una orden de pago.
                if ($numeroCredito <= 0 || ! AplmovpSupport::esTipoPago($tipoCredito)) {
                    continue;
                }

                $etiqueta = self::etiquetaOp(
                    $tipoCredito,
                    $fila['aplvp_letra_cob'] ?? null,
                    (int) ($fila['aplvp_sucursal_cob'] ?? 0),
                    $numeroCredito,
                );
                $fecha = (string) $this->fechaIso($fila['aplvp_fecha'] ?? null);

                foreach ($porClave[$clave] as $comprobanteId) {
                    $acumulado[$comprobanteId]['ops'][$etiqueta] = true;
                    // Se muestra la aplicación más reciente: es la que cerró
                    // el comprobante y la que el usuario espera ver.
                    if (($acumulado[$comprobanteId]['fecha'] ?? '') <= $fecha) {
                        $acumulado[$comprobanteId]['referencia'] = $etiqueta;
                        $acumulado[$comprobanteId]['fecha'] = $fecha;
                    }
                }
            }
        }

        $out = [];
        foreach ($acumulado as $comprobanteId => $datos) {
            $out[$comprobanteId] = [
                'referencia' => (string) $datos['referencia'],
                'cantidad' => count($datos['ops']),
            ];
        }

        return $out;
    }

    private function claveAplmovp(Comprobante_Proveedor $comprobante): ?string
    {
        $codigo = (int) ($comprobante->proveedores?->codigo ?? 0);
        $tipo = (string) ($comprobante->tipotransaccion_compras?->abreviatura ?? '');
        $numero = (int) $comprobante->numerocomprobante;

        if ($codigo <= 0 || trim($tipo) === '' || $numero <= 0) {
            return null;
        }

        return $this->armarClaveAplmovp(
            (string) $codigo,
            $tipo,
            (string) $comprobante->letra,
            (int) $comprobante->sucursal,
            $numero,
        );
    }

    private function armarClaveAplmovp(
        string $proveedor,
        string $tipo,
        string $letra,
        int $sucursal,
        int $numero,
    ): string {
        return implode('|', [
            str_pad((string) (int) preg_replace('/\D/', '', $proveedor), 6, '0', STR_PAD_LEFT),
            strtoupper(trim($tipo)),
            strtoupper(trim($letra)),
            $sucursal,
            $numero,
        ]);
    }

    /**
     * @param  list<int>  $numeros
     * @return list<array<string, mixed>>
     */
    private function consultarAplmovp(array $numeros): array
    {
        if ($numeros === []) {
            return [];
        }

        try {
            $raw = (new ApiAnita)->apiCall([
                'acc' => 'list',
                'sistema' => (string) config('comprobante_proveedor.anita_sistema_compras', 'compras'),
                'tabla' => 'aplmovp',
                'campos' => 'aplvp_proveedor, aplvp_tipo, aplvp_letra, aplvp_sucursal, aplvp_nro,'
                    .' aplvp_tipo_cob, aplvp_letra_cob, aplvp_sucursal_cob, aplvp_nro_cob,'
                    .' aplvp_fecha, aplvp_monto',
                'whereArmado' => ' WHERE aplvp_nro IN ('.implode(',', $numeros).')',
                'orderBy' => 'aplvp_fecha',
            ]);
        } catch (\Throwable $e) {
            Log::warning('tracking_facturas.aplmovp', ['error' => $e->getMessage()]);

            return [];
        }

        $filas = [];
        foreach (ApiAnita::decodificarListaFilas(is_string($raw) ? $raw : json_encode($raw)) as $fila) {
            $filas[] = (array) $fila;
        }

        return $filas;
    }

    private function fechaIso(mixed $ymd): ?string
    {
        $digitos = preg_replace('/\D/', '', (string) $ymd) ?? '';
        if (strlen($digitos) !== 8 || str_starts_with($digitos, '0000')) {
            return null;
        }

        return substr($digitos, 0, 4).'-'.substr($digitos, 4, 2).'-'.substr($digitos, 6, 2);
    }
}
