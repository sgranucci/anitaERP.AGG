<?php

namespace App\Support\Compras\AnitaSync\Ordencompra;

use App\Models\Compras\Ordencompra;
use App\Models\Compras\Ordencompra_Articulo;
use App\Models\Compras\Requisicion;
use App\Models\Configuracion\Empresa;
use App\Models\Configuracion\Moneda;
use App\Models\Contable\Centrocosto;
use App\Models\Presupuesto\Capex;
use App\Models\Presupuesto\Partidagasto;
use App\Models\Seguridad\Usuario;
use App\Models\Stock\Articulo;
use App\Models\Ventas\Transporte;
use App\Support\Compras\OrdencompraEstados;
use App\Support\Stock\RecepcionProveedorAnitaEscrituraSupport;
use App\Support\Stock\RecepcionProveedorAnitaReferenciaSupport;
use Carbon\Carbon;

/**
 * Resolución ERP → códigos Anita para escritura de OC.
 */
final class OrdencompraAnitaErpContext
{
    /** @var array<string, mixed> */
    private array $cache = [];

    public function __construct(
        public readonly string $usuarioAnita,
    ) {
    }

    public static function desdeUsuarioActual(): self
    {
        $user = auth()->user();
        if ($user === null) {
            return new self('ERP');
        }

        return self::desdeLoginUsuario((string) ($user->usuario ?? $user->name ?? 'ERP'));
    }

    public static function desdeUsuarioId(?int $usuarioId): self
    {
        if ($usuarioId === null || $usuarioId <= 0) {
            return new self('ERP');
        }

        $login = Usuario::query()->whereKey($usuarioId)->value('usuario');

        return self::desdeLoginUsuario((string) ($login ?? 'ERP'));
    }

    private static function desdeLoginUsuario(string $login): self
    {
        $login = substr(trim($login), 0, 8);

        return new self($login !== '' ? $login : 'ERP');
    }

    public function fechaYmd(?string $fecha): int
    {
        if ($fecha === null || trim($fecha) === '') {
            return 0;
        }

        try {
            return (int) Carbon::parse($fecha)->format('Ymd');
        } catch (\Throwable) {
            return 0;
        }
    }

    public function horaActual(): string
    {
        return Carbon::now()->format('H:i:s');
    }

    public function mapEstadoAnita(string $estadoErp): string
    {
        return OrdencompraAnitaEstadosSupport::desdeEstadoErp($estadoErp);
    }

    /** @deprecated Usar mapEstadoAnita() — penmp_estado es char(1) en Anita. */
    public function mapEstadoAnitaEntero(string $estadoErp): int
    {
        return (int) $this->mapEstadoAnita($estadoErp);
    }

    /**
     * occ_medio_pago Anita (C/T/E/R/V) desde formapago ERP.
     */
    public function medioPagoAnitaDesdeFormapago(?int $formapagoId): string
    {
        if ($formapagoId === null || $formapagoId <= 0) {
            return OrdencompraAnitaMedioPagoSupport::SOLO_REGISTRAR;
        }

        $key = 'fp_anita_'.$formapagoId;
        if (array_key_exists($key, $this->cache)) {
            return (string) $this->cache[$key];
        }

        $abrev = strtoupper(trim((string) \App\Models\Ventas\Formapago::query()->whereKey($formapagoId)->value('abreviatura')));

        return (string) ($this->cache[$key] = OrdencompraAnitaMedioPagoSupport::desdeFormapagoAbreviatura($abrev));
    }

    public function usuarioAnitaLogin(): string
    {
        return substr($this->usuarioAnita, 0, 15);
    }

    /**
     * ERP tratamiento → Anita penmp_es_anticipo.
     * No usar str_contains('ANTICIP'): "NO ANTICIPADA" también lo contiene y mandaba S.
     */
    public function mapTratamientoAnticipo(string $tratamiento): string
    {
        return Ordencompra::anitaEsAnticipoDesdeTratamiento($tratamiento);
    }

    public function codigoProveedor6(?int $proveedorId): string
    {
        if ($proveedorId === null || $proveedorId <= 0) {
            return str_repeat('0', 6);
        }

        $key = 'prov_'.$proveedorId;
        if (array_key_exists($key, $this->cache)) {
            return (string) $this->cache[$key];
        }

        $codigo = \App\Models\Compras\Proveedor::query()->whereKey($proveedorId)->value('codigo');

        return (string) ($this->cache[$key] = RecepcionProveedorAnitaReferenciaSupport::proveedorAnita6($codigo ?? '0'));
    }

    public function codigoProveedor6SinSql(?int $proveedorId): string
    {
        return $this->codigoProveedor6($proveedorId);
    }

    public function codigoEmpresa(?int $empresaId): int
    {
        if ($empresaId === null || $empresaId <= 0) {
            return 0;
        }
        $key = 'emp_'.$empresaId;
        if (array_key_exists($key, $this->cache)) {
            return (int) $this->cache[$key];
        }
        $codigo = Empresa::query()->whereKey($empresaId)->value('codigo');

        return (int) ($this->cache[$key] = (int) ($codigo ?? $empresaId));
    }

