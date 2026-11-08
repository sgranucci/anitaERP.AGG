<?php

namespace App\Support\Caja;

use App\Models\Caja\TesoreriaCrownBingo;
use App\Models\Caja\TesoreriaCrownBoveda;
use App\Models\Caja\TesoreriaCrownDeposito;
use App\Models\Caja\TesoreriaCrownJornada;
use App\Models\Caja\TesoreriaCrownMesa;
use App\Models\Caja\TesoreriaCrownMesaJuego;
use App\Models\Caja\TesoreriaCrownSlot;
use App\Models\Caja\TesoreriaCrownValor;

/**
 * Anexo II / l-resmesa.c: resultado del día.
 *
 * Opciones de Anita (help / in_opcion):
 *   1 mesas        → solo mesas (+ bingo si hubiera)
 *   2 gastronomia  → solo gastro/hotel
 *   3 todo         → mesas + slots + ruleta + bingo + gastro
 *
 * Acá además se ofrece `maquinas` (bloque SLOTS) para pedirlo aparte.
 */
final class TesoreriaCrownAnexo2Calculo
{
    public const GRUPO_MESAS = 'mesas';

    public const GRUPO_MAQUINAS = 'maquinas';

    public const GRUPO_GASTRONOMIA = 'gastronomia';

    public const GRUPO_TODO = 'todo';

    /** @var array<string, string> */
    public const GRUPOS = [
        self::GRUPO_MESAS => 'Mesas',
        self::GRUPO_MAQUINAS => 'Máquinas',
        self::GRUPO_GASTRONOMIA => 'Gastronomía',
        self::GRUPO_TODO => 'Todo',
    ];

    /** @var array<string, string> slug de permiso por grupo del selector */
    public const PERMISOS_GRUPO = [
        self::GRUPO_MESAS => 'listar-tesoreria-crown-anexo2-mesas',
        self::GRUPO_MAQUINAS => 'listar-tesoreria-crown-anexo2-maquinas',
        self::GRUPO_GASTRONOMIA => 'listar-tesoreria-crown-anexo2-gastronomia',
        self::GRUPO_TODO => 'listar-tesoreria-crown-anexo2-todo',
    ];

    public static function permisoDeGrupo(string $grupo): ?string
    {
        return self::PERMISOS_GRUPO[self::resolverGrupo($grupo)] ?? null;
    }

    /**
     * Grupos que el usuario puede ver en el selector (según permiso-rol).
     *
     * @return array<string, string>
     */
    public static function gruposPermitidos(): array
    {
        $out = [];
        foreach (self::GRUPOS as $clave => $etiqueta) {
            $slug = self::PERMISOS_GRUPO[$clave] ?? null;
            if ($slug !== null && can($slug, false)) {
                $out[$clave] = $etiqueta;
            }
        }

        return $out;
    }

    public static function resolverGrupo(?string $grupo): string
    {
        $grupo = strtolower(trim((string) $grupo));

        return array_key_exists($grupo, self::GRUPOS) ? $grupo : self::GRUPO_TODO;
    }

    /**
     * Resuelve el grupo pedido si está permitido; si viene vacío, el primero permitido.
     * Sin ningún permiso de grupo → null.
     */
    public static function resolverGrupoPermitido(?string $grupo): ?string
    {
        $permitidos = self::gruposPermitidos();
        if ($permitidos === []) {
            return null;
        }
        $pedido = strtolower(trim((string) $grupo));
        if ($pedido !== '' && array_key_exists($pedido, $permitidos)) {
            return $pedido;
        }
        if ($pedido === '' || $pedido === self::GRUPO_TODO) {
            return array_key_first($permitidos);
        }

        return null;
    }

