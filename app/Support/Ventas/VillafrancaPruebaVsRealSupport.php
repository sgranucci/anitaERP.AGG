<?php

namespace App\Support\Ventas;

use App\ApiAnita;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Villafranca FAC/NC del ERP: origen exacto A 8 / A 10 (o ausente) y presencia en Anita /usr2/villafranca.
 * Solo lectura.
 */
final class VillafrancaPruebaVsRealSupport
{
    public const PV_PRUEBA_CODIGO = '00008';

    public const PV_REAL_CODIGO = '00010';

    public const PATH_ANITA = PedidoFacturaAnitaArchivosSupport::PATH_VILLAFRANCA;

    public const ORIGEN_8 = 'ORIGEN_8';

    public const ORIGEN_10 = 'ORIGEN_10';

    public const SIN_ORIGEN = 'SIN_ORIGEN';

    /** @var list<string> */
    private const TIPOS_FISCALES = ['FAC', 'FCE', 'NCD', 'NCE', 'NCG', 'NCL', 'NCJ', 'NDR', 'NDE', 'NDB'];

    /** @var list<string> */
    private const TIPOS_NC = ['NCD', 'NCE', 'NCG', 'NCL', 'NCJ'];

    /**
     * @return array{
     *   erp: list<array<string, mixed>>,
     *   en_anita: list<array<string, mixed>>,
     *   resumen_origen: array<string, array{n:int,total:float}>,
     *   resumen_anita: array<string, array{n:int,total:float}>
     * }
     */
    public static function generar(): array
    {
        $erp = self::listarErp();
        self::marcarAnitaBridge($erp);

        $enAnita = array_values(array_filter($erp, static fn (array $f): bool => $f['en_anita_vf'] === 'SI'));

        return [
            'erp' => $erp,
            'en_anita' => $enAnita,
            'resumen_origen' => self::resumir($erp, 'origen'),
            'resumen_anita' => self::resumir($erp, 'en_anita_vf'),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function listarErp(): array
    {
        $idsDivision = PedidoFacturaAnitaArchivosSupport::idsPuntoVentaDivision();
        if ($idsDivision === []) {
            return [];
        }

        $ventas = DB::table('venta as v')
            ->join('tipotransaccion as t', 't.id', '=', 'v.tipotransaccion_id')
            ->join('puntoventa as p', 'p.id', '=', 'v.puntoventa_id')
            ->leftJoin('cliente as c', 'c.id', '=', 'v.cliente_id')
            ->leftJoin('usuario as u', 'u.id', '=', 'v.usuario_id')
            ->whereIn('v.puntoventa_id', $idsDivision)
            ->whereIn('t.abreviatura', self::TIPOS_FISCALES)
            ->select([
                'v.id', 'v.fecha', 'v.codigo', 'v.numerocomprobante', 'v.total', 'v.cae',
                'v.pedido_id', 'v.cliente_id',
                't.abreviatura',
                'p.codigo as pv_codigo',
                'c.codigo as cliente_codigo', 'c.nombre as cliente_nombre',
                'u.usuario as usuario',
            ])
            ->orderBy('v.fecha')
            ->orderBy('p.codigo')
            ->orderBy('t.abreviatura')
            ->orderBy('v.numerocomprobante')
            ->get();

        $origenes = self::cargarOrigenesBierzo($ventas);
        $ncAplicadas = self::facturasAplicadasNc($ventas);

        $filas = [];
        foreach ($ventas as $v) {
            $filas[] = self::armarFilaErp($v, $origenes, $ncAplicadas);
        }

        $porId = [];
        foreach ($filas as $i => $f) {
            $porId[(int) $f['venta_id']] = $i;
        }
        foreach ($filas as $i => $f) {
            if ($f['origen'] !== self::SIN_ORIGEN || (int) ($f['aplica_venta_id'] ?? 0) <= 0) {
                continue;
            }
            $idx = $porId[(int) $f['aplica_venta_id']] ?? null;
            if ($idx === null || $filas[$idx]['origen'] === self::SIN_ORIGEN) {
                continue;
            }
            $filas[$i]['origen'] = $filas[$idx]['origen'];
            $filas[$i]['criterio'] = 'nc_hereda_'.$filas[$idx]['origen'];
            $filas[$i]['origen_id'] = $filas[$idx]['origen_id'] ?: $filas[$idx]['venta_id'];
            $filas[$i]['origen_comprobante'] = $filas[$idx]['origen_comprobante'] ?: $filas[$idx]['comprobante'];
            $filas[$i]['origen_pv'] = $filas[$idx]['origen_pv'] ?: $filas[$idx]['pv'];
        }

        return $filas;
    }

    /**
     * @return list<string>
     */
    public static function headers(): array
    {
        return [
            'venta_id', 'fecha', 'tipo', 'letra', 'comprobante', 'pv', 'numero',
            'cliente_codigo', 'cliente', 'total', 'usuario', 'pedido_id',
            'origen', 'criterio', 'origen_id', 'origen_comprobante', 'origen_pv',
            'aplica_venta_id', 'en_anita_vf', 'anita_clave',
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @return array<string, array{n:int,total:float}>
     */
    public static function resumir(array $filas, string $campo): array
    {
        $out = [];
        foreach ($filas as $f) {
            $k = (string) ($f[$campo] ?? '');
            $out[$k]['n'] = ($out[$k]['n'] ?? 0) + 1;
            $out[$k]['total'] = ($out[$k]['total'] ?? 0.0) + (float) $f['total'];
        }
        ksort($out);

        return $out;
    }

    /**
     * @param  Collection<int, object>  $ventas
     * @return array{por_pedido: Collection, por_nro_tipo: Collection}
     */
    private static function cargarOrigenesBierzo(Collection $ventas): array
    {
        $pedidoIds = $ventas->pluck('pedido_id')->filter()->unique()->values()->all();
        $nros = $ventas->pluck('numerocomprobante')->unique()->values()->all();
        $pvIds = DB::table('puntoventa')
            ->whereIn('codigo', [self::PV_PRUEBA_CODIGO, self::PV_REAL_CODIGO])
            ->pluck('id')
            ->all();

        $porPedido = collect();
        $porNroTipo = collect();
        if ($pvIds === []) {
            return ['por_pedido' => $porPedido, 'por_nro_tipo' => $porNroTipo];
        }

        $base = DB::table('venta as v')
            ->join('tipotransaccion as t', 't.id', '=', 'v.tipotransaccion_id')
            ->join('puntoventa as p', 'p.id', '=', 'v.puntoventa_id')
            ->whereIn('v.puntoventa_id', $pvIds)
            ->whereIn('t.abreviatura', self::TIPOS_FISCALES)
            ->select([
                'v.id', 'v.pedido_id', 'v.cliente_id', 'v.numerocomprobante', 'v.codigo', 'v.fecha',
                'p.codigo as pv_codigo', 't.abreviatura',
            ]);

        if ($pedidoIds !== []) {
            $porPedido = (clone $base)->whereIn('v.pedido_id', $pedidoIds)->get()->groupBy('pedido_id');
        }
        if ($nros !== []) {
            $porNroTipo = (clone $base)
                ->whereIn('v.numerocomprobante', $nros)
                ->get()
                ->groupBy(static fn ($r) => $r->abreviatura.'|'.$r->numerocomprobante);
        }

        return ['por_pedido' => $porPedido, 'por_nro_tipo' => $porNroTipo];
    }

    /**
     * @param  Collection<int, object>  $ventas
     * @return array<int, object>
     */
    private static function facturasAplicadasNc(Collection $ventas): array
    {
        $ncIds = $ventas->whereIn('abreviatura', self::TIPOS_NC)->pluck('id')->all();
        if ($ncIds === []) {
            return [];
        }

        $apps = DB::table('cliente_cuentacorriente_aplicacion as a')
            ->join('cliente_cuentacorriente as cc_nc', 'cc_nc.id', '=', 'a.cliente_cuentacorriente_id')
            ->leftJoin('venta as vf', 'vf.id', '=', 'a.ventaaplicado_id')
            ->whereIn('cc_nc.venta_id', $ncIds)
            ->select([
                'cc_nc.venta_id as nc_id',
                'vf.id as fac_id',
                'vf.codigo as fac_codigo',
                'a.comprobanteaplicado',
            ])
            ->get();

        $out = [];
        foreach ($apps as $app) {
            $out[(int) $app->nc_id] ??= $app;
        }

        return $out;
    }

    /**
     * @param  array{por_pedido: Collection, por_nro_tipo: Collection}  $origenes
     * @param  array<int, object>  $ncAplicadas
     * @return array<string, mixed>
     */
    private static function armarFilaErp(object $v, array $origenes, array $ncAplicadas): array
    {
        $aplicaId = '';
        $aplicaCodigo = '';
        $ncApp = $ncAplicadas[(int) $v->id] ?? null;
        if ($ncApp && $ncApp->fac_id) {
            $aplicaId = (string) $ncApp->fac_id;
            $aplicaCodigo = (string) ($ncApp->fac_codigo ?: $ncApp->comprobanteaplicado);
        }

        $elegido = self::elegirOrigenExacto($v, $origenes);
        $origen = $elegido['origen'];
        $criterio = $elegido['criterio'];
        $clase = $origen !== null
            ? self::claseDesdePv((string) $origen->pv_codigo)
            : self::SIN_ORIGEN;
        if ($clase === self::SIN_ORIGEN) {
            $criterio = $criterio !== '' ? $criterio : 'sin_factura_origen_erp';
        }

        $letra = self::letraDesdeCodigo((string) $v->codigo);

        return [
            'venta_id' => $v->id,
            'fecha' => $v->fecha,
            'tipo' => $v->abreviatura,
            'letra' => $letra,
            'comprobante' => $v->codigo,
            'pv' => $v->pv_codigo,
            'numero' => $v->numerocomprobante,
            'cliente_codigo' => $v->cliente_codigo,
            'cliente' => $v->cliente_nombre,
            'total' => (float) $v->total,
            'usuario' => $v->usuario,
            'pedido_id' => $v->pedido_id,
            'origen' => $clase,
            'criterio' => $criterio,
            'origen_id' => $origen->id ?? '',
            'origen_comprobante' => $origen->codigo ?? $aplicaCodigo,
            'origen_pv' => $origen->pv_codigo ?? '',
            'aplica_venta_id' => $aplicaId,
            'en_anita_vf' => 'NO',
            'anita_clave' => '',
        ];
    }

    /**
     * Origen exacto: 1) mismo pedido + PV 8/10 (prioriza mismo tipo y número);
     * 2) mismo tipo + mismo número en PV 8/10 (la VF copia el número de Bierzo).
     * NC no busca por número (chocaría con FAC/FCE 1).
     *
     * @param  array{por_pedido: Collection, por_nro_tipo: Collection}  $origenes
     * @return array{origen: ?object, criterio: string}
     */
    private static function elegirOrigenExacto(object $v, array $origenes): array
    {
        $esNc = in_array((string) $v->abreviatura, self::TIPOS_NC, true);

        if ($v->pedido_id) {
            $cands = $origenes['por_pedido']->get($v->pedido_id, collect());
            $mismoTipo = $cands->where('abreviatura', $v->abreviatura);
            $mismoNro = $mismoTipo->firstWhere('numerocomprobante', $v->numerocomprobante);
            if ($mismoNro) {
                return ['origen' => $mismoNro, 'criterio' => 'mismo_pedido_tipo_numero'];
            }
            $primeroTipo = $mismoTipo->first();
            if ($primeroTipo) {
                return ['origen' => $primeroTipo, 'criterio' => 'mismo_pedido_tipo'];
            }
            $cualquiera = $cands->first();
            if ($cualquiera) {
                return ['origen' => $cualquiera, 'criterio' => 'mismo_pedido'];
            }
        }

        // PV 15 copia el número de Bierzo. PV 1 (reparto 101) y PV 2 (local) tienen numerador propio.
        if (! $esNc && (int) ltrim((string) $v->pv_codigo, '0') === 15) {
            $cands = $origenes['por_nro_tipo']->get($v->abreviatura.'|'.$v->numerocomprobante, collect());
            $origen = $cands->first();
            if ($origen) {
                return ['origen' => $origen, 'criterio' => 'mismo_tipo_y_numero'];
            }
        }

        return ['origen' => null, 'criterio' => 'sin_factura_origen_erp'];
    }

    /**
     * @param  list<array<string, mixed>>  $erp
     */
    private static function marcarAnitaBridge(array &$erp): void
    {
        $clavesAnita = self::clavesAnitaSucursalesErp();
        foreach ($erp as &$e) {
            $sucursal = (int) ltrim((string) $e['pv'], '0');
            $letra = (string) $e['letra'];
            $k = $e['tipo'].'|'.$letra.'|'.$sucursal.'|'.(int) $e['numero'];
            if (isset($clavesAnita[$k])) {
                $e['en_anita_vf'] = 'SI';
                $e['anita_clave'] = $clavesAnita[$k];
            } else {
                $e['en_anita_vf'] = 'NO';
                $e['anita_clave'] = '';
            }
        }
        unset($e);
    }

    /**
     * Claves venta en /usr2/villafranca para sucursales del ERP (15 y 2).
     *
     * @return array<string, string>
     */
    private static function clavesAnitaSucursalesErp(): array
    {
        $idsDivision = PedidoFacturaAnitaArchivosSupport::idsPuntoVentaDivision();
        $sucursales = self::sucursalesAnitaDesdePuntoventaIds($idsDivision);
        if ($sucursales === []) {
            $sucursales = [15, 2];
        }

        $in = implode(',', $sucursales);
        $api = new ApiAnita();
        $raw = (string) $api->apiCall([
            'acc' => 'list',
            'sistema' => 'ventas',
            'tabla' => 'venta',
            'campos' => 'ven_tipo,ven_letra,ven_sucursal,ven_nro,ven_fecha,ven_monto',
            'whereArmado' => " WHERE ven_sucursal IN ({$in}) ",
            'path_sistema' => self::PATH_ANITA,
        ]);
        $err = ApiAnita::extraerMensajeError($raw === '' ? null : $raw);
        if ($err !== null) {
            throw new \RuntimeException('Anita Villafranca: '.$err);
        }

        $out = [];
        foreach (ApiAnita::decodificarListaFilas($raw) as $r) {
            $tipo = trim((string) $r->ven_tipo);
            $letra = trim((string) $r->ven_letra);
            $suc = (int) $r->ven_sucursal;
            $nro = (int) $r->ven_nro;
            $clave = $tipo.' '.$letra.'-'.str_pad((string) $suc, 5, '0', STR_PAD_LEFT).'-'.str_pad((string) $nro, 8, '0', STR_PAD_LEFT);
            $out[$tipo.'|'.$letra.'|'.$suc.'|'.$nro] = $clave;
        }

        return $out;
    }

    private static function claseDesdePv(string $pvCodigo): string
    {
        $n = (int) ltrim($pvCodigo, '0');
        if ($n === 8) {
            return self::ORIGEN_8;
        }
        if ($n === 10) {
            return self::ORIGEN_10;
        }

        return self::SIN_ORIGEN;
    }

    private static function letraDesdeCodigo(string $codigo): string
    {
        if (preg_match('/^[A-Z]{3}\s+([A-Z])-/', $codigo, $m)) {
            return $m[1];
        }

        return 'A';
    }

    /**
     * @param  list<int>  $ids
     * @return list<int>
     */
    private static function sucursalesAnitaDesdePuntoventaIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $out = [];
        foreach (DB::table('puntoventa')->whereIn('id', $ids)->pluck('codigo') as $codigo) {
            $n = (int) ltrim((string) $codigo, '0');
            if ($n > 0) {
                $out[] = $n;
            }
        }

        return array_values(array_unique($out));
    }
}
