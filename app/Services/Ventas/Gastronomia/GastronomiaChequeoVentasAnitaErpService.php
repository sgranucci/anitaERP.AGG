<?php

declare(strict_types=1);

namespace App\Services\Ventas\Gastronomia;

use App\ApiAnita;
use App\Models\Configuracion\Empresa;
use App\Models\Ventas\Puntoventa;
use App\Models\Ventas\Venta;
use App\Models\Ventas\Venta_Impuesto;
use App\Support\Ventas\GastronomiaAnitaImportEmpresaSupport;
use App\Support\Ventas\KandikoAnitaVentaTipoSupport;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Concilia cabecera venta ERP ↔ Informix (Anita) factura por factura para IVA / totales.
 */
final class GastronomiaChequeoVentasAnitaErpService
{
    private const CAMPOS_MONETARIOS = ['total', 'gravado', 'iva', 'exento'];

    /**
     * @return array{
     *   puntoventa: string,
     *   sucursal: int,
     *   fecha_jornada: string,
     *   resumen: array<string, mixed>,
     *   filas: list<array<string, mixed>>
     * }
     */
    public function chequear(
        int $puntoventaId,
        string $fechaJornada,
        float $tolerancia = 0.02,
        bool $soloDiferencias = true,
        int $limite = 0,
    ): array {
        $puntoventa = Puntoventa::query()->findOrFail($puntoventaId);
        $sucursal = $this->sucursalDesdeCodigoPuntoventa((string) $puntoventa->codigo);
        if ($sucursal <= 0) {
            throw new \InvalidArgumentException('Código de punto de venta inválido: '.$puntoventa->codigo);
        }

        $fechaEntera = (int) str_replace('-', '', $fechaJornada);
        if ($fechaEntera <= 0) {
            throw new \InvalidArgumentException('Fecha de jornada inválida: '.$fechaJornada);
        }

        $anitaPorClave = $this->listarCabecerasAnitaPorJornada($sucursal, $fechaEntera, $puntoventa);
        $ventasErp = $this->listarVentasErpPorJornada($puntoventaId, $fechaJornada);

        $filas = [];
        $clavesProcesadas = [];
        $conteo = [
            'ok' => 0,
            'diferencia' => 0,
            'solo_erp' => 0,
            'solo_anita' => 0,
            'error' => 0,
        ];

        foreach ($ventasErp as $venta) {
            $clave = $this->claveComprobanteDesdeVenta($venta);
            if ($clave === null) {
                $conteo['error']++;
                $fila = $this->filaError($venta, 'Código de comprobante ERP no reconocido');
                if (! $soloDiferencias) {
                    $filas[] = $fila;
                }

                continue;
            }

            $clavesProcesadas[$clave] = true;
            $anita = $anitaPorClave[$clave] ?? null;

            if ($anita === null) {
                $conteo['solo_erp']++;
                $fila = $this->filaBase($venta, $clave, null);
                $fila['estado'] = 'solo_erp';
                $fila['diferencias'] = ['anita' => 'Sin cabecera en Informix'];
                $filas[] = $fila;

                continue;
            }

            $erpMontos = $this->montosDesdeVentaErp($venta);
            $anitaMontos = $this->montosDesdeCabeceraAnita($anita);
            $diferencias = $this->compararMontos($erpMontos, $anitaMontos, $tolerancia);

            $fila = $this->filaBase($venta, $clave, $anita);
            $fila['erp'] = $erpMontos;
            $fila['anita'] = $anitaMontos;
            $fila['diferencias'] = $diferencias;
            $fila['estado'] = $diferencias === [] ? 'ok' : 'diferencia';

            if ($fila['estado'] === 'ok') {
                $conteo['ok']++;
            } else {
                $conteo['diferencia']++;
            }

            if (! $soloDiferencias || $fila['estado'] !== 'ok') {
                $filas[] = $fila;
            }
        }

        foreach ($anitaPorClave as $clave => $anita) {
            if (isset($clavesProcesadas[$clave])) {
                continue;
            }

            $conteo['solo_anita']++;
            $filas[] = [
                'estado' => 'solo_anita',
                'clave' => $clave,
                'codigo_erp' => null,
                'venta_id' => null,
                'tipo' => (string) ($anita->ven_tipo ?? ''),
                'numero' => (int) ($anita->ven_nro ?? 0),
                'erp' => null,
                'anita' => $this->montosDesdeCabeceraAnita($anita),
                'diferencias' => ['erp' => 'Sin venta gastronomía en ERP'],
            ];
        }

        usort($filas, function (array $a, array $b): int {
            $estadoOrder = ['diferencia' => 0, 'solo_erp' => 1, 'solo_anita' => 2, 'ok' => 3, 'error' => 4];
            $ea = $estadoOrder[$a['estado'] ?? ''] ?? 9;
            $eb = $estadoOrder[$b['estado'] ?? ''] ?? 9;
            if ($ea !== $eb) {
                return $ea <=> $eb;
            }

            return ((int) ($a['numero'] ?? 0)) <=> ((int) ($b['numero'] ?? 0));
        });

        if ($limite > 0) {
            $filas = array_slice($filas, 0, $limite);
        }

        return [
            'puntoventa' => (string) $puntoventa->codigo,
            'sucursal' => $sucursal,
            'fecha_jornada' => $fechaJornada,
            'resumen' => $this->armarResumen($ventasErp, $anitaPorClave, $conteo, $tolerancia),
            'filas' => $filas,
        ];
    }