    /**
     * @return array{
     *     grupo: string,
     *     grupos: array<string, string>,
     *     secciones: list<array<string, mixed>>,
     *     filas: list<array<string, mixed>>,
     *     totales: array<string, float>,
     *     avisos: list<string>
     * }
     */
    public static function deJornada(TesoreriaCrownJornada $jornada, ?string $grupo = null): array
    {
        $grupo = self::resolverGrupo($grupo);
        $avisos = [];
        $secciones = [];

        if (in_array($grupo, [self::GRUPO_MESAS, self::GRUPO_TODO], true)) {
            $secciones[] = self::seccionMesas($jornada, $avisos);
            $secciones[] = self::seccionBingo($jornada, $avisos);
        }
        if (in_array($grupo, [self::GRUPO_MAQUINAS, self::GRUPO_TODO], true)) {
            $secciones[] = self::seccionMaquinas($jornada, $avisos);
        }
        if (in_array($grupo, [self::GRUPO_GASTRONOMIA, self::GRUPO_TODO], true)) {
            $secciones[] = self::seccionGastronomia($jornada, $avisos);
        }
        if ($grupo === self::GRUPO_TODO) {
            $avisos[] = 'Ruleta electrónica: N/A en CIP (sin movimiento en octubre; no se opera en el ERP).';
        }

        $mesas = null;
        foreach ($secciones as $seccion) {
            if (($seccion['clave'] ?? '') === self::GRUPO_MESAS) {
                $mesas = $seccion;
                break;
            }
        }

        return [
            'grupo' => $grupo,
            'grupos' => self::gruposPermitidos(),
            'secciones' => $secciones,
            'filas' => $mesas['filas'] ?? [],
            'totales' => $mesas['totales'] ?? self::cerosMesas(),
            'avisos' => array_values(array_unique($avisos)),
        ];
    }

    /**
     * @param  list<string>  $avisos
     * @return array<string, mixed>
     */
    private static function seccionMesas(TesoreriaCrownJornada $jornada, array &$avisos): array
    {
        $fecha = $jornada->fecha->format('Y-m-d');
        $salaId = (int) $jornada->sala_id;
        $tablero = TesoreriaCrownMesaSupport::tablero($jornada);
        $valores = TesoreriaCrownValor::query()
            ->where('sala_id', $salaId)
            ->get(['id', 'denominacion'])
            ->keyBy('id');

        $tiposJuego = self::tiposJuegoPorMesaErp((int) $jornada->sala_id);
        if ($tiposJuego === []) {
            $avisos[] = 'Sin tipos de juego ERP (desc_tipojuego): sincronizá con tesoreria-crown:sincronizar-tipos-juego-mesa-anita.';
        }

        // MTD mesas = solo jornadas ERP del 1º al día.
        $desdeMes = $jornada->fecha->copy()->startOfMonth()->format('Y-m-d');
        $fechasErp = TesoreriaCrownJornada::query()
            ->where('sala_id', $salaId)
            ->whereDate('fecha', '>=', $desdeMes)
            ->whereDate('fecha', '<=', $fecha)
            ->orderBy('fecha')
            ->pluck('fecha')
            ->map(fn ($f) => $f instanceof \DateTimeInterface ? (int) $f->format('Ymd') : (int) str_replace('-', '', substr((string) $f, 0, 10)))
            ->unique()
            ->values()
            ->all();
        $acumErp = self::acumuladoMesasErp($jornada, $valores);
        $diasMes = (int) $jornada->fecha->day;
        if (count($fechasErp) < $diasMes) {
            $avisos[] = 'Acumulado de mesas incompleto: hay '.count($fechasErp)
                .' jornada(s) ERP de '.$diasMes.' día(s) del mes. Abrí o importá los días faltantes.';
        }

        $porJuego = [];
        foreach ($tablero['mesas'] as $filaTablero) {
            $mesa = $filaTablero['mesa'];
            $mesaNro = self::numeroMesa((string) $mesa->codigo);
            $montos = self::montosMesa((int) $mesa->id, $fecha, $valores);
            $acumMontos = $acumErp[(int) $mesa->id] ?? self::cerosMontos();
            // Completa banca tipo del día si faltó el alta A pero hay stock de apertura.
            if (abs($montos['habilitacion']) < 0.001 && self::tieneMovimiento($montos)) {
                $stock = TesoreriaCrownMesaSupport::stock((int) $mesa->id, $salaId, $fecha);
                $montos['habilitacion'] = self::montoDeCantidades($stock['apertura'], $valores);
            }
            if (! self::tieneMovimiento($montos) && ! self::tieneMovimiento($acumMontos)) {
                continue;
            }
            // l-resmesa corta por mesaj_desc_tipoj; la banca vigente es otra cosa (fichas).
            $juego = trim((string) ($tiposJuego[$mesaNro] ?? ''));
            if ($juego === '') {
                $juego = self::juegoDesdeDeposito((string) $mesa->nombre, (string) $mesa->codigo);
            }
            $porJuego[$juego][] = self::filaMesa(
                $juego,
                $mesaNro > 0 ? (string) $mesaNro : (string) $mesa->codigo,
                $montos,
                $acumMontos
            );
        }

        ksort($porJuego, SORT_NATURAL | SORT_FLAG_CASE);
        $bloques = [];
        $filas = [];
        $totales = self::cerosMesas();
        foreach ($porJuego as $juego => $filasJuego) {
            usort($filasJuego, fn (array $a, array $b) => strnatcasecmp((string) $a['mesa'], (string) $b['mesa']));
            $sub = self::cerosMesas();
            foreach ($filasJuego as $i => $fila) {
                if ($i > 0) {
                    $fila['juego'] = '';
                }
                $filas[] = $fila;
                foreach (array_keys($sub) as $clave) {
                    if (! is_numeric($fila[$clave] ?? null)) {
                        continue;
                    }
                    $sub[$clave] = round($sub[$clave] + (float) $fila[$clave], 2);
                    $totales[$clave] = round($totales[$clave] + (float) $fila[$clave], 2);
                }
            }
            $subtotal = self::filaCorte('Subtotal', $sub, true, false);
            $filas[] = $subtotal;
            $bloques[] = [
                'titulo' => $juego,
                'filas' => $filasJuego,
                'totales' => $sub,
            ];
        }

        if ($filas === []) {
            $avisos[] = 'No hay movimientos de mesa confirmados en esta jornada.';
        } else {
            $filas[] = self::filaCorte('Total final', $totales, false, true);
        }

        $propina = round((float) TesoreriaCrownMesa::query()
            ->where('jornada_id', $jornada->id)
            ->where('tipo', 'F')
            ->where('estado', TesoreriaCrownMesa::CONFIRMADO)
            ->whereNull('revierte_id')
            ->sum('propina'), 2);

        return [
            'clave' => self::GRUPO_MESAS,
            'titulo' => 'Mesas',
            'tipo' => 'mesas',
            'bloques' => $bloques,
            'filas' => $filas,
            'totales' => $totales,
            'resumen' => self::resumenMesas($jornada, $totales),
            'extras' => [
                'propina_dia' => $propina,
                'venta_mesas' => $totales['drop'],
                'pct_utilidad' => abs($totales['drop']) > 0.001
                    ? round($totales['resultado'] / $totales['drop'] * 100, 2)
                    : null,
                'pct_propina' => abs($totales['drop']) > 0.001
                    ? round($totales['caja_empleado'] / $totales['drop'] * 100, 2)
                    : null,
                'acum_venta_mesas' => $totales['acum_drop'],
                'acum_pct_utilidad' => abs($totales['acum_drop']) > 0.001
                    ? round($totales['acum_resultado'] / $totales['acum_drop'] * 100, 2)
                    : null,
                'acum_pct_propina' => abs($totales['acum_drop']) > 0.001
                    ? round($totales['acum_caja_empleado'] / $totales['acum_drop'] * 100, 2)
                    : null,
            ],
        ];
    }