    public function codigoCentrocosto(?int $centrocostoId): int
    {
        if ($centrocostoId === null || $centrocostoId <= 0) {
            return 0;
        }
        $key = 'cc_'.$centrocostoId;
        if (array_key_exists($key, $this->cache)) {
            return (int) $this->cache[$key];
        }
        $codigo = Centrocosto::query()->whereKey($centrocostoId)->value('codigo');

        return (int) ($this->cache[$key] = (int) ($codigo ?? 0));
    }

    public function codigoMoneda(?int $monedaId): string
    {
        return (string) $this->codigoMonedaAnita($monedaId);
    }

    /** Código moneda Anita (numérico en pendmaep / pendmovp). */
    public function codigoMonedaAnita(?int $monedaId): int
    {
        if ($monedaId === null || $monedaId <= 0) {
            return 1;
        }
        $key = 'mon_'.$monedaId;
        if (array_key_exists($key, $this->cache)) {
            return (int) $this->cache[$key];
        }
        $codigo = Moneda::query()->whereKey($monedaId)->value('codigo');
        $digits = preg_replace('/\D/', '', trim((string) ($codigo ?? ''))) ?? '';
        if ($digits !== '') {
            return (int) ($this->cache[$key] = (int) $digits);
        }

        return (int) ($this->cache[$key] = (int) $monedaId);
    }

    /** penmp_usuario_ini es entero en Anita (id usuario ERP, igual que reqm_usuario). */
    public function usuarioAnitaCodigo(?int $usuarioId = null): int
    {
        $id = (int) ($usuarioId ?? 0);
        if ($id <= 0) {
            $id = (int) (auth()->id() ?? 0);
        }
        if ($id <= 0) {
            return 0;
        }

        $key = 'usu_cod_'.$id;
        if (array_key_exists($key, $this->cache)) {
            return (int) $this->cache[$key];
        }

        $usuario = Usuario::query()->find($id);
        if ($usuario === null) {
            return (int) ($this->cache[$key] = $id);
        }

        $login = trim((string) ($usuario->usuario ?? ''));
        if ($login !== '' && ctype_digit($login)) {
            return (int) ($this->cache[$key] = (int) $login);
        }

        return (int) ($this->cache[$key] = (int) $usuario->id);
    }

    public function codigoCondicioncompra(?int $id): int
    {
        return $this->codigoCatalogoCompras(\App\Models\Compras\Condicioncompra::class, $id);
    }

    public function codigoCondicionentrega(?int $id): int
    {
        return $this->codigoCatalogoCompras(\App\Models\Compras\Condicionentrega::class, $id);
    }

    public function codigoCondicionpago(?int $id): int
    {
        return $this->codigoCatalogoCompras(\App\Models\Compras\Condicionpago::class, $id);
    }

    public function codigoTransporte(?int $id): int
    {
        if ($id === null || $id <= 0) {
            return 0;
        }
        $key = 'tr_'.$id;
        if (array_key_exists($key, $this->cache)) {
            return (int) $this->cache[$key];
        }
        $codigo = Transporte::query()->whereKey($id)->value('codigo');

        return (int) ($this->cache[$key] = (int) ($codigo ?? 0));
    }

    public function numeroRequisicion(?int $requisicionId): int
    {
        if ($requisicionId === null || $requisicionId <= 0) {
            return 0;
        }
        $key = 'req_'.$requisicionId;
        if (array_key_exists($key, $this->cache)) {
            return (int) $this->cache[$key];
        }

        return (int) ($this->cache[$key] = (int) (Requisicion::query()->whereKey($requisicionId)->value('numerorequisicion') ?? 0));
    }

    public function skuArticulo13(?int $articuloId): string
    {
        if ($articuloId === null || $articuloId <= 0) {
            return str_repeat('0', 13);
        }
        $key = 'sku_'.$articuloId;
        if (array_key_exists($key, $this->cache)) {
            return (string) $this->cache[$key];
        }
        $sku = Articulo::query()->whereKey($articuloId)->value('sku');

        return (string) ($this->cache[$key] = RecepcionProveedorAnitaEscrituraSupport::skuAnita13((string) ($sku ?? '')));
    }

    public function skuArticulo13SinPad(?int $articuloId): string
    {
        return trim($this->skuArticulo13($articuloId), "'");
    }

