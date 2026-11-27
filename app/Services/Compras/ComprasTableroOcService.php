<?php

namespace App\Services\Compras;

use App\Models\Stock\Recepcion_Proveedor;
use App\Support\Compras\AnitaSync\AnitaUsuarioBridgeSupport;
use App\Support\Compras\ComprasTableroOcFiltros as F;
use App\Support\Compras\OrdencompraEstados;
use App\Support\Compras\OrdencompraReporteCriteriosSupport;
use App\Support\Configuracion\CotizacionVigenteSupport;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator as LengthAwarePaginatorContract;
use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Tablero de compras (réplica del Power BI de Alejandro).
 *
 * Universo: OC emitidas (APROBADA / CUMPLIDA / CERRADA) con fecha dentro del período.
 * Las OC en PENDIENTE de aprobación y las SUSPENDIDAS no entran: todavía no son pedido al proveedor.
 *
 * Estado de la OC (derivado):
 * - Finalizada: OC CUMPLIDA o CERRADA, o recibida toda la cantidad pedida.
 * - Entrega parcial: OC aprobada con alguna recepción (COM) pero sin completar.
 * - Pendiente: OC aprobada sin recepción.
 *
 * Vencimiento: solo para OC no finalizadas; vencida si la fecha de entrega es anterior a hoy.
 *
 * Tiempos (días corridos):
 * - Gestión: fecha de la requisición → fecha de la OC.
 * - Entrega: fecha de la OC → fecha de la primera COM confirmada.
 * - Ciclo de vida: requisición → primera COM (solo OC con ambas).
 *
 * Comprador: quien cargó la OC en el ERP. En las OC importadas de Anita (comentario «Usuario ini: N»)
 * el comprador es el usuario Anita N, vinculado al usuario ERP por logname (AnitaUsuarioBridgeSupport);
 * si no se puede vincular queda como «Usuario Anita N».
 *
 * Importes en pesos: cantidad × precio × cotización de la línea de la OC (moneda extranjera);
 * moneda local no convierte. Cotización 0/1 en moneda extranjera → vigente (CotizacionVigenteSupport).
 */
final class ComprasTableroOcService
{
    public const ESTADO_NOMBRE_FINALIZADA = 'Finalizada';

    public const ESTADO_NOMBRE_PENDIENTE = 'Pendiente';

    public const ESTADO_NOMBRE_PARCIAL = 'Entrega parcial';

    public const TOP_RANKING = 25;