    /**
     * Pie de mesas de l-resmesa (propinas, ventas, pagos, %).
     *
     * @param  array<string, float>  $totales
     * @return list<array{concepto: string, diario: float|string|null, acumulado: float|string|null, es_pct?: bool}>
     */
    private static function resumenMesas(TesoreriaCrownJornada $jornada, array $totales): array
    {
        $resumenCajero = TesoreriaCrownResumenCajeroCalculo::deJornada($jornada);
        $propinasCaja = 0.0;
        $pagosFichasCajero = 0.0;
        $ventaCaja = 0.0;
        foreach ($resumenCajero['grupos'] as $grupo) {
            $tipo = (string) ($grupo['tipo'] ?? '');
            if ($tipo === 'E') {
                $propinasCaja = round((float) ($grupo['totales']['ingresos'] ?? 0), 2);
            }
            // tot_pago Anita ≈ rendición/pase de fichas (K/T/G/…).
            $pagosFichasCajero = round(
                $pagosFichasCajero + (float) ($grupo['totales']['fichas_casino'] ?? 0),
                2
            );
        }

        $cajaEmpleado = (float) $totales['caja_empleado'];
        $pagoManual = (float) $totales['pagos_manuales'];
        $ventaMesas = (float) $totales['drop'];
        $resultado = (float) $totales['resultado'];
        $totalPropinas = round($cajaEmpleado + $propinasCaja, 2);
        $pagosFichas = round($pagosFichasCajero + $cajaEmpleado, 2);
        $totalVentas = round($ventaCaja + $ventaMesas, 2);
        $totalPagos = round($pagosFichas + $pagoManual, 2);

        $acumCaja = (float) $totales['acum_caja_empleado'];
        $acumDrop = (float) $totales['acum_drop'];
        $acumResultado = (float) $totales['acum_resultado'];
        $acumPagoManual = (float) $totales['acum_pagos_manuales'];
        // Sin histórico de cajero en ERP: el acumulado de propina/caja y pagos fichas
        // usa al menos la propina de mesa (Anita suma también propina cajero MTD).
        $acumTotalPropinas = $acumCaja;
        $acumPagosFichas = $acumCaja;
        $acumTotalVentas = $acumDrop;
        $acumTotalPagos = round($acumPagosFichas + $acumPagoManual, 2);

        $pct = static function (float $num, float $den): ?float {
            return abs($den) > 0.001 ? round($num / $den * 100, 2) : null;
        };

        return [
            ['concepto' => 'Propinas por caja', 'diario' => $propinasCaja, 'acumulado' => null],
            ['concepto' => 'Total propinas', 'diario' => $totalPropinas, 'acumulado' => $acumTotalPropinas],
            ['concepto' => 'Sub-total de venta por caja', 'diario' => $ventaCaja, 'acumulado' => 0.0],
            ['concepto' => 'Total pagos fichas', 'diario' => $pagosFichas, 'acumulado' => $acumPagosFichas],
            ['concepto' => 'Sub-total de venta en mesas', 'diario' => $ventaMesas, 'acumulado' => $acumDrop],
            ['concepto' => 'Total pago manual', 'diario' => $pagoManual, 'acumulado' => $acumPagoManual],
            ['concepto' => 'Total ventas', 'diario' => $totalVentas, 'acumulado' => $acumTotalVentas],
            ['concepto' => 'Total pagos', 'diario' => $totalPagos, 'acumulado' => $acumTotalPagos],
            [
                'concepto' => 'Porc. utilidad mesas s/venta',
                'diario' => $pct($resultado, $totalVentas),
                'acumulado' => $pct($acumResultado, $acumTotalVentas),
                'es_pct' => true,
            ],
            [
                'concepto' => 'Porc. propina mesas s/venta',
                'diario' => $pct($cajaEmpleado, $totalVentas),
                'acumulado' => $pct($acumCaja, $acumTotalVentas),
                'es_pct' => true,
            ],
        ];
    }