    /**
     * @return array<string, object>
     */
    private function listarCabecerasAnitaPorJornada(int $sucursal, int $fechaEntera, ?Puntoventa $puntoventa = null): array
    {
        $api = new ApiAnita;
        $where = " WHERE ven_sucursal = '".$sucursal."'"
            ." AND ven_fecha_vto = '".$fechaEntera."'"
            ." AND ven_letra = 'B' ";

        if ($puntoventa !== null) {
            $empresaCodigo = $puntoventa->empresas?->codigo ?? $puntoventa->empresa_id;
            $where .= GastronomiaAnitaImportEmpresaSupport::whereEmpresa('ven', $empresaCodigo);
        }

        $parsed = ApiAnita::parsearRespuestaLista($api->apiCall([
            'acc' => 'list',
            'tabla' => 'venta',
            'campos' => implode(',', [
                'ven_tipo', 'ven_letra', 'ven_sucursal', 'ven_nro', 'ven_empresa',
                'ven_fecha', 'ven_fecha_vto',
                'ven_monto', 'ven_gravado', 'ven_exento', 'ven_impuesto1', 'ven_monto_desc',
            ]),
            'whereArmado' => $where,
            'orderBy' => 'ven_tipo, ven_nro',
        ]));

        if ($parsed['error_lectura'] !== null) {
            Log::warning('gastronomia.chequeo_anita.lista_jornada_fallo', [
                'sucursal' => $sucursal,
                'fecha_jornada' => $fechaEntera,
                'msg' => $parsed['error_lectura'],
            ]);

            throw new \RuntimeException(
                'No se pudo listar cabeceras Anita para la jornada: '.$parsed['error_lectura']
            );
        }

        $lista = $parsed['filas'];
        $empresaCodigo = $puntoventa !== null
            ? Empresa::query()->whereKey($puntoventa->empresa_id)->value('codigo')
            : null;
        $codigoPv = $puntoventa !== null ? (string) $puntoventa->codigo : '';
        $modoFacturacion = $puntoventa !== null ? ($puntoventa->modofacturacion ?? null) : null;
        $esKandikoCaea = $puntoventa !== null
            && KandikoAnitaVentaTipoSupport::esPvCaeaKandiko($codigoPv, $empresaCodigo, $modoFacturacion);

        $map = [];
        foreach ($lista as $fila) {
            $tipo = trim((string) ($fila->ven_tipo ?? ''));
            $nro = (int) ($fila->ven_nro ?? 0);
            if ($tipo === '' || $nro <= 0) {
                continue;
            }

            if ($puntoventa !== null
                && ! KandikoAnitaVentaTipoSupport::cabeceraAnitaCorrespondeAlPv(
                    $tipo,
                    $codigoPv,
                    $empresaCodigo,
                    $modoFacturacion,
                )) {
                continue;
            }

            if ($esKandikoCaea) {
                $clave = KandikoAnitaVentaTipoSupport::claveConciliacionDesdeNumero($nro);
                $existente = $map[$clave] ?? null;
                $tipoExistente = $existente !== null
                    ? strtoupper(trim((string) ($existente->ven_tipo ?? '')))
                    : '';
                if ($existente === null || ($tipo === KandikoAnitaVentaTipoSupport::TIPO_VENTA_BRIDGE && $tipoExistente !== KandikoAnitaVentaTipoSupport::TIPO_VENTA_BRIDGE)) {
                    $map[$clave] = $fila;
                }

                continue;
            }

            $map[$tipo.'-'.$nro] = $fila;
        }

        return $map;
    }

