<?php

declare(strict_types=1);

namespace App\Services\Arca;

use App\Models\Ventas\ArcaCaea;
use App\Models\Ventas\Puntoventa;
use App\Models\Ventas\Venta;
use App\Services\Ventas\FacturaelectronicaService;
use App\Support\Database\SqlDialectSupport;
use App\Support\Ventas\ArcaCaeaAnitaTipoAfipSupport;
use App\Support\Ventas\ArcaCaeaInformeDatosDesdeAnitaSupport;
use App\Support\Ventas\ArcaCaeaInformeDatosDesdeVentaSupport;
use App\Support\Ventas\ArcaPuntoventaWebserviceSupport;
use App\Support\Ventas\TipotransaccionCodigoAfipSupport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Presentación manual de un comprobante CAEA (ERP primero, fallback Anita).
 */
class ArcaCaeaPresentacionManualService
{
    public function __construct(
        private FacturaelectronicaService $facturaelectronicaService,
        private ArcaCaeaPresentacionService $presentacionService,
        private ArcaWsfeFacturaElectronicaService $wsfe,
        private ArcaMtxcaFacturaElectronicaService $mtxca,
    ) {}

    /**
     * Próximos a presentar (último ARCA + 1) por PV/tipo, con fuente ERP o Anita.
     *
     * @return list<array<string, mixed>>
     */
    public function listarProximosPendientes(ArcaCaea $registro): array
    {
        $registro->loadMissing('empresa');
        $empresaId = (int) $registro->empresa_id;
        $pvs = $this->puntosVentaCaeaEmpresa($empresaId);
        if ($pvs === []) {
            return [];
        }

        $candidatos = $this->combinacionesCandidatas($empresaId, $pvs);
        $out = [];
        $seen = [];

        foreach ($candidatos as $combo) {
            $pto = (int) $combo['pto_vta'];
            $tipoAfip = (int) $combo['tipo_afip'];
            $clave = $pto.'|'.$tipoAfip;
            if (isset($seen[$clave])) {
                continue;
            }
            $seen[$clave] = true;

            $puntoventa = $pvs[$pto] ?? null;
            if ($puntoventa === null) {
                continue;
            }

            try {
                $ultimoArca = $this->consultarUltimoArca($empresaId, $puntoventa, $tipoAfip);
            } catch (Throwable $e) {
                Log::info('arca:caea-manual — sin último ARCA para combinación', [
                    'empresa_id' => $empresaId,
                    'pto_vta' => $pto,
                    'tipo_afip' => $tipoAfip,
                    'msg' => $e->getMessage(),
                ]);

                continue;
            }

            $proximo = $ultimoArca + 1;
            $resolucion = $this->resolverSinPresentar(
                $registro,
                $pto,
                $tipoAfip,
                $proximo,
                (string) ($combo['tipo_anita'] ?? ''),
                (string) ($combo['letra'] ?? ''),
            );

            if (! ($resolucion['encontrado'] ?? false)) {
                continue;
            }

            $out[] = [
                'pto_vta' => $pto,
                'tipo_afip' => $tipoAfip,
                'tipo_anita' => $resolucion['tipo_anita'] ?? ($combo['tipo_anita'] ?? null),
                'letra' => $resolucion['letra'] ?? ($combo['letra'] ?? null),
                'etiqueta' => $combo['etiqueta'] ?? ('T'.$tipoAfip),
                'ultimo_arca' => $ultimoArca,
                'proximo' => $proximo,
                'fuente' => $resolucion['fuente'] ?? null,
                'encontrado' => true,
                'fecha' => $resolucion['fecha'] ?? null,
                'total' => $resolucion['total'] ?? null,
                'cliente' => $resolucion['cliente'] ?? null,
                'venta_id' => $resolucion['venta_id'] ?? null,
                'error' => null,
            ];
        }

        usort($out, static function (array $a, array $b): int {
            return [$a['pto_vta'], $a['tipo_afip']] <=> [$b['pto_vta'], $b['tipo_afip']];
        });

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    public function previsualizar(
        ArcaCaea $registro,
        int $ptoVta,
        int $tipoAfip,
        int $numero,
        ?string $tipoAnita = null,
        ?string $letra = null,
    ): array {
        if (! $registro->estaAutorizado()) {
            return ['ok' => false, 'mensaje' => 'El CAEA no está autorizado.'];
        }

        $resolucion = $this->resolverSinPresentar($registro, $ptoVta, $tipoAfip, $numero, $tipoAnita, $letra);
        if (! ($resolucion['encontrado'] ?? false)) {
            return [
                'ok' => false,
                'mensaje' => $resolucion['mensaje'] ?? 'Comprobante no encontrado en ERP ni en Anita.',
            ];
        }

        $puntoventa = $this->resolverPuntoventa((int) $registro->empresa_id, $ptoVta);
        if ($puntoventa === null) {
            return ['ok' => false, 'mensaje' => "Punto de venta {$ptoVta} no es CAEA de la empresa."];
        }

        try {
            $ultimoArca = $this->consultarUltimoArca((int) $registro->empresa_id, $puntoventa, $tipoAfip);
        } catch (Throwable $e) {
            return ['ok' => false, 'mensaje' => 'No se pudo consultar último autorizado en ARCA: '.$e->getMessage()];
        }

        $esperable = $ultimoArca + 1;
        $correlativoOk = $numero === $esperable;

        return [
            'ok' => true,
            'mensaje' => $correlativoOk
                ? 'Listo para presentar (es el próximo de la secuencia ARCA).'
                : sprintf('Atención: ARCA espera #%d; usted indicó #%d.', $esperable, $numero),
            'correlativo_ok' => $correlativoOk,
            'ultimo_arca' => $ultimoArca,
            'proximo_esperado' => $esperable,
            'preview' => $resolucion,
        ];
    }

    /**
     * @return array{ok:bool, mensaje:string, resultado?:array<string, mixed>}
     */
    public function informarUno(
        ArcaCaea $registro,
        int $ptoVta,
        int $tipoAfip,
        int $numero,
        ?string $tipoAnita = null,
        ?string $letra = null,
        bool $forzarFueraDeSecuencia = false,
    ): array {
        if (! $registro->estaAutorizado()) {
            return ['ok' => false, 'mensaje' => 'El CAEA no está autorizado.'];
        }

        $registro->loadMissing('empresa');
        $empresa = $registro->empresa;
        if ($empresa === null || trim((string) $empresa->nroinscripcion) === '') {
            return ['ok' => false, 'mensaje' => 'Empresa sin CUIT configurado.'];
        }

        $puntoventa = $this->resolverPuntoventa((int) $registro->empresa_id, $ptoVta);
        if ($puntoventa === null) {
            return ['ok' => false, 'mensaje' => "Punto de venta {$ptoVta} no es CAEA de la empresa."];
        }

        try {
            $ultimoArca = $this->consultarUltimoArca((int) $registro->empresa_id, $puntoventa, $tipoAfip);
        } catch (Throwable $e) {
            return ['ok' => false, 'mensaje' => 'No se pudo consultar último autorizado en ARCA: '.$e->getMessage()];
        }

        if ($numero <= $ultimoArca) {
            return [
                'ok' => true,
                'mensaje' => sprintf('El comprobante #%d ya figura informado en ARCA (último: #%d).', $numero, $ultimoArca),
                'resultado' => ['ya_informado' => true, 'ultimo_arca' => $ultimoArca],
            ];
        }

        if ($numero !== $ultimoArca + 1 && ! $forzarFueraDeSecuencia) {
            return [
                'ok' => false,
                'mensaje' => sprintf(
                    'ARCA espera el #%d (último autorizado #%d). No se puede saltar la secuencia.',
                    $ultimoArca + 1,
                    $ultimoArca,
                ),
            ];
        }

        $resolucion = $this->resolverConDatos($registro, $ptoVta, $tipoAfip, $numero, $tipoAnita, $letra);
        if (! ($resolucion['encontrado'] ?? false)) {
            return ['ok' => false, 'mensaje' => $resolucion['mensaje'] ?? 'Comprobante no encontrado.'];
        }

        $datos = $resolucion['datos'];
        unset($datos['cbte_tipo'], $datos['letra'], $datos['caea']);

        $pvSoap = ArcaPuntoventaWebserviceSupport::puntoventaParaSoap($puntoventa);
        $caeaVigente = [
            'caea' => trim((string) $registro->nro_caea),
            'fechavencimientocae' => $registro->fecha_vigencia_hasta?->format('Ymd') ?? '',
            'fechavencimiento' => $registro->fecha_vigencia_hasta?->format('Ymd') ?? '',
        ];

        $resultado = $this->facturaelectronicaService->informarComprobanteCaea(
            $empresa->nroinscripcion,
            $tipoAfip,
            $pvSoap,
            $datos,
            $caeaVigente,
        );

        if (! ($resultado['ok'] ?? false)) {
            $msg = (string) ($resultado['error'] ?? 'Error desconocido al informar');
            if (($resolucion['venta'] ?? null) instanceof Venta) {
                $this->marcarVenta($resolucion['venta'], 'error', $msg);
            }

            return ['ok' => false, 'mensaje' => $msg];
        }

        if (($resolucion['venta'] ?? null) instanceof Venta) {
            $estado = (($resultado['resultado'] ?? 'A') === 'O') ? 'observacion' : 'ok';
            $this->marcarVenta(
                $resolucion['venta'],
                $estado,
                (string) ($resultado['observaciones'] ?? 'Informado manualmente'),
            );
        }

        Log::info('arca:caea-informe-manual — comprobante informado', [
            'arca_caea_id' => $registro->id,
            'pto_vta' => $ptoVta,
            'tipo_afip' => $tipoAfip,
            'numero' => $numero,
            'fuente' => $resolucion['fuente'] ?? null,
        ]);

        try {
            $this->presentacionService->actualizarResumenPeriodo($registro, null, true);
        } catch (Throwable) {
            // no bloquear éxito de presentación
        }

        return [
            'ok' => true,
            'mensaje' => sprintf(
                'Comprobante PV %05d T%d #%d presentado (%s).',
                $ptoVta,
                $tipoAfip,
                $numero,
                $resolucion['fuente'] ?? '?',
            ),
            'resultado' => $resultado,
            'fuente' => $resolucion['fuente'] ?? null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function resolverSinPresentar(
        ArcaCaea $registro,
        int $ptoVta,
        int $tipoAfip,
        int $numero,
        ?string $tipoAnita,
        ?string $letra,
    ): array {
        $empresaId = (int) $registro->empresa_id;

        $venta = $this->buscarVentaErp($empresaId, $ptoVta, $tipoAfip, $numero);
        if ($venta !== null) {
            return [
                'encontrado' => true,
                'fuente' => 'erp',
                'venta_id' => (int) $venta->id,
                'fecha' => (string) $venta->fecha,
                'total' => (float) $venta->total,
                'cliente' => (string) ($venta->nombre ?? $venta->clientes?->nombre ?? ''),
                'tipo_anita' => null,
                'letra' => $this->letraDesdeVenta($venta),
                'mensaje' => null,
            ];
        }

        $anita = $this->resolverClaveAnita($tipoAfip, $tipoAnita, $letra);
        if ($anita === null) {
            return [
                'encontrado' => false,
                'mensaje' => 'No está en ERP y no se pudo inferir tipo Anita para buscar fallback.',
            ];
        }

        try {
            $cab = ArcaCaeaInformeDatosDesdeAnitaSupport::leerCabecera(
                $anita['tipo_anita'],
                $anita['letra'],
                $ptoVta,
                $numero,
            );
        } catch (Throwable $e) {
            return ['encontrado' => false, 'mensaje' => $e->getMessage()];
        }

        if ($cab === null) {
            return [
                'encontrado' => false,
                'mensaje' => sprintf(
                    'No se encontró PV %d T%d #%d en ERP ni Anita (%s %s).',
                    $ptoVta,
                    $tipoAfip,
                    $numero,
                    $anita['tipo_anita'],
                    $anita['letra'],
                ),
            ];
        }

        return [
            'encontrado' => true,
            'fuente' => 'anita',
            'venta_id' => null,
            'fecha' => $this->ymdToDate((string) ($cab['ven_fecha'] ?? '')),
            'total' => (float) ($cab['ven_monto'] ?? 0),
            'cliente' => (string) ($cab['ven_nombre_cliente'] ?? $cab['ven_cliente'] ?? ''),
            'tipo_anita' => $anita['tipo_anita'],
            'letra' => $anita['letra'],
            'mensaje' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function resolverConDatos(
        ArcaCaea $registro,
        int $ptoVta,
        int $tipoAfip,
        int $numero,
        ?string $tipoAnita,
        ?string $letra,
    ): array {
        $empresaId = (int) $registro->empresa_id;
        $venta = $this->buscarVentaErp($empresaId, $ptoVta, $tipoAfip, $numero);
        if ($venta !== null) {
            try {
                $datos = ArcaCaeaInformeDatosDesdeVentaSupport::construir($venta);
                if (ArcaCaeaAnitaTipoAfipSupport::esFce((int) ($datos['cbte_tipo'] ?? $tipoAfip))) {
                    $datos['datos_adicionales'] = $this->datosAdicionalesFce($empresaId);
                }
            } catch (Throwable $e) {
                return ['encontrado' => false, 'mensaje' => 'ERP: '.$e->getMessage()];
            }

            return [
                'encontrado' => true,
                'fuente' => 'erp',
                'venta' => $venta,
                'datos' => $datos,
            ];
        }

        $anita = $this->resolverClaveAnita($tipoAfip, $tipoAnita, $letra);
        if ($anita === null) {
            return ['encontrado' => false, 'mensaje' => 'Sin mapeo Anita para el tipo AFIP '.$tipoAfip];
        }

        try {
            $armado = ArcaCaeaInformeDatosDesdeAnitaSupport::construir(
                $anita['tipo_anita'],
                $anita['letra'],
                $ptoVta,
                $numero,
                $empresaId,
                trim((string) $registro->nro_caea),
            );
        } catch (Throwable $e) {
            return ['encontrado' => false, 'mensaje' => 'Anita: '.$e->getMessage()];
        }

        return [
            'encontrado' => true,
            'fuente' => 'anita',
            'venta' => null,
            'datos' => $armado['datos'],
            'tipo_anita' => $anita['tipo_anita'],
            'letra' => $anita['letra'],
        ];
    }

    /**
     * @return array{tipo_anita:string, letra:string}|null
     */
    private function resolverClaveAnita(int $tipoAfip, ?string $tipoAnita, ?string $letra): ?array
    {
        $tipoAnita = strtoupper(trim((string) $tipoAnita));
        $letra = strtoupper(trim((string) $letra));
        if ($tipoAnita !== '' && $letra !== '') {
            return ['tipo_anita' => $tipoAnita, 'letra' => $letra];
        }

        return ArcaCaeaAnitaTipoAfipSupport::anitaDesdeTipoAfip($tipoAfip);
    }

    private function buscarVentaErp(int $empresaId, int $ptoVta, int $tipoAfip, int $numero): ?Venta
    {
        $ventas = Venta::query()
            ->with(['puntoventas', 'tipotransacciones', 'clientes', 'venta_impuestos', 'venta_emisiones', 'monedas'])
            ->join('puntoventa', 'puntoventa.id', '=', 'venta.puntoventa_id')
            ->join('tipotransaccion', 'tipotransaccion.id', '=', 'venta.tipotransaccion_id')
            ->where('puntoventa.empresa_id', $empresaId)
            ->whereRaw(SqlDialectSupport::castEntero('puntoventa.codigo').' = ?', [$ptoVta])
            ->where('venta.numerocomprobante', $numero)
            ->where('puntoventa.modofacturacion', 'A')
            ->whereIn('puntoventa.webservice', ArcaPuntoventaWebserviceSupport::valoresWhereInSoapCaea())
            ->whereNotNull('venta.cae')
            ->select('venta.*')
            ->get();

        foreach ($ventas as $venta) {
            $codigoAfip = TipotransaccionCodigoAfipSupport::codigoAfipDesdeVentaGrabada(
                (string) ($venta->tipotransacciones?->codigo ?? ''),
                (string) $venta->codigo,
            );
            if ($codigoAfip === $tipoAfip) {
                return $venta;
            }
        }

        return null;
    }

    /**
     * @return array<int, Puntoventa>
     */
    private function puntosVentaCaeaEmpresa(int $empresaId): array
    {
        $rows = Puntoventa::query()
            ->where('empresa_id', $empresaId)
            ->where('modofacturacion', 'A')
            ->whereIn('webservice', ArcaPuntoventaWebserviceSupport::valoresWhereInSoapCaea())
            ->where(function ($q): void {
                $q->whereNull('estado')->orWhere('estado', 'A');
            })
            ->get();

        $out = [];
        foreach ($rows as $pv) {
            $out[(int) $pv->codigo] = $pv;
        }

        return $out;
    }

    private function resolverPuntoventa(int $empresaId, int $ptoVta): ?Puntoventa
    {
        return $this->puntosVentaCaeaEmpresa($empresaId)[$ptoVta] ?? null;
    }

    /**
     * @param  array<int, Puntoventa>  $pvs
     * @return list<array<string, mixed>>
     */
    private function combinacionesCandidatas(int $empresaId, array $pvs): array
    {
        $out = [];
        $seen = [];

        $rows = DB::table('venta')
            ->join('puntoventa', 'puntoventa.id', '=', 'venta.puntoventa_id')
            ->join('tipotransaccion', 'tipotransaccion.id', '=', 'venta.tipotransaccion_id')
            ->where('puntoventa.empresa_id', $empresaId)
            ->where('puntoventa.modofacturacion', 'A')
            ->whereIn('puntoventa.webservice', ArcaPuntoventaWebserviceSupport::valoresWhereInSoapCaea())
            ->whereNotNull('venta.cae')
            ->select([
                'puntoventa.codigo as pto_vta',
                'venta.codigo as codigo_venta',
                'tipotransaccion.codigo as tipo_codigo',
            ])
            ->distinct()
            ->get();

        foreach ($rows as $row) {
            $tipoAfip = TipotransaccionCodigoAfipSupport::codigoAfipDesdeVentaGrabada(
                (string) $row->tipo_codigo,
                (string) $row->codigo_venta,
            );
            if ($tipoAfip <= 0) {
                continue;
            }
            $pto = (int) $row->pto_vta;
            $clave = $pto.'|'.$tipoAfip;
            if (isset($seen[$clave])) {
                continue;
            }
            $seen[$clave] = true;
            $anita = ArcaCaeaAnitaTipoAfipSupport::anitaDesdeTipoAfip($tipoAfip);
            $out[] = [
                'pto_vta' => $pto,
                'tipo_afip' => $tipoAfip,
                'tipo_anita' => $anita['tipo_anita'] ?? null,
                'letra' => $anita['letra'] ?? null,
                'etiqueta' => 'T'.$tipoAfip,
            ];
        }

        // Candidatos Anita habituales (FCE, etc.) aunque no existan en ERP.
        foreach ($pvs as $pto => $pv) {
            foreach (['FCE' => ['A' => 201, 'B' => 206], 'FAC' => ['A' => 1, 'B' => 6]] as $tipoAnita => $letras) {
                foreach ($letras as $letra => $tipoAfip) {
                    $clave = $pto.'|'.$tipoAfip;
                    if (isset($seen[$clave])) {
                        continue;
                    }
                    $seen[$clave] = true;
                    $out[] = [
                        'pto_vta' => (int) $pto,
                        'tipo_afip' => $tipoAfip,
                        'tipo_anita' => $tipoAnita,
                        'letra' => $letra,
                        'etiqueta' => $tipoAnita.' '.$letra.' ('.$tipoAfip.')',
                    ];
                }
            }
        }

        return $out;
    }

    private function consultarUltimoArca(int $empresaId, object $puntoventa, int $tipoAfip): int
    {
        $pvSoap = ArcaPuntoventaWebserviceSupport::puntoventaParaSoap($puntoventa);
        $pto = (int) $pvSoap->codigo;

        if (ArcaPuntoventaWebserviceSupport::esMtxca((string) $pvSoap->webservice)) {
            return (int) $this->mtxca->consultarUltimoComprobanteAutorizado($empresaId, $pto, $tipoAfip);
        }

        return (int) $this->wsfe->feCompUltimoAutorizado($empresaId, $pto, $tipoAfip);
    }

    /**
     * @return list<array{t:int, c1:string}>
     */
    private function datosAdicionalesFce(int $empresaId): array
    {
        $cbu = trim((string) (
            config("arca.caea.fce.cbu_por_empresa.{$empresaId}")
            ?: config('arca.caea.fce.cbu_emisor', '')
        ));
        $opcion = trim((string) config('arca.caea.fce.opcion_transferencia', 'ADC'));
        if ($cbu === '') {
            return [];
        }

        return [
            ['t' => 21, 'c1' => $cbu],
            ['t' => 27, 'c1' => $opcion !== '' ? $opcion : 'ADC'],
        ];
    }

    private function marcarVenta(Venta $venta, string $estado, string $mensaje): void
    {
        $venta->caea_informado_estado = $estado;
        $venta->caea_informado_at = now();
        $venta->caea_informado_mensaje = mb_substr($mensaje, 0, 500);
        $venta->save();
    }

    private function ymdToDate(string $ymd): ?string
    {
        $d = preg_replace('/\D+/', '', $ymd) ?? '';
        if (strlen($d) < 8) {
            return null;
        }

        return substr($d, 0, 4).'-'.substr($d, 4, 2).'-'.substr($d, 6, 2);
    }

    private function letraDesdeVenta(Venta $venta): string
    {
        return \App\Support\Contable\LibroIvaDigital\LibroIvaDigitalMapeosSupport::letraDesdeCodigoVenta(
            (string) $venta->codigo,
        );
    }
}