    /**
     * @param  array{habilitacion: float, reposicion: float, credito: float, estado_final: float, drop: float, caja_empleado: float, pago_manual: float}  $montos
     * @param  array{habilitacion: float, reposicion: float, credito: float, estado_final: float, drop: float, caja_empleado: float, pago_manual: float}  $acum
     * @return array<string, mixed>
     */
    private static function filaMesa(string $juego, string $mesa, array $montos, array $acum): array
    {
        $diario = self::derivados($montos);
        $acumulado = self::derivados($acum);

        return [
            'juego' => $juego,
            'mesa' => $mesa,
            'banca_tipo' => $montos['habilitacion'],
            'creditos' => $montos['credito'],
            'reposiciones' => $montos['reposicion'],
            'estado_final' => $montos['estado_final'],
            'drop' => $montos['drop'],
            'pagos_manuales' => $montos['pago_manual'],
            'recomp_banca' => $diario['recomp'],
            'caja_empleado' => $montos['caja_empleado'],
            'resultado' => $diario['resultado'],
            'pct_mesa' => $diario['pct_mesa'],
            'pct_prop' => $diario['pct_prop'],
            'acum_estado_final' => $acum['estado_final'],
            'acum_drop' => $acum['drop'],
            'acum_pagos_manuales' => $acum['pago_manual'],
            'acum_recomp_banca' => $acumulado['recomp'],
            'acum_caja_empleado' => $acum['caja_empleado'],
            'acum_resultado' => $acumulado['resultado'],
            'acum_pct_mesa' => $acumulado['pct_mesa'],
            'acum_pct_prop' => $acumulado['pct_prop'],
            'es_subtotal' => false,
            'es_total' => false,
        ];
    }