    private const MESES_ABREV = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];

    private const ESTADOS_EMITIDA = [
        OrdencompraEstados::APROBADA,
        OrdencompraEstados::CUMPLIDA,
        OrdencompraEstados::CERRADA,
    ];

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    public function generar(array $filtros): array
    {
        [$desde, $hasta] = F::rangoFechas($filtros);
        $hoy = Carbon::now()->format('Y-m-d');

        $ocs = $this->cargarOcs($filtros, $desde, $hasta, $hoy);
        $lineas = $this->cargarLineas($filtros, $desde, $hasta, $ocs);
        $meses = $this->mesesDelPeriodo($desde, $hasta, $hoy);

        $tablero = $this->agregar($ocs, $lineas, $meses, $filtros, $desde, $hasta, $hoy);

        return [
            'desde' => $desde,
            'hasta' => $hasta,
            'ocs' => $ocs,
            'lineas' => $lineas,
            'meses' => $meses,
            'tablero' => $tablero,
            'detalle' => $this->armarDetalle($ocs, $lineas),
        ];
    }

    // ------------------------------------------------------------------
    // Carga de datos
    // ------------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<int, array<string, mixed>> OC filtradas, indexadas por id
     */
    private function cargarOcs(array $filtros, string $desde, string $hasta, string $hoy): array
    {
        $idsPeriodo = DB::table('ordencompra')->select('id')->whereBetween('fecha', [$desde, $hasta]);

        $lineasSub = DB::table('ordencompra_articulo as oa')
            ->whereIn('oa.ordencompra_id', clone $idsPeriodo)
            ->groupBy('oa.ordencompra_id')
            ->select('oa.ordencompra_id')
            ->selectRaw('COUNT(*) as lineas')
            ->selectRaw('SUM(oa.cantidad) as cantidad_pedida');

        $recepciones = DB::table('recepcion_proveedor as rp')
            ->leftJoin('recepcion_proveedor_articulo as rpa', 'rpa.recepcion_proveedor_id', '=', 'rp.id')
            ->where('rp.estado', Recepcion_Proveedor::ESTADO_CONFIRMADA)
            ->whereIn('rp.ordencompra_id', clone $idsPeriodo)
            ->groupBy('rp.ordencompra_id')
            ->select('rp.ordencompra_id')
            ->selectRaw('MIN(CASE WHEN rp.tipo = ? THEN rp.fecha END) as primera_com', [Recepcion_Proveedor::TIPO_RECEPCION])
            ->selectRaw('COUNT(DISTINCT CASE WHEN rp.tipo = ? THEN rp.id END) as coms', [Recepcion_Proveedor::TIPO_RECEPCION])
            ->selectRaw(
                'SUM(CASE WHEN rp.tipo = ? THEN COALESCE(rpa.cantidad, 0) + COALESCE(rpa.cantidad_rechazada, 0) '
                .'WHEN rp.tipo = ? THEN -(COALESCE(rpa.cantidad, 0) + COALESCE(rpa.cantidad_rechazada, 0)) '
                .'ELSE 0 END) as recibida',
                [Recepcion_Proveedor::TIPO_RECEPCION, Recepcion_Proveedor::TIPO_DEVOLUCION]
            );

        $query = DB::table('ordencompra as oc')
            ->join('empresa as e', 'e.id', '=', 'oc.empresa_id')
            ->leftJoin('usuario as u', 'u.id', '=', 'oc.creousuario_id')
            ->leftJoin('proveedor as p', 'p.id', '=', 'oc.proveedor_id')
            ->leftJoin('requisicion as r', 'r.id', '=', 'oc.requisicion_id')
            ->leftJoinSub($lineasSub, 'ln', 'ln.ordencompra_id', '=', 'oc.id')
            ->leftJoinSub($recepciones, 'rc', 'rc.ordencompra_id', '=', 'oc.id')
            ->select([
                'oc.id',
                'oc.numeroordencompra',
                'oc.fecha',
                'oc.fechaentrega',
                'oc.empresa_id',
                'e.nombre as empresa',
                'oc.creousuario_id as usuario_id',
                'oc.comentario',
                'u.nombre as usuario',
                'oc.proveedor_id',
                'p.codigo as proveedor_codigo',
                'p.nombre as proveedor',
                'oc.estadoordencompra',
                'r.fecha as fecha_requisicion',
                'ln.lineas',
                'ln.cantidad_pedida',
                'rc.primera_com',
                'rc.coms',
                'rc.recibida',
            ]);

        $this->aplicarFiltrosBase($query, $filtros, $desde, $hasta);

        $filas = $query->get();
        $compradores = $this->resolverCompradores($filas);
        $filtroUsuarios = $this->parsearFiltroUsuarios((string) $filtros['usuarios']);

        $ocs = [];
        foreach ($filas as $row) {
            $oc = $this->mapearOc($row, $hoy);
            $comprador = $compradores[(int) $row->id];
            $oc['usuario_id'] = $comprador['id'];
            $oc['usuario'] = $comprador['nombre'];

            if ($filtroUsuarios !== null && ! $filtroUsuarios($oc['usuario_id'])) {
                continue;
            }

            if ($filtros['estado'] !== F::ESTADO_TODOS && $oc['estado_clave'] !== $filtros['estado']) {
                continue;
            }
            if ($filtros['vencimiento'] !== F::VENCIMIENTO_TODOS && $oc['vencimiento'] !== $filtros['vencimiento']) {
                continue;
            }
            if ($filtros['com'] === F::COM_CON && $oc['coms'] <= 0) {
                continue;
            }
            if ($filtros['com'] === F::COM_SIN && $oc['coms'] > 0) {
                continue;
            }

            $ocs[$oc['id']] = $oc;
        }

        return $ocs;
    }

    /**
     * @return array<string, mixed>
     */
    private function mapearOc(object $row, string $hoy): array
    {
        $pedida = (float) ($row->cantidad_pedida ?? 0);
        $recibida = (float) ($row->recibida ?? 0);
        $coms = (int) ($row->coms ?? 0);
        $estadoOc = (string) $row->estadoordencompra;

        $finalizada = in_array($estadoOc, [OrdencompraEstados::CUMPLIDA, OrdencompraEstados::CERRADA], true)
            || ($pedida > 0 && $recibida >= $pedida - 0.000001);

        if ($finalizada) {
            $estadoClave = F::ESTADO_FINALIZADA;
            $estadoNombre = self::ESTADO_NOMBRE_FINALIZADA;
        } elseif ($recibida > 0.000001) {
            $estadoClave = F::ESTADO_PARCIAL;
            $estadoNombre = self::ESTADO_NOMBRE_PARCIAL;
        } else {
            $estadoClave = F::ESTADO_PENDIENTE;
            $estadoNombre = self::ESTADO_NOMBRE_PENDIENTE;
        }

        $vencimiento = null;
        if (! $finalizada) {
            $vencimiento = ((string) $row->fechaentrega !== '' && (string) $row->fechaentrega < $hoy)
                ? F::VENCIMIENTO_VENCIDA
                : F::VENCIMIENTO_A_TIEMPO;
        }

        $fecha = (string) $row->fecha;
        $primeraCom = $row->primera_com !== null ? substr((string) $row->primera_com, 0, 10) : null;
        $fechaRq = $row->fecha_requisicion !== null ? substr((string) $row->fecha_requisicion, 0, 10) : null;

        $gestion = $fechaRq !== null ? $this->diasEntre($fechaRq, $fecha) : null;
        $entrega = ($primeraCom !== null && $coms > 0) ? $this->diasEntre($fecha, $primeraCom) : null;
        $ciclo = ($gestion !== null && $entrega !== null)
            ? $this->diasEntre($fechaRq, $primeraCom)
            : null;

        return [
            'id' => (int) $row->id,
            'numero' => (int) $row->numeroordencompra,
            'fecha' => $fecha,
            'mes' => substr($fecha, 0, 7),
            'fechaentrega' => $row->fechaentrega !== null ? substr((string) $row->fechaentrega, 0, 10) : null,
            'empresa_id' => (int) $row->empresa_id,
            'empresa' => (string) $row->empresa,
            'usuario_id' => (int) ($row->usuario_id ?? 0),
            'usuario' => trim((string) ($row->usuario ?? '')) !== '' ? trim((string) $row->usuario) : '(sin usuario)',
            'proveedor_id' => (int) ($row->proveedor_id ?? 0),
            'proveedor_codigo' => (string) ($row->proveedor_codigo ?? ''),
            'proveedor' => trim((string) ($row->proveedor ?? '')) !== '' ? trim((string) $row->proveedor) : '(sin proveedor)',
            'estado_oc' => $estadoOc,
            'estado_clave' => $estadoClave,
            'estado' => $estadoNombre,
            'vencimiento' => $vencimiento,
            'lineas' => (int) ($row->lineas ?? 0),
            'coms' => $coms,
            'primera_com' => $primeraCom,
            'gestion_dias' => $gestion,
            'entrega_dias' => $entrega,
            'ciclo_dias' => $ciclo,
        ];
    }

    /**
     * Comprador de cada OC (ver docblock de la clase).
     *
     * @param  iterable<object>  $filas
     * @return array<int, array{id: int, nombre: string}> ordencompra.id => comprador
     */
    private function resolverCompradores(iterable $filas): array
    {
        $codigosPorOc = [];
        foreach ($filas as $row) {
            $comentario = (string) ($row->comentario ?? '');
            if (str_contains($comentario, 'Fecha ingreso Anita')
                && preg_match('/Usuario ini:\s*(\d+)/', $comentario, $m) === 1
                && (int) $m[1] > 0
            ) {
                $codigosPorOc[(int) $row->id] = (int) $m[1];
            }
        }

        $mapaAnita = $this->mapaUsuariosAnita(array_values(array_unique($codigosPorOc)));

        $idsErp = [];
        foreach ($filas as $row) {
            $idsErp[(int) ($row->usuario_id ?? 0)] = true;
        }
        foreach ($mapaAnita as $erpId) {
            $idsErp[$erpId] = true;
        }
        $nombres = DB::table('usuario')
            ->whereIn('id', array_keys($idsErp))
            ->pluck('nombre', 'id')
            ->all();

        $resultado = [];
        foreach ($filas as $row) {
            $ocId = (int) $row->id;
            $codigo = $codigosPorOc[$ocId] ?? null;

            if ($codigo !== null && isset($mapaAnita[$codigo])) {
                $erpId = $mapaAnita[$codigo];
                $resultado[$ocId] = ['id' => $erpId, 'nombre' => trim((string) ($nombres[$erpId] ?? '')) ?: ('Usuario #'.$erpId)];
            } elseif ($codigo !== null) {
                // Id negativo: no choca con ningún usuario ERP y agrupa por código Anita.
                $resultado[$ocId] = ['id' => -$codigo, 'nombre' => 'Usuario Anita '.$codigo];
            } else {
                $uid = (int) ($row->usuario_id ?? 0);
                $nombre = trim((string) ($nombres[$uid] ?? ''));
                $resultado[$ocId] = ['id' => $uid, 'nombre' => $nombre !== '' ? $nombre : '(sin usuario)'];
            }
        }

        return $resultado;
    }

    /**
     * @param  list<int>  $codigos
     * @return array<int, int> usu_usuario Anita => usuario.id ERP
     */
    private function mapaUsuariosAnita(array $codigos): array
    {
        if ($codigos === []) {
            return [];
        }
        sort($codigos);

        return Cache::remember(
            'compras_tablero_usuarios_anita:'.md5(implode(',', $codigos)),
            now()->addMinutes(30),
            function () use ($codigos): array {
                try {
                    return AnitaUsuarioBridgeSupport::mapaErpIdPorUsuUsuario($codigos);
                } catch (\Throwable $e) {
                    Log::warning('Tablero compras: no se pudo vincular usuarios Anita', ['error' => $e->getMessage()]);

                    return [];
                }
            }
        );
    }

    /**
     * Criterio de comprador: ids separados por coma o rango «a/b» (mismo formato que el resto de los reportes).
     *
     * @return (callable(int): bool)|null null = sin filtro
     */
    private function parsearFiltroUsuarios(string $valor): ?callable
    {
        $valor = trim($valor);
        if ($valor === '') {
            return null;
        }

        $ids = [];
        $rangos = [];
        foreach (preg_split('/[,;\s]+/', $valor) ?: [] as $token) {
            if ($token === '') {
                continue;
            }
            if (str_contains($token, '/')) {
                [$a, $b] = array_pad(explode('/', $token, 2), 2, '');
                if (ctype_digit($a) && ctype_digit($b)) {
                    $rangos[] = [min((int) $a, (int) $b), max((int) $a, (int) $b)];
                }
            } elseif (ctype_digit($token)) {
                $ids[(int) $token] = true;
            }
        }

        if ($ids === [] && $rangos === []) {
            return null;
        }

        return static function (int $usuarioId) use ($ids, $rangos): bool {
            if (isset($ids[$usuarioId])) {
                return true;
            }
            foreach ($rangos as [$desde, $hasta]) {
                if ($usuarioId >= $desde && $usuarioId <= $hasta) {
                    return true;
                }
            }

            return false;
        };
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @param  array<int, array<string, mixed>>  $ocs
     * @return list<array<string, mixed>>
     */
    private function cargarLineas(array $filtros, string $desde, string $hasta, array $ocs): array
    {
        if ($ocs === []) {
            return [];
        }

        $query = DB::table('ordencompra_articulo as oa')
            ->join('ordencompra as oc', 'oc.id', '=', 'oa.ordencompra_id')
            ->leftJoin('proveedor as p', 'p.id', '=', 'oc.proveedor_id')
            ->leftJoin('articulo as a', 'a.id', '=', 'oa.articulo_id')
            ->leftJoin('moneda as m', 'm.id', '=', 'oa.moneda_id')
            ->select([
                'oa.id',
                'oa.ordencompra_id',
                'oa.articulo_id',
                'a.sku',
                'a.descripcion as articulo',
                'oa.cantidad',
                'oa.precio',
                'oa.moneda_id',
                'oa.cotizacion',
                'm.abreviatura as moneda',
                'oc.fecha as fecha_oc',
            ])
            ->orderBy('oc.fecha')
            ->orderBy('oc.numeroordencompra')
            ->orderBy('oa.id');

        $this->aplicarFiltrosBase($query, $filtros, $desde, $hasta);

        $lineas = [];
        foreach ($query->get() as $row) {
            $ocId = (int) $row->ordencompra_id;
            if (! isset($ocs[$ocId])) {
                continue;
            }

            $monedaId = (int) ($row->moneda_id ?: CotizacionVigenteSupport::MONEDA_LOCAL_ID);
            $coef = CotizacionVigenteSupport::ventaEfectiva($row->cotizacion, $monedaId, (string) $row->fecha_oc);
            $cantidad = (float) $row->cantidad;
            $precio = (float) $row->precio;

            $lineas[] = [
                'id' => (int) $row->id,
                'oc_id' => $ocId,
                'articulo_id' => (int) ($row->articulo_id ?? 0),
                'sku' => (string) ($row->sku ?? ''),
                'articulo' => trim((string) ($row->articulo ?? '')),
                'cantidad' => $cantidad,
                'precio' => $precio,
                'moneda' => (string) ($row->moneda ?? ''),
                'precio_pesos' => $precio * $coef,
                'monto_pesos' => $cantidad * $precio * $coef,
            ];
        }

        return $lineas;
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private function aplicarFiltrosBase(Builder $query, array $filtros, string $desde, string $hasta): void
    {
        $query->whereBetween('oc.fecha', [$desde, $hasta])
            ->whereIn('oc.estadoordencompra', self::ESTADOS_EMITIDA)
            ->whereIn('oc.empresa_id', $filtros['empresa_ids']);

        OrdencompraReporteCriteriosSupport::aplicarFiltroProveedoresCodigo($query, (string) $filtros['proveedores'], 'p.codigo');
    }

    // ------------------------------------------------------------------
    // Agregación
    // ------------------------------------------------------------------

    /**
     * @param  array<int, array<string, mixed>>  $ocs
     * @param  list<array<string, mixed>>  $lineas
     * @param  list<array{key: string, label: string}>  $meses
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    private function agregar(array $ocs, array $lineas, array $meses, array $filtros, string $desde, string $hasta, string $hoy): array
    {
        $claveMeses = array_column($meses, 'key');
        $ceroMeses = array_fill_keys($claveMeses, 0);

        $estados = [
            self::ESTADO_NOMBRE_FINALIZADA => 0,
            self::ESTADO_NOMBRE_PENDIENTE => 0,
            self::ESTADO_NOMBRE_PARCIAL => 0,
        ];
        $estadosMes = [];
        foreach ($estados as $nombre => $_) {
            $estadosMes[$nombre] = $ceroMeses;
        }

        $ocMes = $ceroMeses;
        $aTiempo = 0;
        $vencidas = 0;
        $vencidasMes = [];
        foreach (array_keys($estados) as $nombre) {
            $vencidasMes[$nombre] = $ceroMeses;
        }

        $acGestion = ['suma' => 0.0, 'n' => 0];
        $acEntrega = ['suma' => 0.0, 'n' => 0];
        $acCiclo = ['suma' => 0.0, 'n' => 0];

        $compradores = [];
        $proveedores = [];
        $empresas = [];
        $proveedoresActivosMes = [];

        foreach ($ocs as $oc) {
            $mes = $oc['mes'];
            $estados[$oc['estado']]++;
            if (isset($estadosMes[$oc['estado']][$mes])) {
                $estadosMes[$oc['estado']][$mes]++;
            }
            if (isset($ocMes[$mes])) {
                $ocMes[$mes]++;
            }

            if ($oc['vencimiento'] === F::VENCIMIENTO_VENCIDA) {
                $vencidas++;
                if (isset($vencidasMes[$oc['estado']][$mes])) {
                    $vencidasMes[$oc['estado']][$mes]++;
                }
            } elseif ($oc['vencimiento'] === F::VENCIMIENTO_A_TIEMPO) {
                $aTiempo++;
            }

            if ($oc['gestion_dias'] !== null) {
                $acGestion['suma'] += $oc['gestion_dias'];
                $acGestion['n']++;
            }
            if ($oc['entrega_dias'] !== null) {
                $acEntrega['suma'] += $oc['entrega_dias'];
                $acEntrega['n']++;
            }
            if ($oc['ciclo_dias'] !== null) {
                $acCiclo['suma'] += $oc['ciclo_dias'];
                $acCiclo['n']++;
            }

            // Compradores
            $uid = $oc['usuario_id'];
            if (! isset($compradores[$uid])) {
                $compradores[$uid] = [
                    'id' => $uid,
                    'nombre' => $oc['usuario'],
                    'oc' => 0,
                    'lineas' => 0,
                    'monto' => 0.0,
                    'gestion_suma' => 0.0,
                    'gestion_n' => 0,
                    'oc_mes' => $ceroMeses,
                    'gestion_mes' => array_fill_keys($claveMeses, ['suma' => 0.0, 'n' => 0]),
                ];
            }
            $compradores[$uid]['oc']++;
            $compradores[$uid]['lineas'] += $oc['lineas'];
            if (isset($compradores[$uid]['oc_mes'][$mes])) {
                $compradores[$uid]['oc_mes'][$mes]++;
            }
            if ($oc['gestion_dias'] !== null) {
                $compradores[$uid]['gestion_suma'] += $oc['gestion_dias'];
                $compradores[$uid]['gestion_n']++;
                if (isset($compradores[$uid]['gestion_mes'][$mes])) {
                    $compradores[$uid]['gestion_mes'][$mes]['suma'] += $oc['gestion_dias'];
                    $compradores[$uid]['gestion_mes'][$mes]['n']++;
                }
            }

            // Empresas
            $empresas[$oc['empresa']] = ($empresas[$oc['empresa']] ?? 0) + 1;

            // Proveedores
            $pid = $oc['proveedor_id'];
            if (! isset($proveedores[$pid])) {
                $proveedores[$pid] = [
                    'id' => $pid,
                    'codigo' => $oc['proveedor_codigo'],
                    'nombre' => $oc['proveedor'],
                    'oc' => 0,
                    'lineas' => 0,
                    'monto' => 0.0,
                    'entrega_suma' => 0.0,
                    'entrega_n' => 0,
                ];
            }
            $proveedores[$pid]['oc']++;
            if ($oc['entrega_dias'] !== null) {
                $proveedores[$pid]['entrega_suma'] += $oc['entrega_dias'];
                $proveedores[$pid]['entrega_n']++;
            }
            $proveedoresActivosMes[$mes][$pid] = true;
        }

        // Líneas: artículos comprados y montos
        $lineasMes = $ceroMeses;
        $montoMes = array_fill_keys($claveMeses, 0.0);
        $montoTotal = 0.0;
        $articulos = [];
        $precios = $this->prepararPrecios((int) $filtros['articulo_id'], $claveMeses);

        foreach ($lineas as $linea) {
            $oc = $ocs[$linea['oc_id']];
            $mes = $oc['mes'];
            $uid = $oc['usuario_id'];
            $pid = $oc['proveedor_id'];

            $compradores[$uid]['monto'] += $linea['monto_pesos'];
            $proveedores[$pid]['lineas']++;
            $proveedores[$pid]['monto'] += $linea['monto_pesos'];
            $montoTotal += $linea['monto_pesos'];
            if (isset($lineasMes[$mes])) {
                $lineasMes[$mes]++;
                $montoMes[$mes] += $linea['monto_pesos'];
            }

            $aid = $linea['articulo_id'];
            if (! isset($articulos[$aid])) {
                $articulos[$aid] = [
                    'id' => $aid,
                    'sku' => $linea['sku'],
                    'descripcion' => $linea['articulo'],
                    'lineas' => 0,
                    'cantidad' => 0.0,
                    'monto' => 0.0,
                ];
            }
            $articulos[$aid]['lineas']++;
            $articulos[$aid]['cantidad'] += $linea['cantidad'];
            $articulos[$aid]['monto'] += $linea['monto_pesos'];

            if ($precios !== null && $aid === $precios['articulo_id']) {
                $this->acumularPrecio($precios, $linea, $oc, $mes);
            }
        }

        // Rankings
        $listaCompradores = array_values(array_map(function (array $c): array {
            $c['gestion_prom'] = $c['gestion_n'] > 0 ? $c['gestion_suma'] / $c['gestion_n'] : null;

            return $c;
        }, $compradores));
        usort($listaCompradores, fn ($a, $b) => $b['oc'] <=> $a['oc']);

        $listaProveedores = array_values(array_map(function (array $p): array {
            $p['entrega_prom'] = $p['entrega_n'] > 0 ? $p['entrega_suma'] / $p['entrega_n'] : null;

            return $p;
        }, $proveedores));

        $entregaProv = array_values(array_filter($listaProveedores, fn ($p) => $p['entrega_prom'] !== null));
        usort($entregaProv, fn ($a, $b) => $b['entrega_prom'] <=> $a['entrega_prom']);

        $lineasProv = $listaProveedores;
        usort($lineasProv, fn ($a, $b) => $b['lineas'] <=> $a['lineas']);

        $montoProv = $listaProveedores;
        usort($montoProv, fn ($a, $b) => $b['monto'] <=> $a['monto']);

        arsort($empresas);

        $gestionProm = $acGestion['n'] > 0 ? $acGestion['suma'] / $acGestion['n'] : null;
        $entregaProm = $acEntrega['n'] > 0 ? $acEntrega['suma'] / $acEntrega['n'] : null;
        $cicloProm = $acCiclo['n'] > 0 ? $acCiclo['suma'] / $acCiclo['n'] : null;

        $activosMes = [];
        foreach ($claveMeses as $mes) {
            $activosMes[$mes] = isset($proveedoresActivosMes[$mes]) ? count($proveedoresActivosMes[$mes]) : 0;
        }

        $requisiciones = $this->requisiciones($filtros, $desde, $hasta, $hoy, $claveMeses);

        $habilesPeriodo = $this->diasHabiles($desde, min($hasta, $hoy));
        $diariasMes = [];
        foreach ($meses as $m) {
            $hab = $this->diasHabilesMes($m['key'], $desde, $hasta, $hoy);
            $diariasMes[$m['key']] = [
                'req' => $requisiciones['por_mes'][$m['key']] ?? 0,
                'oc' => $ocMes[$m['key']] ?? 0,
                'habiles' => $hab,
                'req_dia' => $hab > 0 ? ($requisiciones['por_mes'][$m['key']] ?? 0) / $hab : 0.0,
                'oc_dia' => $hab > 0 ? ($ocMes[$m['key']] ?? 0) / $hab : 0.0,
            ];
        }

        return [
            'resumen' => [
                'oc' => count($ocs),
                'lineas' => count($lineas),
                'monto' => $montoTotal,
                'proveedores' => count($proveedores),
                'compradores' => count($compradores),
            ],
            'estados' => $estados,
            'estados_mes' => $estadosMes,
            'oc_mes' => $ocMes,
            'ciclo' => [
                'gestion' => $gestionProm,
                'entrega' => $entregaProm,
                'ciclo' => $cicloProm,
                'n_gestion' => $acGestion['n'],
                'n_entrega' => $acEntrega['n'],
                'n_ciclo' => $acCiclo['n'],
            ],
            'diarias' => [
                'habiles' => $habilesPeriodo,
                'req_prom' => $habilesPeriodo > 0 ? $requisiciones['total'] / $habilesPeriodo : 0.0,
                'oc_prom' => $habilesPeriodo > 0 ? count($ocs) / $habilesPeriodo : 0.0,
                'por_mes' => $diariasMes,
            ],
            'pendientes' => [
                'a_tiempo' => $aTiempo,
                'vencida' => $vencidas,
                'vencidas_mes' => $vencidasMes,
            ],
            'urgentes' => [
                'urgente' => $requisiciones['urgentes'],
                'no_urgente' => $requisiciones['total'] - $requisiciones['urgentes'],
                'total' => $requisiciones['total'],
            ],
            'compradores' => $listaCompradores,
            'empresas' => $empresas,
            'proveedores_entrega' => array_slice($entregaProv, 0, self::TOP_RANKING),
            'proveedores_lineas' => array_slice($lineasProv, 0, self::TOP_RANKING),
            'proveedores_monto' => array_slice($montoProv, 0, self::TOP_RANKING),
            'proveedores_activos_mes' => $activosMes,
            'lineas_mes' => $lineasMes,
            'monto_mes' => $montoMes,
            'articulos' => $this->listarArticulos($articulos, (string) $filtros['buscar_articulo']),
            'precios' => $precios !== null ? $this->cerrarPrecios($precios, $claveMeses) : null,
        ];
    }

    /**
     * Requisiciones del período y empresas elegidas (no dependen de comprador ni proveedor).
     *
     * @param  array<string, mixed>  $filtros
     * @param  list<string>  $claveMeses
     * @return array{total: int, urgentes: int, por_mes: array<string, int>}
     */
    private function requisiciones(array $filtros, string $desde, string $hasta, string $hoy, array $claveMeses): array
    {
        $rows = DB::table('requisicion')
            ->whereBetween('fecha', [$desde, $hasta])
            ->whereIn('empresa_id', $filtros['empresa_ids'])
            ->whereNotIn('estado', ['PROVISORIO', 'SUSPENDIDA'])
            ->groupBy('fecha', 'tratamiento')
            ->select('fecha', 'tratamiento')
            ->selectRaw('COUNT(*) as n')
            ->get();

        $porMes = array_fill_keys($claveMeses, 0);
        $total = 0;
        $urgentes = 0;
        foreach ($rows as $row) {
            $n = (int) $row->n;
            $total += $n;
            if (mb_strtolower(trim((string) $row->tratamiento)) === 'urgente') {
                $urgentes += $n;
            }
            $mes = substr((string) $row->fecha, 0, 7);
            if (isset($porMes[$mes])) {
                $porMes[$mes] += $n;
            }
        }

        return ['total' => $total, 'urgentes' => $urgentes, 'por_mes' => $porMes];
    }

    // ------------------------------------------------------------------
    // Precios por artículo
    // ------------------------------------------------------------------

    /**
     * @param  list<string>  $claveMeses
     * @return array<string, mixed>|null
     */
    private function prepararPrecios(int $articuloId, array $claveMeses): ?array
    {
        if ($articuloId <= 0) {
            return null;
        }

        $art = DB::table('articulo')->where('id', $articuloId)->first(['sku', 'descripcion']);

        return [
            'articulo_id' => $articuloId,
            'sku' => (string) ($art->sku ?? ''),
            'descripcion' => trim((string) ($art->descripcion ?? '')),
            'por_proveedor' => [],
            'cantidad_mes' => array_fill_keys($claveMeses, 0.0),
            'filas' => [],
        ];
    }

    /**
     * @param  array<string, mixed>  $precios
     * @param  array<string, mixed>  $linea
     * @param  array<string, mixed>  $oc
     */
    private function acumularPrecio(array &$precios, array $linea, array $oc, string $mes): void
    {
        $pid = $oc['proveedor_id'];
        if (! isset($precios['por_proveedor'][$pid])) {
            $precios['por_proveedor'][$pid] = [
                'nombre' => $oc['proveedor'],
                'lineas' => 0,
                'meses' => [],
            ];
        }
        $precios['por_proveedor'][$pid]['lineas']++;
        $precios['por_proveedor'][$pid]['meses'][$mes]['suma'] = ($precios['por_proveedor'][$pid]['meses'][$mes]['suma'] ?? 0.0) + $linea['precio_pesos'];
        $precios['por_proveedor'][$pid]['meses'][$mes]['n'] = ($precios['por_proveedor'][$pid]['meses'][$mes]['n'] ?? 0) + 1;

        if (isset($precios['cantidad_mes'][$mes])) {
            $precios['cantidad_mes'][$mes] += $linea['cantidad'];
        }

        $precios['filas'][] = [
            'fecha' => $oc['fecha'],
            'oc_id' => $oc['id'],
            'numero' => $oc['numero'],
            'proveedor' => $oc['proveedor'],
            'cantidad' => $linea['cantidad'],
            'moneda' => $linea['moneda'],
            'precio' => $linea['precio'],
            'precio_pesos' => $linea['precio_pesos'],
        ];
    }

    /**
     * @param  array<string, mixed>  $precios
     * @param  list<string>  $claveMeses
     * @return array<string, mixed>
     */
    private function cerrarPrecios(array $precios, array $claveMeses): array
    {
        $series = array_values($precios['por_proveedor']);
        usort($series, fn ($a, $b) => $b['lineas'] <=> $a['lineas']);
        $series = array_slice($series, 0, 8);

        $salida = [];
        foreach ($series as $serie) {
            $puntos = [];
            foreach ($claveMeses as $mes) {
                $acum = $serie['meses'][$mes] ?? null;
                $puntos[] = $acum !== null && $acum['n'] > 0 ? round($acum['suma'] / $acum['n'], 2) : null;
            }
            $salida[] = ['nombre' => $serie['nombre'], 'lineas' => $serie['lineas'], 'valores' => $puntos];
        }

        return [
            'articulo_id' => $precios['articulo_id'],
            'sku' => $precios['sku'],
            'descripcion' => $precios['descripcion'],
            'series' => $salida,
            'cantidad_mes' => $precios['cantidad_mes'],
            'filas' => $precios['filas'],
        ];
    }

    /**
     * Buscador por código o descripción sobre los artículos comprados del filtro.
     *
     * @param  array<int, array<string, mixed>>  $articulos
     * @return list<array<string, mixed>>
     */
    private function listarArticulos(array $articulos, string $buscar): array
    {
        $tokens = array_values(array_filter(preg_split('/\s+/', mb_strtolower($buscar)) ?: []));

        $lista = [];
        foreach ($articulos as $art) {
            if ($art['id'] <= 0) {
                continue;
            }
            if ($tokens !== []) {
                $texto = mb_strtolower($art['sku'].' '.$art['descripcion']);
                foreach ($tokens as $token) {
                    if (! str_contains($texto, $token)) {
                        continue 2;
                    }
                }
            }
            $lista[] = $art;
        }

        usort($lista, fn ($a, $b) => $b['lineas'] <=> $a['lineas']);

        return array_slice($lista, 0, 40);
    }

    // ------------------------------------------------------------------
    // Detalle y paginación
    // ------------------------------------------------------------------

    /**
     * Una fila por línea de OC, con los datos de la cabecera.
     *
     * @param  array<int, array<string, mixed>>  $ocs
     * @param  list<array<string, mixed>>  $lineas
     * @return list<array<string, mixed>>
     */
    private function armarDetalle(array $ocs, array $lineas): array
    {
        $filas = [];
        foreach ($lineas as $linea) {
            $oc = $ocs[$linea['oc_id']];
            $filas[] = [
                'oc_id' => $oc['id'],
                'fecha' => $oc['fecha'],
                'numero' => $oc['numero'],
                'empresa' => $oc['empresa'],
                'nombreempresa' => $oc['empresa'],
                'usuario' => $oc['usuario'],
                'proveedor' => $oc['proveedor'],
                'proveedor_codigo' => $oc['proveedor_codigo'],
                'sku' => $linea['sku'],
                'articulo' => $linea['articulo'],
                'cantidad' => $linea['cantidad'],
                'moneda' => $linea['moneda'],
                'precio' => $linea['precio'],
                'monto_pesos' => $linea['monto_pesos'],
                'estado' => $oc['estado'],
                'vencimiento' => $oc['vencimiento'] === F::VENCIMIENTO_VENCIDA
                    ? 'Vencida'
                    : ($oc['vencimiento'] === F::VENCIMIENTO_A_TIEMPO ? 'A tiempo' : ''),
                'fechaentrega' => $oc['fechaentrega'],
                'primera_com' => $oc['primera_com'],
                'tiene_com' => $oc['coms'] > 0 ? 'Sí' : 'No',
            ];
        }

        return $filas;
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     */
    public function paginarFilas(array $filas, int $perPage, int $page, array $query = []): LengthAwarePaginatorContract
    {
        $page = max(1, $page);
        $perPage = max(10, min(500, $perPage));

        return new LengthAwarePaginator(
            array_slice($filas, ($page - 1) * $perPage, $perPage),
            count($filas),
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => $query],
        );
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @param  iterable<object>  $empresaQuery
     */
    public function subtituloFiltros(array $filtros, $empresaQuery): string
    {
        $partes = ['Período: '.F::textoPeriodo($filtros)];

        $empresas = collect($empresaQuery)
            ->whereIn('id', $filtros['empresa_ids'])
            ->pluck('nombre')
            ->all();
        if ($empresas !== []) {
            $partes[] = 'Empresa: '.implode(', ', $empresas);
        }
        if ($filtros['usuarios'] !== '') {
            $partes[] = 'Comprador (ID): '.$filtros['usuarios'];
        }
        if ($filtros['proveedores'] !== '') {
            $partes[] = 'Proveedor: '.$filtros['proveedores'];
        }
        foreach ([
            'estado' => [F::OPCIONES_ESTADO, F::ESTADO_TODOS, 'Estado OC'],
            'vencimiento' => [F::OPCIONES_VENCIMIENTO, F::VENCIMIENTO_TODOS, 'Vencimiento'],
            'com' => [F::OPCIONES_COM, F::COM_TODAS, 'COM'],
        ] as $clave => [$opciones, $default, $rotulo]) {
            if ($filtros[$clave] !== $default) {
                $partes[] = $rotulo.': '.collect($opciones)->firstWhere('valor', $filtros[$clave])['etiqueta'];
            }
        }

        return implode(' · ', $partes);
    }

    // ------------------------------------------------------------------
    // Fechas
    // ------------------------------------------------------------------

    private function diasEntre(string $desde, string $hasta): ?int
    {
        $d = (int) round((strtotime($hasta) - strtotime($desde)) / 86400);

        return $d >= 0 ? $d : null;
    }

    /**
     * @return list<array{key: string, label: string}>
     */
    private function mesesDelPeriodo(string $desde, string $hasta, string $hoy): array
    {
        $tope = min($hasta, $hoy);
        if ($tope < $desde) {
            $tope = $desde;
        }

        $cursor = Carbon::parse($desde)->startOfMonth();
        $fin = Carbon::parse($tope)->startOfMonth();
        $meses = [];
        while ($cursor->lte($fin)) {
            $meses[] = [
                'key' => $cursor->format('Y-m'),
                'label' => self::MESES_ABREV[$cursor->month - 1].' '.$cursor->year,
            ];
            $cursor->addMonth();
        }

        return $meses;
    }

    /** Días lunes a viernes entre dos fechas (inclusive). */
    private function diasHabiles(string $desde, string $hasta): int
    {
        if ($hasta < $desde) {
            return 0;
        }

        $dias = 0;
        $cursor = Carbon::parse($desde);
        $fin = Carbon::parse($hasta);
        while ($cursor->lte($fin)) {
            if ($cursor->isWeekday()) {
                $dias++;
            }
            $cursor->addDay();
        }

        return $dias;
    }

    private function diasHabilesMes(string $mesClave, string $desde, string $hasta, string $hoy): int
    {
        $inicio = Carbon::parse($mesClave.'-01');
        $ini = max($inicio->format('Y-m-d'), $desde);
        $fin = min($inicio->copy()->endOfMonth()->format('Y-m-d'), $hasta, $hoy);

        return $this->diasHabiles($ini, $fin);
    }
}