    /**
     * Concilia por fecha calendario de emisión (`venta.fecha`), buscando cada comprobante en Anita por clave.
     *
     * @return array{
     *   puntoventa: string,
     *   sucursal: int,
     *   fecha_calendario: string,
     *   resumen: array<string, mixed>,
     *   filas: list<array<string, mixed>>
     * }
     */
    public function chequearPorFechaCalendario(
        int $puntoventaId,
        string $fechaCalendario,
        float $tolerancia = 0.02,
        bool $soloDiferencias = true,
    ): array {
        $puntoventa = Puntoventa::query()->findOrFail($puntoventaId);
        $sucursal = $this->sucursalDesdeCodigoPuntoventa((string) $puntoventa->codigo);
        if ($sucursal <= 0) {
            throw new \InvalidArgumentException('Código de punto de venta inválido: '.$puntoventa->codigo);
        }

        $ventasErp = $this->listarVentasErpPorFechaCalendario($puntoventaId, $fechaCalendario);
        $anitaPorClave = [];

        $filas = [];
        $conteo = [
            'ok' => 0,
            'diferencia' => 0,
            'solo_erp' => 0,
            'solo_anita' => 0,
            'error' => 0,
        ];

        foreach ($ventasErp as $venta) {
            $clave = $this->claveComprobanteDesdeVenta($venta);
            if ($clave === null) {
                $conteo['error']++;
                $fila = $this->filaError($venta, 'Código de comprobante ERP no reconocido');
                if (! $soloDiferencias) {
                    $filas[] = $fila;
                }

                continue;
            }

            [$tipo, $nroStr] = explode('-', $clave, 2);
            $tipoAnita = $this->tipoAnitaConsultaDesdeErp($tipo, $puntoventa);
            $consulta = $this->consultarCabeceraAnitaPorComprobante($sucursal, $tipoAnita, (int) $nroStr);
            if ($consulta['error_lectura'] !== null) {
                $conteo['error']++;
                $fila = $this->filaBase($venta, $clave, null);
                $fila['estado'] = 'error';
                $fila['diferencias'] = [
                    'anita' => 'Error de lectura Anita (no se asume faltante): '.$consulta['error_lectura'],
                ];
                $filas[] = $fila;

                continue;
            }

            $anita = $consulta['cabecera'];
            if ($anita !== null) {
                $anitaPorClave[$clave] = $anita;
            }

            if ($anita === null) {
                $conteo['solo_erp']++;
                $fila = $this->filaBase($venta, $clave, null);
                $fila['estado'] = 'solo_erp';
                $fila['diferencias'] = ['anita' => 'Sin cabecera en Informix (búsqueda por comprobante)'];
                $filas[] = $fila;

                continue;
            }

            $erpMontos = $this->montosDesdeVentaErp($venta);
            $anitaMontos = $this->montosDesdeCabeceraAnita($anita);
            $diferencias = $this->compararMontos($erpMontos, $anitaMontos, $tolerancia);

            $fila = $this->filaBase($venta, $clave, $anita);
            $fila['erp'] = $erpMontos;
            $fila['anita'] = $anitaMontos;
            $fila['diferencias'] = $diferencias;
            $fila['estado'] = $diferencias === [] ? 'ok' : 'diferencia';

            if ($fila['estado'] === 'ok') {
                $conteo['ok']++;
            } else {
                $conteo['diferencia']++;
            }

            if (! $soloDiferencias || $fila['estado'] !== 'ok') {
                $filas[] = $fila;
            }
        }

        usort($filas, function (array $a, array $b): int {
            $estadoOrder = ['diferencia' => 0, 'solo_erp' => 1, 'solo_anita' => 2, 'ok' => 3, 'error' => 4];
            $ea = $estadoOrder[$a['estado'] ?? ''] ?? 9;
            $eb = $estadoOrder[$b['estado'] ?? ''] ?? 9;
            if ($ea !== $eb) {
                return $ea <=> $eb;
            }

            return ((int) ($a['numero'] ?? 0)) <=> ((int) ($b['numero'] ?? 0));
        });

        $resumen = $this->armarResumen($ventasErp, $anitaPorClave, $conteo, $tolerancia);
        $resumen['filtro_anita'] = 'ven_sucursal + ven_tipo + ven_nro + ven_letra=B (por comprobante)';

        return [
            'puntoventa' => (string) $puntoventa->codigo,
            'sucursal' => $sucursal,
            'fecha_calendario' => $fechaCalendario,
            'resumen' => $resumen,
            'filas' => $filas,
        ];
    }