    /**
     * @param  array<string, float>  $totales
     * @return array<string, mixed>
     */
    private static function filaCorte(string $etiqueta, array $totales, bool $esSubtotal, bool $esTotal): array
    {
        return [
            'juego' => $etiqueta,
            'mesa' => '',
            'banca_tipo' => $totales['banca_tipo'],
            'creditos' => $totales['creditos'],
            'reposiciones' => $totales['reposiciones'],
            'estado_final' => $totales['estado_final'],
            'drop' => $totales['drop'],
            'pagos_manuales' => $totales['pagos_manuales'],
            'recomp_banca' => $totales['recomp_banca'],
            'caja_empleado' => $totales['caja_empleado'],
            'resultado' => $totales['resultado'],
            'pct_mesa' => abs($totales['drop']) > 0.001 ? round($totales['resultado'] / $totales['drop'] * 100, 2) : null,
            'pct_prop' => abs($totales['drop']) > 0.001 ? round($totales['caja_empleado'] / $totales['drop'] * 100, 2) : null,
            'acum_estado_final' => $totales['acum_estado_final'],
            'acum_drop' => $totales['acum_drop'],
            'acum_pagos_manuales' => $totales['acum_pagos_manuales'],
            'acum_recomp_banca' => $totales['acum_recomp_banca'],
            'acum_caja_empleado' => $totales['acum_caja_empleado'],
            'acum_resultado' => $totales['acum_resultado'],
            'acum_pct_mesa' => abs($totales['acum_drop']) > 0.001 ? round($totales['acum_resultado'] / $totales['acum_drop'] * 100, 2) : null,
            'acum_pct_prop' => abs($totales['acum_drop']) > 0.001 ? round($totales['acum_caja_empleado'] / $totales['acum_drop'] * 100, 2) : null,
            'es_subtotal' => $esSubtotal,
            'es_total' => $esTotal,
        ];
    }

    /**
     * @param  array{habilitacion: float, reposicion: float, credito: float, estado_final: float, drop: float, caja_empleado: float, pago_manual: float}  $montos
     * @return array{recomp: float, resultado: float, pct_mesa: ?float, pct_prop: ?float}
     */
    private static function derivados(array $montos): array
    {
        $recomp = round(
            $montos['estado_final'] + $montos['drop'] - $montos['habilitacion']
            + $montos['caja_empleado'] - $montos['pago_manual'],
            2
        );
        $resultado = round(
            $recomp - $montos['reposicion'] + $montos['credito']
            - (2 * $montos['caja_empleado']),
            2
        );

        return [
            'recomp' => $recomp,
            'resultado' => $resultado,
            'pct_mesa' => abs($montos['drop']) > 0.001 ? round($resultado / $montos['drop'] * 100, 2) : null,
            'pct_prop' => abs($montos['drop']) > 0.001 ? round($montos['caja_empleado'] / $montos['drop'] * 100, 2) : null,
        ];
    }

    /** @param  array<string, float>  $montos */
    private static function tieneMovimiento(array $montos): bool
    {
        foreach ($montos as $importe) {
            if (abs((float) $importe) > 0.001) {
                return true;
            }
        }

        return false;
    }

    private static function numeroMesa(string $codigo): int
    {
        if (preg_match('/(\d+)\s*$/', $codigo, $m) === 1) {
            return (int) $m[1];
        }

        return 0;
    }

    /**
     * @return array<int, string> mesa_nro => desc_tipojuego
     */
    private static function tiposJuegoPorMesaErp(int $salaId): array
    {
        $out = [];
        $juegos = TesoreriaCrownMesaJuego::query()
            ->whereHas('deposito', fn ($q) => $q->where('sala_id', $salaId))
            ->with('deposito:id,codigo')
            ->get();
        foreach ($juegos as $juego) {
            $desc = trim((string) ($juego->desc_tipojuego ?? ''));
            if ($desc === '') {
                continue;
            }
            $mesaNro = self::numeroMesa((string) ($juego->deposito->codigo ?? ''));
            if ($mesaNro > 0) {
                $out[$mesaNro] = $desc;
            }
        }

        return $out;
    }

    /** Fallback si falta desc_tipojuego: quita el nro. de mesa del nombre del depósito. */
    private static function juegoDesdeDeposito(string $nombre, string $codigo): string
    {
        $nombre = trim($nombre);
        if ($nombre !== '' && preg_match('/^\d+\s+(.+)$/u', $nombre, $m) === 1) {
            return trim($m[1]) !== '' ? trim($m[1]) : 'Sin juego';
        }
        $nro = self::numeroMesa($codigo);
        if ($nro > 0 && $nombre !== '') {
            $sinNro = trim(preg_replace('/\b'.$nro.'\b/', '', $nombre, 1) ?? $nombre);

            return $sinNro !== '' ? $sinNro : $nombre;
        }

        return $nombre !== '' ? $nombre : 'Sin juego';
    }

