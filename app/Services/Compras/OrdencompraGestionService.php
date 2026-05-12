<?php

namespace App\Services\Compras;

use App\Models\Compras\Condicionpago;
use App\Models\Compras\Ordencompra;
use App\Models\Compras\Ordencompra_Comprobante;
use App\Models\Compras\Ordencompra_Comprobante_Cuota;
use App\Models\Compras\Ordencompra_Historia;
use App\Models\Compras\Requisicion;
use App\Models\Compras\Requisicion_Estado;
use App\Models\Compras\Sector_Legajocompra;
use App\Repositories\Compras\Ordencompra_ArchivoRepositoryInterface;
use App\Repositories\Compras\Ordencompra_ArticuloRepositoryInterface;
use App\Repositories\Compras\Ordencompra_EstadoRepositoryInterface;
use App\Repositories\Compras\OrdencompraRepositoryInterface;
use App\Services\Configuracion\ArbolaprobacionService;
use App\Support\Compras\OrdencompraCondicionesContratacionGenerator;
use App\Support\Compras\ValidacionPresupuestoPartidaCapexLineas;
use App\Support\Compras\OrdencompraEstados;
use App\Support\Compras\OrdencompraTotalesCabecera;
use Auth;
use Carbon\Carbon;
use DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class OrdencompraGestionService
{
    public function __construct(
        private OrdencompraRepositoryInterface $ordencompraRepository,
        private Ordencompra_EstadoRepositoryInterface $ordencompraEstadoRepository,
        private Ordencompra_ArticuloRepositoryInterface $ordencompraArticuloRepository,
        private Ordencompra_ArchivoRepositoryInterface $ordencompraArchivoRepository,
        private ArbolaprobacionService $arbolaprobacionService,
    ) {
    }

    public function idSectorCompras(): ?int
    {
        return Sector_Legajocompra::query()->where('nombre', 'Compras')->value('id');
    }

    /**
     * @return array<int, array{id:int,numerorequisicion:int,fecha:string,proveedor:string,centrocosto:string}>
     */
    public function buscarRequisicionesAprobadas(?string $q, int $limite = 40): array
    {
        $aprobada = Requisicion_Estado::$enumEstado[array_search('A', array_column(Requisicion_Estado::$enumEstado, 'valor'))]['nombre'];
        $query = Requisicion::query()
            ->select(['requisicion.id', 'requisicion.numerorequisicion', 'requisicion.fecha', 'proveedor.nombre as proveedor', 'centrocosto.nombre as centrocosto'])
            ->leftJoin('proveedor', 'proveedor.id', '=', 'requisicion.proveedor_id')
            ->leftJoin('centrocosto', 'centrocosto.id', '=', 'requisicion.centrocosto_id')
            ->where('requisicion.estado', $aprobada)
            ->orderByDesc('requisicion.fecha')
            ->limit($limite);

        if ($q !== null && $q !== '') {
            $b = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $q).'%';
            $query->where(function ($w) use ($b) {
                $w->where('requisicion.numerorequisicion', 'like', $b)
                    ->orWhere('proveedor.nombre', 'like', $b)
                    ->orWhere('centrocosto.nombre', 'like', $b);
            });
        }

        return $query->get()->map(fn ($r) => [
            'id' => (int) $r->id,
            'numerorequisicion' => (int) $r->numerorequisicion,
            'fecha' => (string) $r->fecha,
            'proveedor' => (string) ($r->proveedor ?? ''),
            'centrocosto' => (string) ($r->centrocosto ?? ''),
        ])->all();
    }

    /**
     * Plantilla JSON para precargar formulario (sin historia ni archivos).
     *
     * @return array<string, mixed>
     */
    public function plantillaDesdeRequisicion(int $requisicionId): array
    {
        $aprobada = Requisicion_Estado::$enumEstado[array_search('A', array_column(Requisicion_Estado::$enumEstado, 'valor'))]['nombre'];
        $req = Requisicion::with([
            'proveedores',
            'requisicion_articulos.articulos',
            'requisicion_articulos.monedas',
            'requisicion_articulos.centrocostos_destino',
            'requisicion_articulos.partidagastos.articulos',
            'requisicion_articulos.capexs',
        ])->find($requisicionId);
        if (! $req || $req->estado !== $aprobada) {
            throw new \InvalidArgumentException('La requisición no existe o no está aprobada.');
        }

        $articulos = [];
        foreach ($req->requisicion_articulos as $i => $lin) {
            $pg = $lin->partidagastos;
            $cpx = $lin->capexs;
            $art = $lin->articulos;
            $articulos[] = [
                'articulo_id' => $lin->articulo_id,
                'sku' => $art ? (string) ($art->sku ?? '') : '',
                'descripcion_articulo' => $art ? (string) ($art->descripcion ?? '') : '',
                'cantidad' => $lin->cantidad,
                'precio' => $lin->precio,
                'moneda_id' => $lin->moneda_id,
                'fechaentrega' => $lin->fechaentrega,
                'cantidadalternativa' => $lin->cantidadalternativa,
                'detalle' => $lin->detalle,
                'centrocostodestino_id' => $lin->centrocostodestino_id,
                'partidagasto_id' => $lin->partidagasto_id,
                'codigopartidagasto' => $pg ? (string) ($pg->codigo ?? '') : '',
                'descripcionpartidagasto' => ($pg && $pg->articulos) ? (string) ($pg->articulos->detalle ?? '') : '',
                'capex_id' => $lin->capex_id,
                'codigocapex' => $cpx ? (string) ($cpx->codigo ?? '') : '',
                'descripcioncapex' => $cpx ? (string) ($cpx->nombre ?? '') : '',
                'cotizacion' => 1,
            ];
        }

        $prov = $req->proveedores;

        return [
            'requisicion_id' => $req->id,
            'empresa_id' => $req->empresa_id,
            'fecha' => $req->fecha,
            'fechaentrega' => $req->fechaentrega,
            'centrocosto_id' => $req->centrocosto_id,
            'comentario' => $req->comentario,
            'detalle' => $req->detalle,
            'tratamiento' => Ordencompra::mapearTratamientoDesdeRequisicion((string) ($req->tratamiento ?? '')),
            'proveedor_id' => $req->proveedor_id,
            'proveedor_codigo' => $prov ? (string) ($prov->codigo ?? '') : '',
            'proveedor_nombre' => $prov ? (string) ($prov->nombre ?? '') : '',
            'articulos' => $articulos,
        ];
    }

    /**
     * @return list<array{fechavencimiento:string,monto:float,moneda_id:int,cotizacion:float,formapago_id:?int,detalle:string}>
     */
    public function sugerirCuotasDesdeCondicionpago(int $condicionpagoId, string $fechaBaseYmd, float $montoTotal, int $monedaId): array
    {
        $cp = Condicionpago::with(['condicionpagocuotas' => fn ($q) => $q->orderBy('cuota')])->find($condicionpagoId);
        if (! $cp || $cp->condicionpagocuotas->isEmpty()) {
            return [];
        }

        $cuotas = $cp->condicionpagocuotas;
        $sumPct = (float) $cuotas->sum(fn ($c) => (float) ($c->porcentaje ?? 0));
        $cursor = Carbon::parse($fechaBaseYmd)->startOfDay();
        $salida = [];
        $n = $cuotas->count();
        $idx = 0;

        foreach ($cuotas as $c) {
            $idx++;
            $pct = (float) ($c->porcentaje ?? 0);
            if ($sumPct > 0 && $pct > 0) {
                $monto = round($montoTotal * $pct / $sumPct, 4);
            } else {
                $monto = round($montoTotal / max(1, $n), 4);
            }

            $tipo = (string) ($c->tipoplazo ?? '');
            $fv = null;
            if (! empty($c->fechavencimiento)) {
                $fv = Carbon::parse($c->fechavencimiento)->format('Y-m-d');
            } elseif ($tipo === 'D' || $tipo === 'O') {
                $dias = (int) ($c->plazo ?? 0);
                $cursor = $cursor->copy()->addDays($dias);
                $fv = $cursor->format('Y-m-d');
            } elseif ($tipo === 'F') {
                $cursor = $cursor->copy()->addMonth();
                $fv = $cursor->format('Y-m-d');
            } else {
                $dias = (int) ($c->plazo ?? 0);
                $cursor = $cursor->copy()->addDays($dias);
                $fv = $cursor->format('Y-m-d');
            }

            $salida[] = [
                'fechavencimiento' => $fv ?? $fechaBaseYmd,
                'monto' => $monto,
                'moneda_id' => $monedaId,
                'cotizacion' => 1.0,
                'formapago_id' => null,
                'detalle' => 'Cuota '.$c->cuota.' — '.$cp->nombre,
            ];
        }

        return $salida;
    }

    public function guardar(Request $request): array
    {
        $v = Validator::make($request->all(), $this->reglasCabecera());
        if ($v->fails()) {
            return ['mensaje' => 'error', 'errores' => $v->errors()->first()];
        }
        $data = $v->validated();
        $payload = $request->all();

        try {
            $this->validarComprobantesCuotas($payload);
        } catch (\InvalidArgumentException $e) {
            return ['mensaje' => 'error', 'errores' => $e->getMessage()];
        }

        try {
            ValidacionPresupuestoPartidaCapexLineas::validar($payload);
        } catch (\InvalidArgumentException $e) {
            return ['mensaje' => 'error', 'errores' => $e->getMessage()];
        }

        try {
            $this->arbolaprobacionService->validaOrdencompraRequestContraArbolOpcional($payload);
        } catch (\RuntimeException $e) {
            return ['mensaje' => 'error', 'errores' => $e->getMessage()];
        }

        $sectorId = $this->idSectorCompras();
        $uid = Auth::user()->id;

        $cab = $this->armaCabeceraDesdeRequest($payload, OrdencompraEstados::PENDIENTE, $sectorId, $uid);

        $oc = null;
        DB::beginTransaction();
        try {
            $oc = $this->ordencompraRepository->create($cab);

            $this->ordencompraEstadoRepository->create([
                'fechas' => [Carbon::now()->format('Y-m-d')],
                'estados' => [OrdencompraEstados::PENDIENTE],
                'usuario_ids' => [$uid],
                'observacionestados' => ['Alta de orden de compra'],
            ], $oc->id);

            $this->ordencompraArticuloRepository->syncFromRequest(array_merge($payload, ['fecha' => $cab['fecha']]), $oc->id);

            $this->sincronizarComprobantesCuotas($oc->id, $payload, $uid);
            $this->regenerarCondicionesContratacion($oc->id);
            $this->ordencompraArchivoRepository->create($request, $oc->id);

            if ($sectorId) {
                Ordencompra_Historia::create([
                    'ordencompra_id' => $oc->id,
                    'sector_legajocompra_id' => $sectorId,
                    'fecha' => Carbon::now(),
                    'observacion' => 'Ingreso al legajo de compras',
                    'leyenda' => 'Sector inicial',
                    'creousuario_id' => $uid,
                ]);
            }

            if ($this->arbolaprobacionService->empresaTieneArbolOrdencompraActivoUnico((int) $oc->empresa_id)) {
                $this->arbolaprobacionService->procesaArbolaprobacion('OC', $oc->id, 'insert');
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();

            return ['mensaje' => 'error', 'errores' => $e->getMessage()];
        }

        return ['mensaje' => 'ok', 'id' => $oc ? $oc->id : null];
    }

    public function actualizar(Request $request, int $id): array
    {
        $existente = $this->ordencompraRepository->find($id);
        if (! in_array($existente->estadoordencompra, [OrdencompraEstados::PENDIENTE, OrdencompraEstados::SUSPENDIDA], true)) {
            return ['mensaje' => 'error', 'errores' => 'Solo se puede editar en estado PENDIENTE o SUSPENDIDA.'];
        }

        $v = Validator::make($request->all(), $this->reglasCabecera());
        if ($v->fails()) {
            return ['mensaje' => 'error', 'errores' => $v->errors()->first()];
        }
        $payload = $request->all();
        try {
            $this->validarComprobantesCuotas($payload);
        } catch (\InvalidArgumentException $e) {
            return ['mensaje' => 'error', 'errores' => $e->getMessage()];
        }

        try {
            ValidacionPresupuestoPartidaCapexLineas::validar($payload);
        } catch (\InvalidArgumentException $e) {
            return ['mensaje' => 'error', 'errores' => $e->getMessage()];
        }

        if (($existente->estadoordencompra ?? '') === OrdencompraEstados::PENDIENTE) {
            try {
                $payload['ordencompra_id'] = $id;
                $this->arbolaprobacionService->validaOrdencompraRequestContraArbolOpcional($payload);
            } catch (\RuntimeException $e) {
                return ['mensaje' => 'error', 'errores' => $e->getMessage()];
            }
        }

        $cab = $this->armaCabeceraDesdeRequest($payload, $existente->estadoordencompra, $existente->sector_legajocompra_id, $existente->creousuario_id);
        unset($cab['creousuario_id']);

        DB::beginTransaction();
        try {
            $this->ordencompraRepository->update($cab, $id);
            $this->ordencompraArticuloRepository->syncFromRequest(array_merge($payload, ['fecha' => $cab['fecha']]), $id);
            $this->sincronizarComprobantesCuotas($id, $payload, Auth::user()->id);
            $this->regenerarCondicionesContratacion($id);
            $this->ordencompraArchivoRepository->update($request, $id);

            if (($existente->estadoordencompra ?? '') === OrdencompraEstados::PENDIENTE
                && $this->arbolaprobacionService->empresaTieneArbolOrdencompraActivoUnico((int) $cab['empresa_id'])) {
                $this->arbolaprobacionService->procesaArbolaprobacion('OC', $id, 'insert');
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();

            return ['mensaje' => 'error', 'errores' => $e->getMessage()];
        }

        return ['mensaje' => 'ok'];
    }

    public function cambiarEstado(int $id, string $nuevoEstado, string $observacion): array
    {
        if (! OrdencompraEstados::esNombreValido($nuevoEstado)) {
            return ['mensaje' => 'error', 'errores' => 'Estado inválido.'];
        }
        $oc = $this->ordencompraRepository->find($id);

        DB::beginTransaction();
        try {
            $this->ordencompraRepository->update(['estadoordencompra' => $nuevoEstado], $id);
            $this->ordencompraEstadoRepository->creaEstado(
                $id,
                Carbon::now()->format('Y-m-d'),
                $nuevoEstado,
                Auth::user()->id,
                $observacion !== '' ? $observacion : 'Cambio de estado'
            );
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();

            return ['mensaje' => 'error', 'errores' => $e->getMessage()];
        }

        return ['mensaje' => 'ok'];
    }

    public function reactivarDesdeSuspendida(int $id): array
    {
        $oc = $this->ordencompraRepository->find($id);
        if ($oc->estadoordencompra !== OrdencompraEstados::SUSPENDIDA) {
            return ['mensaje' => 'error', 'errores' => 'Solo aplica a órdenes suspendidas.'];
        }

        return $this->cambiarEstado($id, OrdencompraEstados::PENDIENTE, 'Reactivación desde suspendida a pendiente');
    }

    public function cambiarSector(int $id, int $sectorLegajocompraId, ?string $observacion, ?string $leyenda): array
    {
        $sec = Sector_Legajocompra::find($sectorLegajocompraId);
        if (! $sec) {
            return ['mensaje' => 'error', 'errores' => 'Sector inválido.'];
        }

        DB::beginTransaction();
        try {
            $this->ordencompraRepository->update(['sector_legajocompra_id' => $sectorLegajocompraId], $id);
            Ordencompra_Historia::create([
                'ordencompra_id' => $id,
                'sector_legajocompra_id' => $sectorLegajocompraId,
                'fecha' => Carbon::now(),
                'observacion' => $observacion,
                'leyenda' => $leyenda,
                'creousuario_id' => Auth::user()->id,
            ]);
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();

            return ['mensaje' => 'error', 'errores' => $e->getMessage()];
        }

        return ['mensaje' => 'ok'];
    }

    public function leerHistoriaLegajo(int $ordencompraId)
    {
        return Ordencompra_Historia::query()
            ->where('ordencompra_id', $ordencompraId)
            ->with(['sector_legajocompras', 'usuarios'])
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->get();
    }

    public function leerHistoriaEstados(int $ordencompraId)
    {
        return DB::table('ordencompra_estado')
            ->where('ordencompra_id', $ordencompraId)
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function armaCabeceraDesdeRequest(array $payload, string $estado, ?int $sectorId, int $creoUsuarioId): array
    {
        return [
            'fecha' => $payload['fecha'],
            'fechaentrega' => $payload['fechaentrega'],
            'empresa_id' => $payload['empresa_id'],
            'requisicion_id' => ! empty($payload['requisicion_id']) ? (int) $payload['requisicion_id'] : null,
            'centrocosto_id' => $payload['centrocosto_id'],
            'comentario' => $payload['comentario'] ?? '',
            'detalle' => $payload['detalle'] ?? '',
            'lugarentrega' => $payload['lugarentrega'] ?? null,
            'transporte_id' => ! empty($payload['transporte_id']) ? (int) $payload['transporte_id'] : null,
            'tratamiento' => $payload['tratamiento'],
            'proveedor_id' => ! empty($payload['proveedor_id']) ? (int) $payload['proveedor_id'] : null,
            'condicioncompra_id' => ! empty($payload['condicioncompra_id']) ? (int) $payload['condicioncompra_id'] : null,
            'condicionentrega_id' => ! empty($payload['condicionentrega_id']) ? (int) $payload['condicionentrega_id'] : null,
            'descuento' => isset($payload['descuento']) ? (float) $payload['descuento'] : null,
            'estadoordencompra' => $estado,
            'sector_legajocompra_id' => $sectorId,
            'creousuario_id' => $creoUsuarioId,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function reglasCabecera(): array
    {
        return [
            'fecha' => 'required|date',
            'fechaentrega' => 'required|date',
            'empresa_id' => 'required|integer|exists:empresa,id',
            'centrocosto_id' => 'required|integer|exists:centrocosto,id',
            'comentario' => 'nullable|string|max:255',
            'detalle' => 'required|string',
            'tratamiento' => ['required', 'string', 'max:50', Rule::in(array_column(Ordencompra::$enumTratamientoCompra, 'nombre'))],
            'requisicion_id' => 'nullable|integer|exists:requisicion,id',
            'proveedor_id' => 'nullable|integer|exists:proveedor,id',
            'transporte_id' => 'nullable|integer|exists:transporte,id',
            'condicioncompra_id' => 'nullable|integer|exists:condicioncompra,id',
            'condicionentrega_id' => 'nullable|integer|exists:condicionentrega,id',
            'descuento' => 'nullable|numeric',
            'lugarentrega' => 'nullable|string|max:255',
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    /**
     * @param  array<string, mixed>  $payload
     * @return list<array<string, mixed>>
     */
    private function listaComprobantesDesdePayload(array $payload): array
    {
        $raw = $payload['comprobantes_json'] ?? '[]';
        if (is_array($raw)) {
            return $raw;
        }
        $lista = json_decode(trim((string) $raw), true) ?: [];

        return is_array($lista) ? $lista : [];
    }

    /**
     * @param  array<string, mixed>  $payload
     *
     * @throws \InvalidArgumentException
     */
    private function validarComprobantesCuotas(array $payload): void
    {
        $lista = $this->listaComprobantesDesdePayload($payload);
        foreach ($lista as $idx => $c) {
            if (! is_array($c)) {
                continue;
            }
            $cuotas = $c['cuotas'] ?? [];
            if (! is_array($cuotas) || count($cuotas) === 0) {
                continue;
            }
            $montoComp = (float) ($c['monto'] ?? 0);
            $monedaComp = (int) ($c['moneda_id'] ?? 1);
            $sum = 0.0;
            foreach ($cuotas as $q) {
                if (! is_array($q)) {
                    continue;
                }
                $mid = (int) ($q['moneda_id'] ?? $monedaComp);
                if ($mid !== $monedaComp) {
                    throw new \InvalidArgumentException(
                        'Comprobante #'.(((int) $idx) + 1).': todas las cuotas deben estar en la misma moneda que el comprobante.'
                    );
                }
                $sum += (float) ($q['monto'] ?? 0);
            }
            if (abs($sum - $montoComp) > 0.02) {
                throw new \InvalidArgumentException(
                    'Comprobante #'.(((int) $idx) + 1).': la suma de cuotas ('.number_format($sum, 2, ',', '.').') no coincide con el monto del comprobante ('.number_format($montoComp, 2, ',', '.').').'
                );
            }
        }
    }

    private function sincronizarComprobantesCuotas(int $ordencompraId, array $payload, int $creousuarioId): void
    {
        $lista = $this->listaComprobantesDesdePayload($payload);

        $idsComp = Ordencompra_Comprobante::where('ordencompra_id', $ordencompraId)->pluck('id');
        if ($idsComp->isNotEmpty()) {
            Ordencompra_Comprobante_Cuota::whereIn('ordencompra_comprobante_id', $idsComp)->delete();
        }
        Ordencompra_Comprobante::where('ordencompra_id', $ordencompraId)->delete();

        foreach ($lista as $c) {
            if (! is_array($c)) {
                continue;
            }
            $comp = Ordencompra_Comprobante::create([
                'ordencompra_id' => $ordencompraId,
                'tipocomprobante' => (string) ($c['tipocomprobante'] ?? 'FACTURA'),
                'fechavencimiento' => (string) ($c['fechavencimiento'] ?? date('Y-m-d')),
                'monto' => (float) ($c['monto'] ?? 0),
                'moneda_id' => (int) ($c['moneda_id'] ?? 1),
                'cotizacion' => isset($c['cotizacion']) ? (float) $c['cotizacion'] : null,
                'detalle' => $c['detalle'] ?? null,
                'cantidadcuota' => isset($c['cantidadcuota']) ? (int) $c['cantidadcuota'] : null,
                'condicionpago_id' => ! empty($c['condicionpago_id']) ? (int) $c['condicionpago_id'] : null,
                'creousuario_id' => $creousuarioId,
            ]);

            $cuotas = $c['cuotas'] ?? [];
            if (! is_array($cuotas)) {
                continue;
            }
            foreach ($cuotas as $q) {
                if (! is_array($q)) {
                    continue;
                }
                Ordencompra_Comprobante_Cuota::create([
                    'ordencompra_comprobante_id' => $comp->id,
                    'fechavencimiento' => (string) ($q['fechavencimiento'] ?? $comp->fechavencimiento),
                    'monto' => (float) ($q['monto'] ?? 0),
                    'moneda_id' => (int) ($q['moneda_id'] ?? $comp->moneda_id),
                    'cotizacion' => isset($q['cotizacion']) ? (float) $q['cotizacion'] : null,
                    'formapago_id' => max(1, (int) ($q['formapago_id'] ?? 1)),
                    'detalle' => $q['detalle'] ?? null,
                    'creousuario_id' => $creousuarioId,
                ]);
            }
        }
    }

    private function regenerarCondicionesContratacion(int $ordencompraId): void
    {
        $oc = Ordencompra::with([
            'ordencompra_comprobantes.monedas',
            'ordencompra_comprobantes.ordencompra_comprobante_cuotas.formapagos',
            'ordencompra_comprobantes.ordencompra_comprobante_cuotas.monedas',
        ])->find($ordencompraId);
        if (! $oc) {
            return;
        }
        $texto = OrdencompraCondicionesContratacionGenerator::desdeModelo($oc);
        $this->ordencompraRepository->update(['condiciones_contratacion' => $texto], $ordencompraId);
    }
}