    /**
     * Auditoría global por fecha calendario (todos los PV gastronomía de la empresa).
     *
     * @return array{
     *   fecha_calendario: string,
     *   por_puntoventa: list<array<string, mixed>>,
     *   resumen_global: array<string, mixed>
     * }
     */
    public function auditoriaPorFechaCalendario(
        string $fechaCalendario,
        int $empresaId,
        float $tolerancia = 0.02,
        ?string $codigoPv = null,
    ): array {
        $combinaciones = $this->listarCombinacionesPvFechaCalendario($fechaCalendario, $empresaId, $codigoPv);
        $porPv = [];
        $conteoGlobal = [
            'ok' => 0,
            'diferencia' => 0,
            'solo_erp' => 0,
            'solo_anita' => 0,
            'error' => 0,
        ];
        $totalesErp = ['total' => 0.0, 'gravado' => 0.0, 'iva' => 0.0, 'exento' => 0.0];
        $totalesAnita = ['total' => 0.0, 'gravado' => 0.0, 'iva' => 0.0, 'exento' => 0.0];

        foreach ($combinaciones as $combo) {
            $resultado = $this->chequearPorFechaCalendario(
                (int) $combo['puntoventa_id'],
                $fechaCalendario,
                $tolerancia,
                true,
            );
            $porPv[] = $resultado;

            $res = $resultado['resumen'];
            foreach ($conteoGlobal as $k => $_) {
                $conteoGlobal[$k] += (int) ($res['conteo'][$k] ?? 0);
            }
            foreach (self::CAMPOS_MONETARIOS as $c) {
                $totalesErp[$c] += (float) ($res['totales_erp'][$c] ?? 0);
                $totalesAnita[$c] += (float) ($res['totales_anita_signo_erp'][$c] ?? 0);
            }
        }

        foreach ($totalesErp as $k => $v) {
            $totalesErp[$k] = round($v, 2);
        }
        foreach ($totalesAnita as $k => $v) {
            $totalesAnita[$k] = round($v, 2);
        }

        $delta = [];
        foreach (self::CAMPOS_MONETARIOS as $c) {
            $delta[$c] = round($totalesErp[$c] - $totalesAnita[$c], 2);
        }

        return [
            'fecha_calendario' => $fechaCalendario,
            'por_puntoventa' => $porPv,
            'resumen_global' => [
                'puntoventas' => count($porPv),
                'ventas_erp' => array_sum(array_map(
                    fn (array $r) => (int) ($r['resumen']['ventas_erp'] ?? 0),
                    $porPv,
                )),
                'tolerancia' => $tolerancia,
                'conteo' => $conteoGlobal,
                'totales_erp' => $totalesErp,
                'totales_anita_signo_erp' => $totalesAnita,
                'delta_totales' => $delta,
                'filtro_erp' => 'venta.fecha (calendario) + gastronomia_emision',
                'filtro_anita' => 'ven_sucursal + ven_tipo + ven_nro + ven_letra=B',
            ],
        ];
    }

    /**
     * @return Collection<int, Venta>
     */
    public function listarVentasErpPorJornada(int $puntoventaId, string $fechaJornada): Collection
    {
        return Venta::query()
            ->where('puntoventa_id', $puntoventaId)
            ->whereDate('fechajornada', $fechaJornada)
            ->whereHas('gastronomiaEmision')
            ->orderBy('numerocomprobante')
            ->get(['id', 'codigo', 'numerocomprobante', 'total', 'fechajornada', 'fecha', 'tipotransaccion_id']);
    }

    /**
     * Ventas gastronomía del ERP sin cabecera en Informix para un PV y fecha de jornada.
     *
     * @return Collection<int, Venta>
     */
    public function listarVentasErpSinCabeceraAnita(int $puntoventaId, string $fechaJornada): Collection
    {
        $puntoventa = Puntoventa::query()->findOrFail($puntoventaId);
        $sucursal = $this->sucursalDesdeCodigoPuntoventa((string) $puntoventa->codigo);
        $fechaEntera = (int) str_replace('-', '', $fechaJornada);

        $anitaPorClave = $this->listarCabecerasAnitaPorJornada($sucursal, $fechaEntera, $puntoventa);
        $ventasErp = $this->listarVentasErpPorJornada($puntoventaId, $fechaJornada);

        return $ventasErp->filter(function (Venta $venta) use ($anitaPorClave): bool {
            $clave = $this->claveComprobanteDesdeVenta($venta);

            return $clave !== null && ! isset($anitaPorClave[$clave]);
        })->values();
    }

    /**
     * Combinaciones PV + fecha de jornada con ventas gastronomía en el rango indicado.
     *
     * @return list<array{puntoventa_id:int, codigo_pv:string, fecha_jornada:string}>
     */
    public function listarCombinacionesPvJornada(
        string $fechaDesde,
        ?string $fechaHasta,
        int $empresaId,
        ?string $codigoPv = null,
    ): array {
        $query = Venta::query()
            ->selectRaw('venta.puntoventa_id, DATE(venta.fechajornada) as fecha_jornada, puntoventa.codigo as codigo_pv')
            ->join('puntoventa', 'puntoventa.id', '=', 'venta.puntoventa_id')
            ->whereDate('venta.fechajornada', '>=', $fechaDesde)
            ->whereHas('gastronomiaEmision')
            ->where('puntoventa.modofacturacion', '!=', 'M')
            ->where('puntoventa.empresa_id', $empresaId)
            ->groupBy('venta.puntoventa_id', 'fecha_jornada', 'codigo_pv')
            ->orderBy('fecha_jornada')
            ->orderBy('codigo_pv');

        if ($fechaHasta !== null && $fechaHasta !== '') {
            $query->whereDate('venta.fechajornada', '<=', $fechaHasta);
        }

        if ($codigoPv !== null && trim($codigoPv) !== '') {
            $query->where('puntoventa.codigo', trim($codigoPv));
        }

        $filas = [];
        foreach ($query->get() as $row) {
            $filas[] = [
                'puntoventa_id' => (int) $row->puntoventa_id,
                'codigo_pv' => (string) $row->codigo_pv,
                'fecha_jornada' => (string) $row->fecha_jornada,
            ];
        }

        return $filas;
    }

