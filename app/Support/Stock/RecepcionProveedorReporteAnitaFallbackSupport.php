<?php

namespace App\Support\Stock;

use App\ApiAnita;
use App\Models\Compras\Ordencompra;
use App\Models\Compras\Proveedor;
use App\Models\Compras\Requisicion;
use App\Models\Configuracion\Empresa;
use App\Models\Seguridad\Usuario;
use App\Models\Stock\Recepcion_Proveedor;
use App\Support\Compras\AnitaSync\Requisicion\AnitaSqlLiteral;
use App\Support\Compras\AnitaSync\Requisicion\RequisicionAnitaAprobcompMapper;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Histórico COM Anita (antes del primer COM ERP) en una sola lectura Informix.
 *
 * FROM recepmov + recepmae + OUTER (pendmaep, OUTER (reqmae, OUTER aprobcomp)).
 * Informix no admite un 4.º OUTER a usuario: el pedidor llega en reqm_usuario
 * y el autorizante en aprobc_usuario; los nombres se resuelven en MySQL.
 */
final class RecepcionProveedorReporteAnitaFallbackSupport
{
    public const FUENTE = 'anita';

    /**
     * Primer día con COM en ERP: el fallback Anita cubre desde/hasta estrictamente anteriores.
     */
    public static function fechaCorteErp(): string
    {
        $cfg = trim((string) config('recepcion_proveedor.reporte.anita_corte_erp', ''));
        if ($cfg !== '') {
            try {
                return Carbon::parse($cfg)->toDateString();
            } catch (\Throwable) {
                // sigue al mínimo de tabla
            }
        }

        $min = DB::table('recepcion_proveedor')->min('fecha');
        if ($min) {
            try {
                return Carbon::parse((string) $min)->toDateString();
            } catch (\Throwable) {
                // fallback fijo
            }
        }

        return '2025-01-01';
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array{desde: int, hasta: int}|null
     */
    public static function rangoAnita(array $filtros, ?string $corteYmd = null): ?array
    {
        $corte = $corteYmd ?? self::fechaCorteErp();
        $desde = trim((string) ($filtros['fecha_desde'] ?? ''));
        if ($desde === '') {
            return null;
        }

        $hasta = trim((string) ($filtros['fecha_hasta'] ?? ''));
        if ($hasta === '') {
            $hasta = date('Y-m-d');
        }

        if ($desde >= $corte) {
            return null;
        }

        $hastaAnita = $hasta < $corte ? $hasta : Carbon::parse($corte)->subDay()->toDateString();
        if ($hastaAnita < $desde) {
            return null;
        }

        return [
            'desde' => (int) str_replace('-', '', $desde),
            'hasta' => (int) str_replace('-', '', $hastaAnita),
        ];
    }

    /**
     * @return list<string>
     */
    public static function camposSelect(): array
    {
        return [
            'recv_articulo as sku',
            'recv_desc as descripcion',
            'recv_cantidad as cantidad',
            'recv_precio as precio',
            'recv_dto_art as descuento',
            'recv_cantrech as cant_rech',
            'recv_unidad_medida as um',
            'recv_ccosto as ccosto',
            'recv_deposito as deposito',
            'recv_orden as linea_orden',
            'recv_cod_mon as cod_mon',
            'recv_cotizacion as cotizacion',
            'recv_motivo_rech as motivo_rech',
            'recv_agrupacion as agrupacion',
            'recm_proveedor as codigo_proveedor',
            'recm_tipo as recm_tipo',
            'recm_letra as recm_letra',
            'recm_sucursal as recm_sucursal',
            'recm_nro as recm_nro',
            'recm_fecha as recm_fecha',
            'recm_estado as recm_estado',
            'recm_usuario as recm_usuario',
            'recm_empresa as recm_empresa',
            'recm_observacion as observacion',
            'recm_com_nro as com_nro',
            'recm_nro_fac as nro_fac',
            'recm_tipo_fac as tipo_fac',
            'recm_letra_fac as letra_fac',
            'recm_sucursal_fac as sucursal_fac',
            'penmp_nro as nro_oc',
            'penmp_fecha as fecha_oc',
            'penmp_requisicion as nro_req',
            'reqm_nro as reqm_nro',
            'reqm_fecha as fecha_req',
            'reqm_ccosto_dest as cc_dest',
            'reqm_usuario as reqm_usuario',
            'aprobc_usuario as autorizante_anita',
            'aprobc_cod_usuario as autorizante_codigo',
            'aprobc_estado as aprobc_estado',
            'aprobc_tipo as aprobc_tipo',
            'aprobc_fecha_modif as fecha_aprob',
        ];
    }

    public static function camposCsv(): string
    {
        return implode(', ', self::camposSelect());
    }

    public static function tablaFrom(): string
    {
        // Paréntesis Informix: sin ellos, AND sobre reqmae/aprobcomp elimina el COM.
        // Informix no acepta 4 OUTER anidados; usuario se omite (el nombre del autorizante
        // ya viene en aprobc_usuario). El pedidor queda en reqm_usuario.
        return 'recepmov, recepmae, OUTER (pendmaep, OUTER (reqmae, OUTER aprobcomp))';
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @param  array{desde: int, hasta: int}  $rango
     * @param  list<int>  $sucursales
     */
    public static function whereArmado(array $filtros, array $rango, array $sucursales): string
    {
        $ocTipo = AnitaSqlLiteral::string(
            (string) config('recepcion_proveedor.anita.oc_tipo', 'PEP'),
            3
        );
        $ocLetra = AnitaSqlLiteral::char((string) config('recepcion_proveedor.anita.oc_letra', 'X'), 1);
        $ocSucursal = (int) config('recepcion_proveedor.anita.oc_sucursal', 0);
        $tipoCom = AnitaSqlLiteral::string(
            (string) config('recepcion_proveedor.anita.recepcion_tipo', 'COM'),
            3
        );
        $letraCom = AnitaSqlLiteral::char(
            (string) config('recepcion_proveedor.anita.recepcion_letra', 'X'),
            1
        );

        $where = ' WHERE recm_tipo = recv_tipo'
            .' AND recm_letra = recv_letra'
            .' AND recm_sucursal = recv_sucursal'
            .' AND recm_nro = recv_nro'
            .' AND recm_tipo = '.$tipoCom
            .' AND recm_letra = '.$letraCom
            .' AND recm_fecha >= '.(int) $rango['desde']
            .' AND recm_fecha <= '.(int) $rango['hasta']
            .' AND penmp_tipo = '.$ocTipo
            .' AND penmp_letra = '.$ocLetra
            .' AND penmp_sucursal = '.$ocSucursal
            // Histórico: el PEP vive en recm_nro_fac (l-recprov / aplicped).
            // recm_com_nro suele ser 0 o un nro chico; el OR con com_nro multiplica el OUTER.
            .' AND penmp_nro = recm_nro_fac'
            .' AND reqm_nro = penmp_requisicion'
            // l-proy busca_aprobacion("REQ"): sin el tipo, un PEP posterior
            // con el mismo número (COM 109755 / REQ 184935) pisa el autorizante.
            .' AND aprobc_nro = reqm_nro'
            ." AND aprobc_tipo MATCHES 'R*'";

        $estado = (string) ($filtros['estado'] ?? RecepcionProveedorReporteFiltros::ESTADO_CONFIRMADA);
        $estadoConf = AnitaSqlLiteral::char(
            (string) config('recepcion_proveedor.anita.recepcion_estado_confirmada', '2'),
            1
        );
        $estadoAnul = AnitaSqlLiteral::char(
            (string) config('recepcion_proveedor.anita.recepcion_estado_anulada', '3'),
            1
        );
        if ($estado === RecepcionProveedorReporteFiltros::ESTADO_CONFIRMADA) {
            $where .= ' AND recm_estado <> '.$estadoAnul;
        } elseif ($estado === RecepcionProveedorReporteFiltros::ESTADO_ANULADA) {
            $where .= ' AND recm_estado = '.$estadoAnul;
        } elseif ($estado === RecepcionProveedorReporteFiltros::ESTADO_BORRADOR) {
            $where .= ' AND recm_estado <> '.$estadoConf.' AND recm_estado <> '.$estadoAnul;
        }

        if ($sucursales !== []) {
            $where .= ' AND recm_sucursal IN ('.implode(',', array_map('intval', $sucursales)).')';
        }

        $proveedor = trim((string) ($filtros['proveedor'] ?? ''));
        if ($proveedor !== '') {
            $like = self::matchesInformix($proveedor);
            $where .= ' AND (recm_proveedor MATCHES '.$like
                .' OR recm_proveedor = '.AnitaSqlLiteral::string(str_pad(ltrim($proveedor, '0') ?: '0', 6, '0', STR_PAD_LEFT), 6).')';
        }

        $sku = trim((string) ($filtros['sku'] ?? ''));
        if ($sku !== '') {
            $like = self::matchesInformix($sku);
            $where .= ' AND (recv_articulo MATCHES '.$like.' OR recv_desc MATCHES '.$like.')';
        }

        $deposito = trim((string) ($filtros['deposito'] ?? ''));
        if ($deposito !== '' && ctype_digit($deposito)) {
            $where .= ' AND recv_deposito = '.(int) $deposito;
        }

        if (! empty($filtros['solo_rechazadas'])) {
            $where .= ' AND recv_cantrech > 0';
        }

        return $where;
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function correspondeFallback(array $filtros, ?string $corteYmd = null): bool
    {
        if (self::rangoAnita($filtros, $corteYmd) === null) {
            return false;
        }

        $tipo = (string) ($filtros['tipo'] ?? RecepcionProveedorReporteFiltros::TIPO_TODAS);
        if ($tipo === RecepcionProveedorReporteFiltros::TIPO_DEVOLUCION) {
            return false;
        }

        $facturacion = (string) ($filtros['facturacion'] ?? RecepcionProveedorReporteFiltros::FACTURACION_TODAS);
        if ($facturacion === RecepcionProveedorReporteFiltros::FACTURACION_FACTURADAS) {
            return false;
        }

        if (! empty($filtros['solo_diferencias'])) {
            return false;
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array{filas: Collection<int, array<string, mixed>>, error: ?string}
     */
    public static function consultar(array $filtros, array $sucursales = []): array
    {
        $vacio = ['filas' => collect(), 'error' => null];
        if (! self::correspondeFallback($filtros)) {
            return $vacio;
        }

        $rango = self::rangoAnita($filtros);
        if ($rango === null) {
            return $vacio;
        }

        $api = new ApiAnita;
        $payload = [
            'acc' => 'list',
            'sistema' => (string) config('recepcion_proveedor.anita.sistema_compras', 'compras'),
            'tabla' => self::tablaFrom(),
            'campos' => self::camposCsv(),
            'whereArmado' => self::whereArmado($filtros, $rango, $sucursales),
            'orderBy' => 'recm_fecha, recm_sucursal, recm_nro, recv_orden',
        ];

        $raw = (string) $api->apiCall($payload);
        $err = ApiAnita::extraerMensajeError($raw === '' ? null : $raw);
        if ($err !== null) {
            Log::warning('RecepcionProveedorReporteAnita: fallo lectura única', [
                'error' => $err,
                'rango' => $rango,
            ]);

            return ['filas' => collect(), 'error' => $err];
        }

        $filas = self::deduplicarAprobado(ApiAnita::decodificarListaFilas($raw));
        $enriquecidas = self::enriquecerLocal($filas);

        return [
            'filas' => collect($enriquecidas)->map(fn (object $row) => self::mapearFila($row))->values(),
            'error' => null,
        ];
    }

    /**
     * Si aprobcomp tiene más de un Aprobado, Informix puede repetir la línea COM.
     * Solo cuenta tipo REQ (un PEP con el mismo nro no es el autorizante de la requi).
     *
     * @param  list<object>  $filas
     * @return list<object>
     */
    public static function deduplicarAprobado(array $filas): array
    {
        $porClave = [];
        foreach ($filas as $fila) {
            $suc = (int) ($fila->recm_sucursal ?? 0);
            $nro = (int) ($fila->recm_nro ?? 0);
            $orden = (int) ($fila->linea_orden ?? 0);
            $clave = $suc.'|'.$nro.'|'.$orden;
            $prev = $porClave[$clave] ?? null;
            if ($prev === null || self::puntajeAprobacionReq($fila) > self::puntajeAprobacionReq($prev)) {
                $porClave[$clave] = self::sanearAprobacionSiNoEsReq($fila);
            }
        }

        return array_values($porClave);
    }

    public static function esAprobacionRequisicion(?string $tipo): bool
    {
        $tipo = strtoupper(trim((string) $tipo));

        return $tipo !== '' && str_starts_with($tipo, 'R');
    }

    private static function puntajeAprobacionReq(object $fila): int
    {
        if (! self::esAprobacionRequisicion((string) ($fila->aprobc_tipo ?? ''))) {
            return 0;
        }

        return (int) ($fila->aprobc_estado ?? 0) === RequisicionAnitaAprobcompMapper::ESTADO_APROBADO
            ? 2
            : 1;
    }

    private static function sanearAprobacionSiNoEsReq(object $fila): object
    {
        if (self::esAprobacionRequisicion((string) ($fila->aprobc_tipo ?? ''))) {
            return $fila;
        }

        $fila->autorizante_anita = '';
        $fila->autorizante_codigo = '';
        $fila->aprobc_estado = '';

        return $fila;
    }

    /**
     * @return array<string, mixed>
     */
    public static function mapearFila(object $row): array
    {
        $cantidad = (float) ($row->cantidad ?? 0);
        $precio = (float) ($row->precio ?? 0);
        $descuento = (float) ($row->descuento ?? 0);
        $precioNeto = $precio * (1 - ($descuento / 100));
        $total = round($cantidad * $precioNeto, 4);
        $cantRech = (float) ($row->cant_rech ?? 0);
        $fecha = self::ymdDesdeAnita((int) ($row->recm_fecha ?? 0));
        $fechaOc = self::ymdDesdeAnita((int) ($row->fecha_oc ?? 0));
        $fechaReq = self::ymdDesdeAnita((int) ($row->fecha_req ?? 0));
        $sucursal = (int) ($row->recm_sucursal ?? 0);
        $nro = (int) ($row->recm_nro ?? 0);
        $letra = trim((string) ($row->recm_letra ?? 'X'));
        $comAnita = $nro > 0 ? sprintf('%s%04d-%08d', $letra, $sucursal, $nro) : '';
        $nroOc = (int) ($row->nro_oc ?? 0);
        if ($nroOc <= 0) {
            $nroOc = (int) ($row->com_nro ?? 0);
        }
        if ($nroOc <= 0) {
            $tipoFac = strtoupper(trim((string) ($row->tipo_fac ?? '')));
            if (in_array($tipoFac, ['PEP', 'OC', 'ORD'], true)) {
                $nroOc = (int) ($row->nro_fac ?? 0);
            }
        }
        $nroReq = (int) (($row->reqm_nro ?? 0) ?: ($row->nro_req ?? 0));
        $estadoAnita = trim((string) ($row->recm_estado ?? ''));
        $estadoAnul = (string) config('recepcion_proveedor.anita.recepcion_estado_anulada', '3');
        $estado = $estadoAnita === $estadoAnul
            ? RecepcionProveedorEstados::ANULADA
            : RecepcionProveedorEstados::CONFIRMADA;
        $facturaAnita = self::formatoFacturaAnita(
            $row->tipo_fac ?? null,
            $row->letra_fac ?? null,
            $row->sucursal_fac ?? null,
            $row->nro_fac ?? null,
        );

        return [
            'tipo_fila' => 'dato',
            'fuente' => self::FUENTE,
            'linea_id' => 0,
            'recepcion_id' => 0,
            'articulo_id' => (int) ($row->articulo_id ?? 0),
            'ordencompra_id' => 0,
            'requisicion_id' => 0,
            'cuentacontable_id' => 0,
            'comprobante_proveedor_id' => 0,
            'asiento_id' => 0,
            'empresa_id' => (int) ($row->empresa_id ?? 0),
            'proveedor_id' => (int) ($row->proveedor_id ?? 0),
            'nombreempresa' => (string) ($row->nombreempresa ?? ''),
            'sku' => trim((string) ($row->sku ?? '')),
            'descripcion_articulo' => trim((string) ($row->descripcion ?? '')),
            'npu_desde' => '',
            'npu_hasta' => '',
            'nombre_categoria' => trim((string) ($row->agrupacion ?? '')),
            'nombre_subcategoria' => '',
            'nombre_tipoarticulo' => '',
            'numerorecepcion' => $nro > 0 ? (string) $nro : '',
            'com_anita' => $comAnita,
            'codigo_proveedor' => ltrim(trim((string) ($row->codigo_proveedor ?? '')), '0') ?: trim((string) ($row->codigo_proveedor ?? '')),
            'nombreproveedor' => (string) ($row->nombreproveedor ?? ''),
            'fecha' => $fecha,
            'fecha_fmt' => RecepcionProveedorReporteFiltros::formatearFechaPantalla($fecha),
            'tipo' => Recepcion_Proveedor::TIPO_RECEPCION,
            'estado' => $estado,
            'cantidad' => $cantidad,
            'cantidad_oc' => 0.0,
            'cantidad_rechazada' => $cantRech,
            'cantidad_entregada_oc' => 0.0,
            'um_abreviatura' => trim((string) ($row->um ?? '')),
            'precio' => $precioNeto,
            'precio_oc' => 0.0,
            'total' => $total,
            'numeroordencompra' => $nroOc > 0 ? (string) $nroOc : '',
            'fecha_oc' => $fechaOc,
            'fecha_oc_fmt' => RecepcionProveedorReporteFiltros::formatearFechaPantalla($fechaOc),
            'codigo_cc' => trim((string) ($row->ccosto ?? '')) !== '' && (int) ($row->ccosto ?? 0) > 0
                ? (string) (int) $row->ccosto
                : '',
            'nombre_cc' => '',
            'codigo_cc_req' => trim((string) ($row->cc_dest ?? '')) !== '' && (int) ($row->cc_dest ?? 0) > 0
                ? (string) (int) $row->cc_dest
                : '',
            'codigo_cuenta' => '',
            'nombre_cuenta' => '',
            'dif_unidades' => 0.0,
            'pendiente' => 0.0,
            'var_precio_pct' => 0.0,
            'numerofactura' => $facturaAnita,
            'numerorequisicion' => $nroReq > 0 ? (string) $nroReq : '',
            'fecha_requisicion' => $fechaReq,
            'fecha_requisicion_fmt' => RecepcionProveedorReporteFiltros::formatearFechaPantalla($fechaReq),
            'usuario_requisicion' => trim((string) (($row->usuario_requisicion ?? '') ?: ((int) ($row->reqm_usuario ?? 0) > 0 ? (string) (int) $row->reqm_usuario : ''))),
            'autorizante_requisicion' => trim((string) ($row->autorizante_anita ?? '')),
            'comentario' => trim((string) (($row->motivo_rech ?? '') ?: ($row->observacion ?? ''))),
            'usuario' => trim((string) ($row->recm_usuario ?? '')),
            'usuario_orig' => '',
            'numerorecepcion_orig' => '',
            'codigo_deposito' => trim((string) ($row->deposito ?? '')) !== '' && (int) ($row->deposito ?? 0) > 0
                ? (string) (int) $row->deposito
                : '',
            'nombre_deposito' => '',
            'numeroasiento' => '',
            'moneda_id' => 1,
            'moneda_abreviatura' => trim((string) ($row->cod_mon ?? '')),
            'cotizacion' => (float) ($row->cotizacion ?? 0),
            'facturado' => false,
            'estado_facturacion' => 'Anita (sin factura ERP)',
            'factura_erp' => '',
            'factura_fecha' => '',
            'dias_sin_facturar' => null,
            'tiene_diff' => $cantRech > 0.0001,
            'fl_precio_pendiente' => false,
            'tipo_linea' => '',
            'clave_grupo' => '',
            'etiqueta_grupo' => '',
            'anita_sucursal' => $sucursal,
            'anita_nro' => $nro,
        ];
    }

    /**
     * @param  list<object>  $filas
     * @return list<object>
     */
    private static function enriquecerLocal(array $filas): array
    {
        if ($filas === []) {
            return [];
        }

        $sucursales = [];
        $codigosProv = [];
        foreach ($filas as $fila) {
            $suc = (int) ($fila->recm_sucursal ?? 0);
            if ($suc > 0) {
                $sucursales[$suc] = true;
            }
            $cod = ltrim(trim((string) ($fila->codigo_proveedor ?? '')), '0');
            if ($cod !== '') {
                $codigosProv[$cod] = true;
                $codigosProv[str_pad($cod, 6, '0', STR_PAD_LEFT)] = true;
            }
        }

        $empresas = Empresa::query()
            ->whereIn('codigo', array_map('strval', array_keys($sucursales)))
            ->get(['id', 'codigo', 'nombre']);
        $empresaPorCodigo = [];
        foreach ($empresas as $e) {
            $empresaPorCodigo[(int) $e->codigo] = $e;
        }

        $proveedores = $codigosProv === []
            ? collect()
            : Proveedor::query()->whereIn('codigo', array_keys($codigosProv))->get(['id', 'codigo', 'nombre']);
        $provPorCodigo = [];
        foreach ($proveedores as $p) {
            $norm = ltrim(trim((string) $p->codigo), '0') ?: (string) $p->codigo;
            $provPorCodigo[$norm] = $p;
        }

        foreach ($filas as $fila) {
            $suc = (int) ($fila->recm_sucursal ?? 0);
            $emp = $empresaPorCodigo[$suc] ?? null;
            $fila->empresa_id = $emp ? (int) $emp->id : 0;
            $fila->nombreempresa = $emp ? (string) $emp->nombre : '';
            $cod = ltrim(trim((string) ($fila->codigo_proveedor ?? '')), '0');
            $prov = $cod !== '' ? ($provPorCodigo[$cod] ?? null) : null;
            $fila->proveedor_id = $prov ? (int) $prov->id : 0;
            $fila->nombreproveedor = $prov ? (string) $prov->nombre : '';
        }

        return self::enriquecerNombresLocal($filas);
    }

    /**
     * Nombres de pedidor/autorizante sin segunda lectura Anita:
     * usuario ERP (id o login) y, si la OC/REQ ya está en el ERP, su creador.
     *
     * @param  list<object>  $filas
     * @return list<object>
     */
    private static function enriquecerNombresLocal(array $filas): array
    {
        $idsUsuario = [];
        $logins = [];
        $nrosOc = [];
        $nrosReq = [];
        foreach ($filas as $fila) {
            $idReqUsu = (int) ($fila->reqm_usuario ?? 0);
            if ($idReqUsu > 0) {
                $idsUsuario[$idReqUsu] = true;
            }
            $loginApr = mb_strtolower(trim((string) ($fila->autorizante_anita ?? '')));
            if ($loginApr !== '') {
                $logins[$loginApr] = true;
            }
            $nroOc = self::nroOcDesdeFilaAnita($fila);
            if ($nroOc > 0) {
                $nrosOc[$nroOc] = true;
            }
            $nroFac = (int) ($fila->nro_fac ?? 0);
            $tipoFac = strtoupper(trim((string) ($fila->tipo_fac ?? '')));
            if ($nroFac > 0 && in_array($tipoFac, ['PEP', 'OC', 'ORD'], true)) {
                $nrosOc[$nroFac] = true;
            }
            $nroReq = (int) (($fila->reqm_nro ?? 0) ?: ($fila->nro_req ?? 0));
            if ($nroReq > 0) {
                $nrosReq[$nroReq] = true;
            }
        }

        $usuarios = collect();
        if ($idsUsuario !== [] || $logins !== []) {
            $usuarios = Usuario::query()
                ->where(function ($q) use ($idsUsuario, $logins) {
                    if ($idsUsuario !== []) {
                        $q->orWhereIn('id', array_keys($idsUsuario));
                    }
                    if ($logins !== []) {
                        $q->orWhereIn('usuario', array_keys($logins));
                    }
                })
                ->get(['id', 'usuario', 'nombre']);
        }
        $porId = [];
        $porLogin = [];
        foreach ($usuarios as $u) {
            $porId[(int) $u->id] = trim((string) $u->nombre) ?: trim((string) $u->usuario);
            $login = mb_strtolower(trim((string) $u->usuario));
            if ($login !== '') {
                $porLogin[$login] = trim((string) $u->nombre) ?: $login;
            }
        }

        $nombrePorReq = [];
        if ($nrosReq !== []) {
            $reqs = Requisicion::query()
                ->whereIn('numerorequisicion', array_keys($nrosReq))
                ->get(['id', 'numerorequisicion', 'empresa_id', 'creousuario_id']);
            $creadores = [];
            foreach ($reqs as $req) {
                $creadores[(int) $req->creousuario_id] = true;
            }
            if ($creadores !== []) {
                foreach (Usuario::query()->whereIn('id', array_keys($creadores))->get(['id', 'usuario', 'nombre']) as $u) {
                    $porId[(int) $u->id] = trim((string) $u->nombre) ?: trim((string) $u->usuario);
                }
            }
            foreach ($reqs as $req) {
                $nombre = $porId[(int) $req->creousuario_id] ?? '';
                if ($nombre === '') {
                    continue;
                }
                $clave = ((int) $req->empresa_id).'|'.((int) $req->numerorequisicion);
                $nombrePorReq[$clave] = $nombre;
                $nombrePorReq['0|'.((int) $req->numerorequisicion)] ??= $nombre;
            }
        }

        if ($nrosOc !== []) {
            $ocs = Ordencompra::query()
                ->whereIn('numeroordencompra', array_keys($nrosOc))
                ->whereNotNull('requisicion_id')
                ->get(['numeroordencompra', 'empresa_id', 'requisicion_id']);
            $reqIds = $ocs->pluck('requisicion_id')->map(fn ($id) => (int) $id)->filter()->unique()->all();
            if ($reqIds !== []) {
                $reqsOc = Requisicion::query()
                    ->whereIn('id', $reqIds)
                    ->get(['id', 'creousuario_id']);
                $faltan = [];
                foreach ($reqsOc as $req) {
                    if (! isset($porId[(int) $req->creousuario_id])) {
                        $faltan[(int) $req->creousuario_id] = true;
                    }
                }
                if ($faltan !== []) {
                    foreach (Usuario::query()->whereIn('id', array_keys($faltan))->get(['id', 'usuario', 'nombre']) as $u) {
                        $porId[(int) $u->id] = trim((string) $u->nombre) ?: trim((string) $u->usuario);
                    }
                }
                $nombrePorReqId = [];
                foreach ($reqsOc as $req) {
                    $nombrePorReqId[(int) $req->id] = $porId[(int) $req->creousuario_id] ?? '';
                }
                foreach ($ocs as $oc) {
                    $nombre = $nombrePorReqId[(int) $oc->requisicion_id] ?? '';
                    if ($nombre === '') {
                        continue;
                    }
                    $nro = (int) $oc->numeroordencompra;
                    $nombrePorReq[((int) $oc->empresa_id).'|oc|'.$nro] = $nombre;
                    $nombrePorReq['0|oc|'.$nro] ??= $nombre;
                }
            }
        }

        foreach ($filas as $fila) {
            $empId = (int) ($fila->empresa_id ?? 0);
            $nroReq = (int) (($fila->reqm_nro ?? 0) ?: ($fila->nro_req ?? 0));
            $nroOc = self::nroOcDesdeFilaAnita($fila);
            $pedidor = trim((string) ($fila->usuario_requisicion ?? ''));
            if ($pedidor === '' && $nroReq > 0) {
                $pedidor = $nombrePorReq[$empId.'|'.$nroReq] ?? $nombrePorReq['0|'.$nroReq] ?? '';
            }
            if ($pedidor === '' && $nroOc > 0) {
                $pedidor = $nombrePorReq[$empId.'|oc|'.$nroOc] ?? '';
            }
            $nroFac = (int) ($fila->nro_fac ?? 0);
            if ($pedidor === '' && $nroFac > 0) {
                $pedidor = $nombrePorReq[$empId.'|oc|'.$nroFac] ?? $nombrePorReq['0|oc|'.$nroFac] ?? '';
            }
            if ($pedidor === '') {
                $idReqUsu = (int) ($fila->reqm_usuario ?? 0);
                $pedidor = $idReqUsu > 0 ? ($porId[$idReqUsu] ?? '') : '';
            }
            if ($pedidor !== '') {
                $fila->usuario_requisicion = $pedidor;
            }

            $loginApr = mb_strtolower(trim((string) ($fila->autorizante_anita ?? '')));
            if ($loginApr !== '' && isset($porLogin[$loginApr])) {
                $fila->autorizante_anita = $porLogin[$loginApr];
            }
        }

        return $filas;
    }

    private static function nroOcDesdeFilaAnita(object $row): int
    {
        $nroOc = (int) ($row->nro_oc ?? 0);
        if ($nroOc > 0) {
            return $nroOc;
        }
        $nroOc = (int) ($row->com_nro ?? 0);
        if ($nroOc > 0) {
            return $nroOc;
        }
        $tipoFac = strtoupper(trim((string) ($row->tipo_fac ?? '')));
        if (in_array($tipoFac, ['PEP', 'OC', 'ORD'], true)) {
            return (int) ($row->nro_fac ?? 0);
        }

        return 0;
    }

    private static function matchesInformix(string $texto): string
    {
        $limpio = str_replace(['*', '?', '[', ']'], ['', '', '', ''], $texto);

        return AnitaSqlLiteral::string('*'.$limpio.'*', 40);
    }

    private static function ymdDesdeAnita(int $fecha): string
    {
        $s = str_pad((string) $fecha, 8, '0', STR_PAD_LEFT);
        if (strlen($s) !== 8 || (int) $s < 19900101) {
            return '';
        }

        return substr($s, 0, 4).'-'.substr($s, 4, 2).'-'.substr($s, 6, 2);
    }

    private static function formatoFacturaAnita($tipo, $letra, $sucursal, $nro): string
    {
        $nro = (int) $nro;
        $tipo = strtoupper(trim((string) $tipo));
        if ($nro <= 0 || in_array($tipo, ['PEP', 'OC', 'ORD', 'COM', ''], true)) {
            return '';
        }

        return trim($tipo.' '.trim((string) $letra).sprintf('%04d', (int) $sucursal).'-'.$nro);
    }
}