    /**
     * Bloque BINGO de l-resmesa (lee_bingo / imprime_total_bingo).
     * Día = fila de la fecha; MTD = suma 1º del mes → fecha (solo ERP).
     *
     * @param  list<string>  $avisos
     * @return array<string, mixed>
     */
    private static function seccionBingo(TesoreriaCrownJornada $jornada, array &$avisos): array
    {
        $fecha = $jornada->fecha->format('Y-m-d');
        $desde = $jornada->fecha->copy()->startOfMonth()->format('Y-m-d');
        $salaId = (int) $jornada->sala_id;

        $dia = TesoreriaCrownBingo::deSalaFecha($salaId, $fecha);
        $mes = TesoreriaCrownBingo::query()
            ->where('sala_id', $salaId)
            ->whereDate('fecha', '>=', $desde)
            ->whereDate('fecha', '<=', $fecha)
            ->get();

        $totDia = [
            'carton' => round((float) ($dia->tot_carton ?? 0), 2),
            'premio' => round((float) ($dia->tot_premio ?? 0), 2),
            'rse' => round((float) ($dia->tot_rse ?? 0), 2),
            'resultado' => round((float) ($dia->resultado ?? 0), 2),
        ];
        $totMes = [
            'carton' => round((float) $mes->sum('tot_carton'), 2),
            'premio' => round((float) $mes->sum('tot_premio'), 2),
            'rse' => round((float) $mes->sum('tot_rse'), 2),
            'resultado' => round((float) $mes->sum('resultado'), 2),
        ];

        if ($mes->isEmpty()) {
            $avisos[] = 'Bingo sin movimiento ERP en el mes: cargá Bingo o importá movbingo.';
        }

        return [
            'clave' => 'bingo',
            'titulo' => 'BINGO',
            'tipo' => 'bingo',
            'filas' => [[
                'concepto' => 'Jugadas',
                'carton' => $totDia['carton'],
                'premio' => $totDia['premio'],
                'rse' => $totDia['rse'],
                'resultado' => $totDia['resultado'],
                'acum_carton' => $totMes['carton'],
                'acum_premio' => $totMes['premio'],
                'acum_rse' => $totMes['rse'],
                'acum_resultado' => $totMes['resultado'],
            ]],
            'totales' => array_merge($totDia, [
                'acum_carton' => $totMes['carton'],
                'acum_premio' => $totMes['premio'],
                'acum_rse' => $totMes['rse'],
                'acum_resultado' => $totMes['resultado'],
            ]),
        ];
    }

    /**
     * Bloque SLOTS de l-resmesa (imprime_total_slots).
     *
     * @param  list<string>  $avisos
     * @return array<string, mixed>
     */
    private static function seccionMaquinas(TesoreriaCrownJornada $jornada, array &$avisos): array
    {
        $resumen = TesoreriaCrownResumenCajeroCalculo::deJornada($jornada);
        $ventasCaja = 0.0;
        $pagosCashless = 0.0;
        foreach ($resumen['grupos'] as $grupo) {
            if (! in_array((string) $grupo['tipo'], ['K', 'L', 'T'], true)) {
                continue;
            }
            $ventasCaja = round($ventasCaja + (float) ($grupo['totales']['vta_cashless'] ?? 0), 2);
            $pagosCashless = round($pagosCashless + (float) ($grupo['totales']['pago_cashless'] ?? 0), 2);
        }

        $validadoras = round((float) TesoreriaCrownSlot::query()
            ->where('jornada_id', $jornada->id)
            ->where('estado', TesoreriaCrownSlot::RECIBIDO)
            ->where('tipo', '!=', TesoreriaCrownSlot::DROP)
            ->sum('total'), 2);

        // l-resmesa Total pagos = cashless + tokens compra + premio bóveda 39 + movficha + jackpot.
        $pagoCreditos = self::pagoCreditosBoveda($jornada);
        $pagos = round($pagosCashless + $pagoCreditos, 2);
        // Resultado = ventas + validadoras − total pagos (no el slot_resultado del online).
        $resultado = round($ventasCaja + $validadoras - $pagos, 2);
        if (abs($validadoras) < 0.001) {
            $avisos[] = 'Sin recuento de slots (validadoras) en la jornada.';
        }
        if (abs($pagoCreditos) > 0.001) {
            $avisos[] = 'Total pagos incluye PAGO DE CRÉDITOS (bóveda tipo 39): '
                .number_format($pagoCreditos, 2, ',', '.');
        }

        $filas = [
            ['concepto' => 'Ventas por caja', 'diario' => $ventasCaja],
            ['concepto' => 'Recaudación validadoras', 'diario' => $validadoras],
            ['concepto' => 'Total pagos', 'diario' => $pagos],
            ['concepto' => 'Resultado', 'diario' => $resultado],
        ];

        return [
            'clave' => self::GRUPO_MAQUINAS,
            'titulo' => 'SLOTS',
            'tipo' => 'maquinas',
            'filas' => $filas,
            'totales' => [
                'ventas' => $ventasCaja,
                'validadoras' => $validadoras,
                'pagos' => $pagos,
                'pago_creditos' => $pagoCreditos,
                'resultado' => $resultado,
            ],
        ];
    }