    /**
     * @return Collection<int, Venta>
     */
    public function listarVentasErpPorFechaCalendario(int $puntoventaId, string $fechaCalendario): Collection
    {
        return Venta::query()
            ->where('puntoventa_id', $puntoventaId)
            ->whereDate('fecha', $fechaCalendario)
            ->whereHas('gastronomiaEmision')
            ->orderBy('numerocomprobante')
            ->get(['id', 'codigo', 'numerocomprobante', 'total', 'fechajornada', 'fecha', 'tipotransaccion_id']);
    }

    /**
     * @return Collection<int, Venta>
     */
    public function listarVentasErpSinCabeceraAnitaPorFechaCalendario(int $puntoventaId, string $fechaCalendario): Collection
    {
        $puntoventa = Puntoventa::query()->findOrFail($puntoventaId);
        $sucursal = $this->sucursalDesdeCodigoPuntoventa((string) $puntoventa->codigo);

        return $this->listarVentasErpPorFechaCalendario($puntoventaId, $fechaCalendario)
            ->filter(function (Venta $venta) use ($sucursal, $puntoventa): bool {
                $clave = $this->claveComprobanteDesdeVenta($venta);
                if ($clave === null) {
                    return false;
                }
                [$tipo, $nroStr] = explode('-', $clave, 2);
                $tipoAnita = $this->tipoAnitaConsultaDesdeErp($tipo, $puntoventa);
                $consulta = $this->consultarCabeceraAnitaPorComprobante($sucursal, $tipoAnita, (int) $nroStr);
                if ($consulta['error_lectura'] !== null) {
                    Log::warning('gastronomia.chequeo_anita.omitir_faltante_lectura_fallida', [
                        'venta_id' => $venta->id,
                        'codigo' => $venta->codigo,
                        'msg' => $consulta['error_lectura'],
                    ]);

                    return false;
                }

                return $consulta['cabecera'] === null;
            })
            ->values();
    }

    /**
     * @return list<array{puntoventa_id:int, codigo_pv:string, fecha_calendario:string}>
     */
    public function listarCombinacionesPvFechaCalendario(
        string $fechaCalendario,
        int $empresaId,
        ?string $codigoPv = null,
    ): array {
        $query = Venta::query()
            ->selectRaw('venta.puntoventa_id, DATE(venta.fecha) as fecha_calendario, puntoventa.codigo as codigo_pv')
            ->join('puntoventa', 'puntoventa.id', '=', 'venta.puntoventa_id')
            ->whereDate('venta.fecha', $fechaCalendario)
            ->whereHas('gastronomiaEmision')
            ->where('puntoventa.modofacturacion', '!=', 'M')
            ->where('puntoventa.empresa_id', $empresaId)
            ->groupBy('venta.puntoventa_id', 'fecha_calendario', 'codigo_pv')
            ->orderBy('codigo_pv');

        if ($codigoPv !== null && trim($codigoPv) !== '') {
            $query->where('puntoventa.codigo', trim($codigoPv));
        }

        $filas = [];
        foreach ($query->get() as $row) {
            $filas[] = [
                'puntoventa_id' => (int) $row->puntoventa_id,
                'codigo_pv' => (string) $row->codigo_pv,
                'fecha_calendario' => (string) $row->fecha_calendario,
            ];
        }

        return $filas;
    }

    public function leerCabeceraAnitaPorComprobante(
        int $sucursal,
        string $tipo,
        int $numero,
        string $letra = 'B',
    ): ?object {
        return $this->consultarCabeceraAnitaPorComprobante($sucursal, $tipo, $numero, $letra)['cabecera'];
    }

    /**
     * @return array{cabecera: ?object, error_lectura: ?string}
     */
    public function consultarCabeceraAnitaPorComprobante(
        int $sucursal,
        string $tipo,
        int $numero,
        string $letra = 'B',
    ): array {
        if ($sucursal <= 0 || $tipo === '' || $numero <= 0) {
            return ['cabecera' => null, 'error_lectura' => null];
        }

        $api = new ApiAnita;
        $where = " WHERE ven_sucursal = '".$sucursal."'"
            ." AND ven_tipo = '".addslashes($tipo)."'"
            ." AND ven_nro = '".$numero."'"
            ." AND ven_letra = '".addslashes($letra)."' ";

        $parsed = ApiAnita::parsearRespuestaLista($api->apiCall([
            'acc' => 'list',
            'tabla' => 'venta',
            'campos' => implode(',', [
                'ven_tipo', 'ven_letra', 'ven_sucursal', 'ven_nro',
                'ven_fecha', 'ven_fecha_vto',
                'ven_monto', 'ven_gravado', 'ven_exento', 'ven_impuesto1', 'ven_monto_desc',
            ]),
            'whereArmado' => $where,
        ]));

        return [
            'cabecera' => $parsed['filas'][0] ?? null,
            'error_lectura' => $parsed['error_lectura'],
        ];
    }