    public function unidadMedidaArticulo(?int $articuloId): string
    {
        if ($articuloId === null || $articuloId <= 0) {
            return 'UNI';
        }
        $key = 'um_'.$articuloId;
        if (array_key_exists($key, $this->cache)) {
            return (string) $this->cache[$key];
        }
        $art = Articulo::query()->with('unidadesdemedidas')->find($articuloId);
        $abrev = strtoupper(substr(trim((string) optional($art?->unidadesdemedidas)->abreviatura), 0, 3));
        if ($abrev === '') {
            $abrev = strtoupper(substr(trim((string) optional($art?->unidadesdemedidas)->codigo), 0, 3));
        }

        return (string) ($this->cache[$key] = ($abrev !== '' ? $abrev : 'UNI'));
    }

    public function agrupacionArticulo(?int $articuloId): string
    {
        if ($articuloId === null || $articuloId <= 0) {
            return '0000';
        }
        $key = 'agr_'.$articuloId;
        if (array_key_exists($key, $this->cache)) {
            return (string) $this->cache[$key];
        }
        $art = Articulo::query()->with('categorias')->find($articuloId);
        $codigo = trim((string) optional($art?->categorias)->codigo);

        return (string) ($this->cache[$key] = str_pad(substr($codigo !== '' ? $codigo : '0', 0, 4), 4, '0', STR_PAD_LEFT));
    }

    public function tipoIvaArticulo(?int $articuloId): int
    {
        if ($articuloId === null || $articuloId <= 0) {
            return 0;
        }
        $key = 'iva_'.$articuloId;
        if (array_key_exists($key, $this->cache)) {
            return (int) $this->cache[$key];
        }
        $art = Articulo::query()->with('impuestos')->find($articuloId);

        return (int) ($this->cache[$key] = RecepcionProveedorAnitaEscrituraSupport::tipoIvaAnitaCodigo($art));
    }

    /** @return array{presupuesto: int, escenario: int, partida: int} */
    public function datosPresupuestoLinea(?int $partidagastoId): array
    {
        if ($partidagastoId === null || $partidagastoId <= 0) {
            return ['presupuesto' => 0, 'escenario' => 0, 'partida' => 0];
        }
        $key = 'pgdat_'.$partidagastoId;
        if (array_key_exists($key, $this->cache)) {
            return $this->cache[$key];
        }
        $pg = Partidagasto::query()
            ->with(['presupuestos', 'presupuesto_escenarios'])
            ->find($partidagastoId);

        return $this->cache[$key] = [
            'presupuesto' => (int) (optional($pg?->presupuestos)->codigo ?? 0),
            'escenario' => (int) (optional($pg?->presupuesto_escenarios)->codigo ?? 0),
            'partida' => (int) ($pg->codigo ?? 0),
        ];
    }

    /** @return array{proyecto: int, cod_proyecto: int} */
    public function datosCapexLinea(?int $capexId): array
    {
        if ($capexId === null || $capexId <= 0) {
            return ['proyecto' => 0, 'cod_proyecto' => 0];
        }
        $key = 'cpx_'.$capexId;
        if (array_key_exists($key, $this->cache)) {
            return $this->cache[$key];
        }
        $cpx = Capex::query()->find($capexId);

        return $this->cache[$key] = [
            'proyecto' => (int) ($cpx->codigo ?? 0),
            'cod_proyecto' => (int) ($cpx->codigoproyecto ?? $cpx->codigo ?? 0),
        ];
    }

    public function cotizacionCabecera(Ordencompra $oc): float
    {
        $linea = $oc->ordencompra_articulos->first();
        $cot = $linea ? (float) ($linea->cotizacion ?? 1) : 1.0;

        return $cot > 0 ? $cot : 1.0;
    }

    public function monedaCabeceraId(Ordencompra $oc): int
    {
        $linea = $oc->ordencompra_articulos->first();

        return (int) ($linea->moneda_id ?? 1);
    }

    public function condicionpagoCabecera(Ordencompra $oc): int
    {
        $desdeCabecera = $this->codigoCondicionpago((int) ($oc->condicionpago_id ?? 0));
        if ($desdeCabecera > 0) {
            return $desdeCabecera;
        }

        $comp = $oc->ordencompra_comprobantes->first();
        if (! $comp) {
            return 0;
        }

        return $this->codigoCondicionpago((int) ($comp->condicionpago_id ?? 0));
    }

    public function importeLinea(Ordencompra_Articulo $linea): float
    {
        $cant = (float) ($linea->cantidad ?? 0);
        $precio = (float) ($linea->precio ?? 0);
        $dto = (float) ($linea->descuento ?? 0);
        $importe = $cant * $precio;
        if ($dto > 0) {
            $importe *= (1 - ($dto / 100));
        }

        return round(max(0, $importe), 4);
    }

    private function codigoCatalogoCompras(string $modelClass, ?int $id): int
    {
        if ($id === null || $id <= 0) {
            return 0;
        }
        $key = 'cat_'.$modelClass.'_'.$id;
        if (array_key_exists($key, $this->cache)) {
            return (int) $this->cache[$key];
        }
        $codigo = $modelClass::query()->whereKey($id)->value('codigo');

        return (int) ($this->cache[$key] = (int) ($codigo ?? 0));
    }
}