    /** Anita lee_efeboveda(39): PAGO DE CRÉDITOS del día. */
    private static function pagoCreditosBoveda(TesoreriaCrownJornada $jornada): float
    {
        $movimientos = TesoreriaCrownBoveda::query()
            ->with(['lineas.valor'])
            ->where('jornada_id', $jornada->id)
            ->where('estado', TesoreriaCrownBoveda::CONFIRMADO)
            ->whereNull('revierte_id')
            ->whereHas('tipo', fn ($q) => $q
                ->where('codigo', 39)
                ->where('sala_id', (int) $jornada->sala_id))
            ->get();

        $total = 0.0;
        foreach ($movimientos as $movimiento) {
            foreach ($movimiento->lineas as $linea) {
                $total += (float) $linea->cantidad * (float) ($linea->valor->denominacion ?? 0);
            }
        }

        return round($total, 2);
    }

    /**
     * Bloque GASTRO/HOTEL de l-resmesa (imprime_total_gastronomia).
     *
     * @param  list<string>  $avisos
     * @return array<string, mixed>
     */
    private static function seccionGastronomia(TesoreriaCrownJornada $jornada, array &$avisos): array
    {
        $datos = TesoreriaCrownAnexo2GastroCalculo::seccion($jornada, $avisos);

        return [
            'clave' => self::GRUPO_GASTRONOMIA,
            'titulo' => 'GASTRO/HOTEL',
            'tipo' => 'gastronomia',
            'filas' => $datos['filas'],
            'totales' => $datos['totales'],
            'fuente' => $datos['fuente'],
        ];
    }

    /**
     * Acumulado 1º del mes → fecha desde jornadas ERP de la misma sala.
     *
     * @param  \Illuminate\Support\Collection<int, \App\Models\Caja\TesoreriaCrownValor>  $valores
     * @return array<int, array{habilitacion: float, reposicion: float, credito: float, estado_final: float, drop: float, caja_empleado: float, pago_manual: float}>
     */
    private static function acumuladoMesasErp(TesoreriaCrownJornada $jornada, $valores): array
    {
        $fecha = $jornada->fecha->format('Y-m-d');
        $desde = $jornada->fecha->copy()->startOfMonth()->format('Y-m-d');
        $fechas = TesoreriaCrownJornada::query()
            ->where('sala_id', $jornada->sala_id)
            ->whereDate('fecha', '>=', $desde)
            ->whereDate('fecha', '<=', $fecha)
            ->orderBy('fecha')
            ->pluck('fecha')
            ->map(fn ($f) => $f instanceof \DateTimeInterface ? $f->format('Y-m-d') : substr((string) $f, 0, 10))
            ->unique()
            ->values();

        $out = [];
        $depositos = TesoreriaCrownDeposito::query()
            ->where('sala_id', $jornada->sala_id)
            ->whereHas('tipoDeposito', fn ($q) => $q->where('codigo', 'MESA'))
            ->pluck('id');
        foreach ($depositos as $depositoId) {
            $bucket = self::cerosMontos();
            foreach ($fechas as $dia) {
                $parte = self::montosMesa((int) $depositoId, $dia, $valores);
                foreach (array_keys($bucket) as $clave) {
                    $bucket[$clave] = round($bucket[$clave] + (float) $parte[$clave], 2);
                }
            }
            if (self::tieneMovimiento($bucket)) {
                $out[(int) $depositoId] = $bucket;
            }
        }

        return $out;
    }