    /**
     * @return array{cabecera: ?object, error_lectura: ?string}
     */
    public function claveComprobanteDesdeVentaErp(Venta $venta): ?string
    {
        return $this->claveComprobanteDesdeVenta($venta);
    }

    /**
     * @return array{
     *   estado: string,
     *   erp: array{total: float, gravado: float, iva: float, exento: float},
     *   anita: ?array{total: float, gravado: float, iva: float, exento: float},
     *   diferencias: array<string, string>
     * }
     */
    public function conciliarVentaConCabeceraAnita(Venta $venta, ?object $cabeceraAnita, float $tolerancia = 0.02): array
    {
        $erpMontos = $this->montosDesdeVentaErp($venta);

        if ($cabeceraAnita === null) {
            return [
                'estado' => 'solo_erp',
                'erp' => $erpMontos,
                'anita' => null,
                'diferencias' => ['anita' => 'Sin cabecera en Informix'],
            ];
        }

        $anitaMontos = $this->montosDesdeCabeceraAnita($cabeceraAnita);
        $diferencias = $this->compararMontos($erpMontos, $anitaMontos, $tolerancia);

        return [
            'estado' => $diferencias === [] ? 'ok' : 'diferencia',
            'erp' => $erpMontos,
            'anita' => $anitaMontos,
            'diferencias' => $diferencias,
        ];
    }

    public function consultarCabeceraAnitaDesdeVenta(Venta $venta, string $letra = 'B'): array
    {
        $venta->loadMissing('puntoventas');
        $puntoventa = $venta->puntoventas;
        if (! $puntoventa) {
            return ['cabecera' => null, 'error_lectura' => 'Punto de venta no encontrado'];
        }

        $clave = $this->claveComprobanteDesdeVenta($venta);
        if ($clave === null) {
            return ['cabecera' => null, 'error_lectura' => 'Código de comprobante ERP no reconocido'];
        }

        [$tipo, $nroStr] = explode('-', $clave, 2);
        $sucursal = $this->sucursalDesdeCodigoPuntoventa((string) $puntoventa->codigo);
        $tipoAnita = $this->tipoAnitaConsultaDesdeErp($tipo, $puntoventa);

        return $this->consultarCabeceraAnitaPorComprobante($sucursal, $tipoAnita, (int) $nroStr, $letra);
    }

    private function tipoAnitaConsultaDesdeErp(string $tipoErp, Puntoventa $puntoventa): string
    {
        $empresaCodigo = Empresa::query()->whereKey($puntoventa->empresa_id)->value('codigo');

        return KandikoAnitaVentaTipoSupport::tipoVentaAnitaBridge(
            $tipoErp,
            (string) $puntoventa->codigo,
            $empresaCodigo,
            $puntoventa->modofacturacion ?? null,
        );
    }

    /**
     * Montos ERP alineados con Anita (ven_gravado / ven_exento / ven_impuesto1).
     *
     * Gastronomía suele grabar el neto en «Subtotal» sin renglón «Gravado al …»;
     * las cortesías $0,01 llevan exento y Subtotal = bruto pre-descuento (no es gravado).
     *
     * @return array{total: float, gravado: float, iva: float, exento: float}
     */
    private function montosDesdeVentaErp(Venta $venta): array
    {
        $impuestos = Venta_Impuesto::query()
            ->where('venta_id', (int) $venta->id)
            ->get(['concepto', 'importe', 'baseimponible']);

        $gravadoAl = 0.0;
        $subtotalConcepto = 0.0;
        $iva = 0.0;
        $baseIva = 0.0;
        $exento = 0.0;

        foreach ($impuestos as $row) {
            $concepto = trim((string) ($row->concepto ?? ''));
            $importe = round((float) ($row->importe ?? 0), 2);
            $base = round((float) ($row->baseimponible ?? 0), 2);

            if (preg_match('/^Gravado/i', $concepto)) {
                $gravadoAl += $importe;
            } elseif ($concepto === 'Subtotal') {
                $subtotalConcepto += $importe;
            } elseif (preg_match('/^Iva/i', $concepto)) {
                $iva += $importe;
                $baseIva += $base;
            } elseif (stripos($concepto, 'exento') !== false) {
                $exento += $importe;
            }
        }

        $total = round((float) $venta->total, 2);
        $esCortesiaMinima = abs(abs($total) - 0.01) <= 0.02;

        if ($gravadoAl > 0.) {
            $gravado = $gravadoAl;
        } elseif ($esCortesiaMinima) {
            $gravado = 0.0;
        } elseif ($baseIva > 0.) {
            $gravado = $baseIva;
        } elseif ($subtotalConcepto > 0. && $iva > 0.) {
            // Neto gravado gastronomía (equivale a ven_gravado cuando no hay «Gravado al»).
            $gravado = $subtotalConcepto;
        } else {
            $gravado = 0.0;
        }

        return [
            'total' => $total,
            'gravado' => round($gravado, 2),
            'iva' => round($iva, 2),
            'exento' => round($exento, 2),
        ];
    }

    /**
     * @return array{total: float, gravado: float, iva: float, exento: float}
     */
    private function montosDesdeCabeceraAnita(object $cab): array
    {
        return [
            'total' => round((float) ($cab->ven_monto ?? 0), 2),
            'gravado' => round((float) ($cab->ven_gravado ?? 0), 2),
            'iva' => round((float) ($cab->ven_impuesto1 ?? 0), 2),
            'exento' => round((float) ($cab->ven_exento ?? 0), 2),
        ];
    }

    /**
     * @param  array{total: float, gravado: float, iva: float, exento: float}  $erp
     * @param  array{total: float, gravado: float, iva: float, exento: float}  $anita
     * @return array<string, string>
     */
    private function compararMontos(array $erp, array $anita, float $tolerancia): array
    {
        $diffs = [];

        foreach (self::CAMPOS_MONETARIOS as $campo) {
            $ev = (float) ($erp[$campo] ?? 0);
            $av = (float) ($anita[$campo] ?? 0);

            if ($this->coincideMonetario($ev, $av, $tolerancia)) {
                continue;
            }

            $diffs[$campo] = 'ERP '.$this->fmt($ev).' vs Anita '.$this->fmt($av);
        }

        return $diffs;
    }

    private function coincideMonetario(float $erp, float $anita, float $tolerancia): bool
    {
        if (abs($erp - $anita) <= $tolerancia) {
            return true;
        }

        // NC: ERP negativo, Anita positivo (convención Informix).
        return abs(abs($erp) - abs($anita)) <= $tolerancia;
    }

    private function claveComprobanteDesdeVenta(Venta $venta): ?string
    {
        $codigo = trim((string) ($venta->codigo ?? ''));
        if (preg_match('/^(\S+)\s+[A-Z]-\d+-(\d+)$/', $codigo, $m)) {
            return $m[1].'-'.(int) $m[2];
        }

        if ((int) ($venta->numerocomprobante ?? 0) <= 0) {
            return null;
        }

        return 'FAC-'.(int) $venta->numerocomprobante;
    }