    /**
     * Montos de l-resmesa por mesa.
     * Drop ERP = líneas tipo D + voucher. Caja empleado = propina del estado final.
     *
     * @return array{habilitacion: float, reposicion: float, credito: float, estado_final: float, drop: float, caja_empleado: float, pago_manual: float}
     */
    private static function montosMesa(int $depositoId, string $fechaYmd, $valores): array
    {
        $out = [
            'habilitacion' => 0.0,
            'reposicion' => 0.0,
            'credito' => 0.0,
            'estado_final' => 0.0,
            'drop' => 0.0,
            'caja_empleado' => 0.0,
            'pago_manual' => 0.0,
        ];
        $mapa = [
            'A' => 'habilitacion',
            'R' => 'reposicion',
            'C' => 'credito',
            'F' => 'estado_final',
            'D' => 'drop',
        ];
        $movimientos = TesoreriaCrownMesa::query()
            ->with(['lineas', 'reversa'])
            ->where('deposito_id', $depositoId)
            ->where('estado', TesoreriaCrownMesa::CONFIRMADO)
            ->whereNull('revierte_id')
            ->whereDoesntHave('reversa')
            ->whereHas('jornada', fn ($q) => $q->whereDate('fecha', $fechaYmd))
            ->get();

        foreach ($movimientos as $movimiento) {
            $tipo = (string) $movimiento->tipo;
            $clave = $mapa[$tipo] ?? null;
            if ($clave === null) {
                continue;
            }
            $monto = 0.0;
            foreach ($movimiento->lineas as $linea) {
                $valor = $valores->get((int) $linea->valor_id);
                if ($valor === null) {
                    continue;
                }
                $monto += (float) $linea->cantidad * (float) $valor->denominacion;
            }
            if ($tipo === 'D') {
                $monto = round($monto + self::voucherDrop($movimiento), 2);
            }
            $out[$clave] = round($out[$clave] + $monto, 2);
            if ($tipo === 'F') {
                $out['caja_empleado'] = round(
                    $out['caja_empleado'] + (float) ($movimiento->propina ?? 0),
                    2
                );
            }
        }

        return $out;
    }

    /**
     * Voucher del drop (efem_voucher). Si la columna quedó en 0 en imports viejos, se lee del comentario.
     */
    private static function voucherDrop(TesoreriaCrownMesa $movimiento): float
    {
        $voucher = round((float) ($movimiento->voucher ?? 0), 2);
        if ($voucher > 0.0001) {
            return $voucher;
        }
        $comentario = (string) ($movimiento->comentario ?? '');
        if (! preg_match('/voucher\s+([\d.]+(?:,\d+)?)/iu', $comentario, $m)) {
            return 0.0;
        }
        $texto = str_replace('.', '', $m[1]);
        $texto = str_replace(',', '.', $texto);

        return round((float) $texto, 2);
    }

    /**
     * @param  array<int, float>  $cantidades
     */
    private static function montoDeCantidades(array $cantidades, $valores): float
    {
        $total = 0.0;
        foreach ($cantidades as $valorId => $cantidad) {
            $valor = $valores->get((int) $valorId);
            if ($valor === null) {
                continue;
            }
            $total += (float) $cantidad * (float) $valor->denominacion;
        }

        return round($total, 2);
    }

    /** @return array{habilitacion: float, reposicion: float, credito: float, estado_final: float, drop: float, caja_empleado: float, pago_manual: float} */
    private static function cerosMontos(): array
    {
        return [
            'habilitacion' => 0.0,
            'reposicion' => 0.0,
            'credito' => 0.0,
            'estado_final' => 0.0,
            'drop' => 0.0,
            'caja_empleado' => 0.0,
            'pago_manual' => 0.0,
        ];
    }

    /** @return array<string, float> */
    private static function cerosMesas(): array
    {
        return [
            'banca_tipo' => 0.0,
            'creditos' => 0.0,
            'reposiciones' => 0.0,
            'estado_final' => 0.0,
            'drop' => 0.0,
            'pagos_manuales' => 0.0,
            'recomp_banca' => 0.0,
            'caja_empleado' => 0.0,
            'resultado' => 0.0,
            'acum_estado_final' => 0.0,
            'acum_drop' => 0.0,
            'acum_pagos_manuales' => 0.0,
            'acum_recomp_banca' => 0.0,
            'acum_caja_empleado' => 0.0,
            'acum_resultado' => 0.0,
        ];
    }
}