    /**
     * @return array<string, mixed>
     */
    private function filaBase(Venta $venta, string $clave, ?object $anita): array
    {
        [$tipo, $nro] = explode('-', $clave, 2);

        return [
            'estado' => 'ok',
            'clave' => $clave,
            'codigo_erp' => (string) $venta->codigo,
            'venta_id' => (int) $venta->id,
            'tipo' => $tipo,
            'numero' => (int) $nro,
            'ven_fecha_anita' => $anita !== null ? (string) ($anita->ven_fecha ?? '') : null,
            'erp' => null,
            'anita' => null,
            'diferencias' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function filaError(Venta $venta, string $mensaje): array
    {
        return [
            'estado' => 'error',
            'clave' => null,
            'codigo_erp' => (string) $venta->codigo,
            'venta_id' => (int) $venta->id,
            'tipo' => null,
            'numero' => (int) ($venta->numerocomprobante ?? 0),
            'erp' => $this->montosDesdeVentaErp($venta),
            'anita' => null,
            'diferencias' => ['parse' => $mensaje],
        ];
    }

    /**
     * @param  Collection<int, Venta>  $ventasErp
     * @param  array<string, object>  $anitaPorClave
     * @param  array<string, int>  $conteo
     * @return array<string, mixed>
     */
    private function armarResumen(
        Collection $ventasErp,
        array $anitaPorClave,
        array $conteo,
        float $tolerancia,
    ): array {
        $totalesErp = ['total' => 0.0, 'gravado' => 0.0, 'iva' => 0.0, 'exento' => 0.0];
        foreach ($ventasErp as $venta) {
            $m = $this->montosDesdeVentaErp($venta);
            foreach (self::CAMPOS_MONETARIOS as $c) {
                $totalesErp[$c] += $m[$c];
            }
        }
        foreach ($totalesErp as $k => $v) {
            $totalesErp[$k] = round($v, 2);
        }

        $totalesAnitaBruto = ['total' => 0.0, 'gravado' => 0.0, 'iva' => 0.0, 'exento' => 0.0];
        $totalesAnitaSignoErp = ['total' => 0.0, 'gravado' => 0.0, 'iva' => 0.0, 'exento' => 0.0];
        foreach ($anitaPorClave as $cab) {
            $m = $this->montosDesdeCabeceraAnita($cab);
            $esNc = $this->esNotaCreditoAnita($cab);
            foreach (self::CAMPOS_MONETARIOS as $c) {
                $totalesAnitaBruto[$c] += $m[$c];
                $totalesAnitaSignoErp[$c] += $esNc ? -abs($m[$c]) : $m[$c];
            }
        }
        foreach ($totalesAnitaBruto as $k => $v) {
            $totalesAnitaBruto[$k] = round($v, 2);
        }
        foreach ($totalesAnitaSignoErp as $k => $v) {
            $totalesAnitaSignoErp[$k] = round($v, 2);
        }

        return [
            'ventas_erp' => $ventasErp->count(),
            'cabeceras_anita' => count($anitaPorClave),
            'tolerancia' => $tolerancia,
            'conteo' => $conteo,
            'totales_erp' => $totalesErp,
            'totales_anita_bruto' => $totalesAnitaBruto,
            'totales_anita_signo_erp' => $totalesAnitaSignoErp,
            'delta_totales' => [
                'total' => round($totalesErp['total'] - $totalesAnitaSignoErp['total'], 2),
                'gravado' => round($totalesErp['gravado'] - $totalesAnitaSignoErp['gravado'], 2),
                'iva' => round($totalesErp['iva'] - $totalesAnitaSignoErp['iva'], 2),
                'exento' => round($totalesErp['exento'] - $totalesAnitaSignoErp['exento'], 2),
            ],
            'filtro_anita' => 'ven_sucursal + ven_fecha_vto (fecha jornada) + ven_letra=B',
        ];
    }

    /**
     * Facturación bruta Anita (cabeceras venta, sin NC) por PV y fecha de jornada.
     */
    public function totalFacturacionBrutaAnitaPorJornada(int $puntoventaId, string $fechaJornada): float
    {
        $puntoventa = Puntoventa::query()->findOrFail($puntoventaId);
        $sucursal = $this->sucursalDesdeCodigoPuntoventa((string) $puntoventa->codigo);
        $fechaEntera = (int) str_replace('-', '', $fechaJornada);
        if ($sucursal <= 0 || $fechaEntera <= 0) {
            return 0.0;
        }

        $cabeceras = $this->listarCabecerasAnitaPorJornada($sucursal, $fechaEntera, $puntoventa);
        $total = 0.0;
        foreach ($cabeceras as $cab) {
            if ($this->esNotaCreditoAnita($cab)) {
                continue;
            }
            $total += (float) ($cab->ven_monto ?? 0);
        }

        return round($total, 2);
    }

    /**
     * Suma ven_monto Anita de facturas ERP (sin NC) emparejadas por clave comprobante.
     *
     * @param  list<int>  $ventaIds
     * @param  array<int, array<string, object>>  $cacheCabecerasPorPv
     */
    public function totalFacturacionBrutaAnitaParaVentasIds(
        array $ventaIds,
        string $fechaJornada,
        array &$cacheCabecerasPorPv = [],
    ): float {
        if ($ventaIds === []) {
            return 0.0;
        }

        $ventas = Venta::query()
            ->whereIn('id', $ventaIds)
            ->get(['id', 'codigo', 'numerocomprobante', 'puntoventa_id']);

        $total = 0.0;
        foreach ($ventas as $venta) {
            $clave = $this->claveComprobanteDesdeVenta($venta);
            if ($clave === null) {
                continue;
            }

            $pvId = (int) ($venta->puntoventa_id ?? 0);
            if ($pvId <= 0) {
                continue;
            }

            if (! isset($cacheCabecerasPorPv[$pvId])) {
                $cacheCabecerasPorPv[$pvId] = $this->cabecerasAnitaMapPorPuntoventa($pvId, $fechaJornada);
            }

            $cab = $cacheCabecerasPorPv[$pvId][$clave] ?? null;
            if ($cab === null || $this->esNotaCreditoAnita($cab)) {
                continue;
            }

            $total += (float) ($cab->ven_monto ?? 0);
        }

        return round($total, 2);
    }

    /**
     * @return array<string, object>
     */
    public function cabecerasAnitaMapPorPuntoventa(int $puntoventaId, string $fechaJornada): array
    {
        $puntoventa = Puntoventa::query()->with('empresas')->find($puntoventaId);
        if ($puntoventa === null) {
            return [];
        }

        $sucursal = $this->sucursalDesdeCodigoPuntoventa((string) $puntoventa->codigo);
        $fechaEntera = (int) str_replace('-', '', $fechaJornada);
        if ($sucursal <= 0 || $fechaEntera <= 0) {
            return [];
        }

        return $this->listarCabecerasAnitaPorJornada($sucursal, $fechaEntera, $puntoventa);
    }

    private function esNotaCreditoAnita(object $cab): bool
    {
        $tipo = strtoupper(trim((string) ($cab->ven_tipo ?? '')));

        return str_starts_with($tipo, 'NC') || (float) ($cab->ven_monto ?? 0) < 0;
    }

    private function fmt(float $valor): string
    {
        return number_format($valor, 2, '.', '');
    }

    private function sucursalDesdeCodigoPuntoventa(string $codigo): int
    {
        return (int) preg_replace('/\D+/', '', trim($codigo));
    }
}
