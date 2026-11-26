<?php

namespace App\Services\Compras;

use App\Imports\Compras\AhorroImportLecturaCruda;
use App\Models\Compras\Ordencompra;
use App\Models\Compras\Ordencompra_Articulo;
use App\Models\Compras\Requisicion;
use App\Models\Compras\Requisicion_Articulo;
use App\Models\Compras\TipoAhorro;
use App\Models\Configuracion\Empresa;
use App\Models\Configuracion\Moneda;
use App\Support\Compras\AhorroLineaSupport;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

/**
 * Carga histórica de ahorros desde Excel: lee, evalúa fila por fila (sin escribir) y aplica solo lo válido.
 * Cada fila apunta a UNA línea (documento + número + SKU) y se guarda con los mismos servicios que las pantallas,
 * así que se audita y se copia a la requisición / OC vinculada igual que una carga manual.
 */
class AhorroImportacionService
{
    public const MAX_FILAS = 2000;

    /** @var list<string> */
    public const COLUMNAS = [
        'documento', 'numero', 'sku', 'precio_anterior', 'moneda', 'origen', 'referencia', 'tipo_ahorro', 'observacion', 'empresa',
    ];

    /** Alias de encabezado (ya normalizado) => columna canónica. */
    private const ALIAS = [
        'documento' => 'documento', 'documento_req_u_oc' => 'documento', 'tipo_documento' => 'documento', 'doc' => 'documento',
        'numero' => 'numero', 'nro' => 'numero', 'n' => 'numero', 'n_documento' => 'numero', 'numero_documento' => 'numero',
        'sku' => 'sku', 'articulo' => 'sku', 'codigo_articulo' => 'sku',
        'precio_anterior' => 'precio_anterior', 'precio_ant' => 'precio_anterior', 'precio_original' => 'precio_anterior',
        'moneda' => 'moneda', 'moneda_precio_anterior' => 'moneda',
        'origen' => 'origen', 'origen_del_precio_anterior' => 'origen', 'origen_precio_anterior' => 'origen',
        'referencia' => 'referencia', 'referencia_anterior' => 'referencia',
        'tipo_ahorro' => 'tipo_ahorro', 'tipo_de_ahorro' => 'tipo_ahorro', 'tipo' => 'tipo_ahorro',
        'observacion' => 'observacion', 'observaciones' => 'observacion', 'motivo' => 'observacion',
        'empresa' => 'empresa',
    ];

    /** @var array<string, int|null> */
    private array $cacheEmpresas = [];

    /** @var array<string, int>|null */
    private ?array $monedas = null;

    /** @var array<string, array{id: int, nombre: string}>|null */
    private ?array $tipos = null;

    /**
     * @return list<array<string, mixed>> filas con la clave `fila` (número de fila en el Excel)
     */
    public function leerArchivo(string $ruta): array
    {
        $hojas = Excel::toArray(new AhorroImportLecturaCruda(), $ruta);
        $hoja = $hojas[0] ?? [];

        $mapa = null;
        $salida = [];
        foreach ($hoja as $i => $celdas) {
            $celdas = array_values((array) $celdas);
            if (collect($celdas)->every(fn ($c) => trim((string) $c) === '')) {
                continue;
            }
            if ($mapa === null) {
                $mapa = [];
                foreach ($celdas as $col => $titulo) {
                    $clave = self::ALIAS[$this->normalizar((string) $titulo)] ?? null;
                    if ($clave !== null && ! in_array($clave, $mapa, true)) {
                        $mapa[$col] = $clave;
                    }
                }
                $faltan = array_diff(['documento', 'numero', 'sku', 'precio_anterior'], array_values($mapa));
                if ($faltan !== []) {
                    throw new DomainException('Faltan columnas obligatorias en la primera fila: '.implode(', ', $faltan).'. Descargue la plantilla.');
                }

                continue;
            }
            $fila = array_fill_keys(self::COLUMNAS, '');
            foreach ($mapa as $col => $clave) {
                $v = $celdas[$col] ?? '';
                $fila[$clave] = is_string($v) ? trim($v) : $v;
            }
            $fila['fila'] = $i + 1;
            $salida[] = $fila;
        }

        if ($mapa === null) {
            throw new DomainException('El archivo está vacío.');
        }
        if (count($salida) === 0) {
            throw new DomainException('El archivo no tiene filas de datos.');
        }
        if (count($salida) > self::MAX_FILAS) {
            throw new DomainException('El archivo tiene más de '.self::MAX_FILAS.' filas. Divídalo en partes.');
        }

        return $salida;
    }

    /**
     * Evalúa sin escribir. Cada resultado trae estado ok|error, mensaje, avisos y lo necesario para aplicar.
     *
     * @param  list<array<string, mixed>>  $filas
     * @return list<array<string, mixed>>
     */
    public function evaluar(array $filas): array
    {
        $vistos = [];
        $salida = [];
        foreach ($filas as $fila) {
            $r = $this->evaluarFila($fila);
            if ($r['estado'] === 'ok') {
                $clave = $r['doc_tipo'].':'.$r['linea_id'];
                if (isset($vistos[$clave])) {
                    $r['estado'] = 'error';
                    $r['mensaje'] = 'La misma línea ya viene en la fila '.$vistos[$clave].'.';
                } else {
                    $vistos[$clave] = $fila['fila'];
                }
            }
            $salida[] = $r;
        }

        return $salida;
    }

    /**
     * Aplica las filas evaluadas como ok.
     *
     * @param  list<array<string, mixed>>  $evaluadas
     * @return array{aplicadas: int, con_error: int, detalle: list<array{fila: int, ok: bool, mensaje: string}>}
     */
    public function aplicar(array $evaluadas): array
    {
        $detalle = [];
        $aplicadas = 0;
        $errores = 0;
        foreach ($evaluadas as $e) {
            if ($e['estado'] !== 'ok') {
                $errores++;
                $detalle[] = ['fila' => (int) $e['fila'], 'ok' => false, 'mensaje' => (string) $e['mensaje']];

                continue;
            }
            try {
                $copiado = DB::transaction(function () use ($e): string {
                    if ($e['doc_tipo'] === 'OC') {
                        $oc = Ordencompra::query()->findOrFail($e['doc_id']);
                        $linea = Ordencompra_Articulo::query()->findOrFail($e['linea_id']);
                        $res = app(OrdencompraAhorroService::class)->guardar($oc, $linea, $e['datos']);

                        return $res['requisicion_actualizada'] ? 'Copiado a la requisición.' : '';
                    }
                    $req = Requisicion::query()->findOrFail($e['doc_id']);
                    $linea = Requisicion_Articulo::query()->findOrFail($e['linea_id']);
                    $res = app(RequisicionAhorroService::class)->guardar($req, $linea, $e['datos']);

                    return $res['ordenes_actualizadas'] > 0 ? 'Copiado a '.$res['ordenes_actualizadas'].' línea(s) de OC.' : '';
                });
                $aplicadas++;
                $detalle[] = ['fila' => (int) $e['fila'], 'ok' => true, 'mensaje' => trim('Guardado. '.$copiado)];
            } catch (Throwable $ex) {
                $errores++;
                $detalle[] = ['fila' => (int) $e['fila'], 'ok' => false, 'mensaje' => $ex->getMessage()];
            }
        }

        return ['aplicadas' => $aplicadas, 'con_error' => $errores, 'detalle' => $detalle];
    }

    /**
     * @param  array<string, mixed>  $f
     * @return array<string, mixed>
     */
    private function evaluarFila(array $f): array
    {
        $base = [
            'fila' => (int) $f['fila'],
            'estado' => 'error',
            'mensaje' => '',
            'avisos' => [],
            'entrada' => $f,
            'doc_tipo' => null,
            'doc_id' => null,
            'doc_texto' => '',
            'empresa_texto' => '',
            'linea_id' => null,
            'articulo_texto' => '',
            'precio_actual' => null,
            'cantidad' => null,
            'moneda_actual' => '',
            'datos' => [],
            'ahorro_pesos' => null,
            'porc' => null,
        ];

        try {
            $tipoDoc = $this->tipoDocumento((string) $f['documento']);
            $numero = $this->entero($f['numero'], 'El número de documento');
            $sku = trim((string) $f['sku']);
            if ($sku === '') {
                throw new DomainException('Falta el SKU.');
            }
            $precioAnterior = $this->decimal($f['precio_anterior']);
            if ($precioAnterior <= 0) {
                throw new DomainException('El precio anterior debe ser mayor a cero.');
            }

            $empresaFiltro = $this->resolverEmpresa((string) $f['empresa']);
            $empresasPermitidas = $this->empresaIdsDelUsuario();
            $articuloIds = DB::table('articulo')->where('sku', $sku)->pluck('id')->all();
            if ($articuloIds === []) {
                throw new DomainException('No existe un artículo con SKU '.$sku.'.');
            }

            if ($tipoDoc === 'OC') {
                $docs = Ordencompra::query()->where('numeroordencompra', $numero);
            } else {
                $docs = Requisicion::query()->where('numerorequisicion', $numero);
            }
            if ($empresaFiltro) {
                $docs->where('empresa_id', $empresaFiltro);
            }
            if ($empresasPermitidas !== null) {
                $docs->whereIn('empresa_id', $empresasPermitidas);
            }
            $docs = $docs->get();
            if ($docs->count() === 0) {
                throw new DomainException(($tipoDoc === 'OC' ? 'La orden de compra ' : 'La requisición ').$numero.' no existe o no tiene acceso.');
            }
            if ($docs->count() > 1) {
                throw new DomainException('El número '.$numero.' existe en varias empresas: complete la columna Empresa.');
            }
            $doc = $docs->first();
            $empresa = Empresa::query()->find($doc->empresa_id);
            $base['doc_tipo'] = $tipoDoc;
            $base['doc_id'] = (int) $doc->id;
            $base['doc_texto'] = ($tipoDoc === 'OC' ? 'OC ' : 'REQ ').$numero;
            $base['empresa_texto'] = (string) optional($empresa)->nombre;

            $lineas = $tipoDoc === 'OC'
                ? Ordencompra_Articulo::query()->where('ordencompra_id', $doc->id)->whereIn('articulo_id', $articuloIds)->get()
                : Requisicion_Articulo::query()->where('requisicion_id', $doc->id)->whereIn('articulo_id', $articuloIds)->get();
            if ($lineas->count() === 0) {
                throw new DomainException('El SKU '.$sku.' no está en '.$base['doc_texto'].'.');
            }
            if ($lineas->count() > 1) {
                throw new DomainException('El SKU '.$sku.' está '.$lineas->count().' veces en '.$base['doc_texto'].': cárguelo desde la pantalla.');
            }
            $linea = $lineas->first();
            $base['linea_id'] = (int) $linea->id;
            $base['articulo_texto'] = $sku;
            $base['precio_actual'] = (float) $linea->precio;
            $base['cantidad'] = (float) $linea->cantidad;
            $base['moneda_actual'] = (string) (Moneda::query()->where('id', $linea->moneda_id)->value('abreviatura') ?? '');
            if ((float) $linea->precio <= 0) {
                throw new DomainException('La línea no tiene precio: no se puede calcular el ahorro.');
            }

            $monedaId = $this->resolverMoneda((string) $f['moneda'], (int) $linea->moneda_id);
            $origen = $this->resolverOrigen((string) $f['origen']);
            $tipoAhorroId = $this->resolverTipoAhorro((string) $f['tipo_ahorro']);

            $datos = [
                'precio_anterior' => $precioAnterior,
                'moneda_anterior_id' => $monedaId,
                'origen_anterior' => $origen,
                'referencia_anterior' => (string) $f['referencia'],
                'tipoahorro_id' => $tipoAhorroId,
                'observacion' => (string) $f['observacion'],
            ];

            $cols = AhorroLineaSupport::columnasParaGrabar(
                $datos,
                (float) $linea->precio,
                (int) $linea->moneda_id,
                (float) $linea->cantidad,
                $doc->fecha ? (string) $doc->fecha : null,
            );
            if (($cols['ahorro_total_pesos'] ?? null) === null) {
                throw new DomainException('No hay cotización vigente para convertir a pesos en la fecha del documento.');
            }
            $base['datos'] = $datos;
            $base['ahorro_pesos'] = (float) $cols['ahorro_total_pesos'];
            $base['porc'] = $cols['porc_ahorro'] !== null ? (float) $cols['porc_ahorro'] : null;

            if ($linea->ahorro_total_pesos !== null) {
                $base['avisos'][] = 'Reemplaza un ahorro ya cargado ($ '.number_format((float) $linea->ahorro_total_pesos, 2, ',', '.').').';
            }
            if ($tipoDoc === 'OC' && (int) ($linea->requisicion_articulo_id ?? 0) > 0) {
                $base['avisos'][] = 'Se copia a la requisición de origen.';
            }
            if ($tipoDoc === 'REQ') {
                $n = Ordencompra_Articulo::query()->where('requisicion_articulo_id', $linea->id)->count();
                if ($n > 0) {
                    $base['avisos'][] = 'Se copia a '.$n.' línea(s) de OC.';
                }
            }

            $base['estado'] = 'ok';
        } catch (DomainException $e) {
            $base['mensaje'] = $e->getMessage();
        }

        return $base;
    }

    private function tipoDocumento(string $v): string
    {
        $n = $this->normalizar($v);
        if (in_array($n, ['oc', 'orden_de_compra', 'orden_compra', 'ordencompra'], true)) {
            return 'OC';
        }
        if (in_array($n, ['req', 'requisicion', 'rq'], true)) {
            return 'REQ';
        }
        throw new DomainException('Documento debe ser REQ (requisición) u OC (orden de compra).');
    }

    private function entero(mixed $v, string $nombre): int
    {
        $s = trim((string) $v);
        if (is_float($v) || is_int($v)) {
            $s = (string) (int) $v;
        }
        if (! ctype_digit($s) || (int) $s <= 0) {
            throw new DomainException($nombre.' no es válido.');
        }

        return (int) $s;
    }

    private function decimal(mixed $v): float
    {
        if (is_int($v) || is_float($v)) {
            return (float) $v;
        }
        $s = trim((string) $v);
        if ($s === '') {
            return 0.0;
        }
        // 1.234,56 (es-AR) o 1234.56
        if (str_contains($s, ',')) {
            $s = str_replace('.', '', $s);
            $s = str_replace(',', '.', $s);
        }

        return is_numeric($s) ? (float) $s : 0.0;
    }

    /** @return list<int>|null null = usuario con acceso a todas las empresas */
    private function empresaIdsDelUsuario(): ?array
    {
        $ids = collect(Session::get('usuario_empresas'))->map(fn ($e) => (int) ($e['id'] ?? 0))->filter()->values()->all();

        return $ids === [] ? null : $ids;
    }

    private function resolverEmpresa(string $texto): ?int
    {
        $texto = trim($texto);
        if ($texto === '') {
            return null;
        }
        $clave = $this->normalizar($texto);
        if (! array_key_exists($clave, $this->cacheEmpresas)) {
            $id = null;
            foreach (Empresa::query()->get(['id', 'nombre']) as $e) {
                if ($this->normalizar((string) $e->nombre) === $clave || (string) $e->id === $texto) {
                    $id = (int) $e->id;
                    break;
                }
            }
            $this->cacheEmpresas[$clave] = $id;
        }
        if ($this->cacheEmpresas[$clave] === null) {
            throw new DomainException('Empresa "'.$texto.'" inexistente.');
        }

        return $this->cacheEmpresas[$clave];
    }

    private function resolverMoneda(string $texto, int $monedaLineaId): int
    {
        $texto = trim($texto);
        if ($texto === '') {
            return $monedaLineaId;
        }
        if ($this->monedas === null) {
            $this->monedas = [];
            foreach (Moneda::query()->get(['id', 'abreviatura', 'nombre']) as $m) {
                $this->monedas[$this->normalizar((string) $m->abreviatura)] = (int) $m->id;
                $this->monedas[$this->normalizar((string) $m->nombre)] = (int) $m->id;
            }
        }
        $id = $this->monedas[$this->normalizar($texto)] ?? null;
        if ($id === null) {
            $validas = Moneda::query()->orderBy('id')->pluck('abreviatura')->filter()->implode(', ');
            throw new DomainException('Moneda "'.$texto.'" inexistente. Válidas: '.$validas.'.');
        }

        return $id;
    }

    private function resolverOrigen(string $texto): string
    {
        $texto = trim($texto);
        if ($texto === '') {
            return '';
        }
        $n = $this->normalizar($texto);
        foreach (AhorroLineaSupport::origenes() as $clave => $etiqueta) {
            if ($n === $this->normalizar($clave) || $n === $this->normalizar($etiqueta)) {
                return $clave;
            }
        }
        throw new DomainException('Origen "'.$texto.'" inexistente. Válidos: '.implode(', ', AhorroLineaSupport::origenes()).'.');
    }

    private function resolverTipoAhorro(string $texto): ?int
    {
        $texto = trim($texto);
        if ($texto === '') {
            return null;
        }
        if ($this->tipos === null) {
            $this->tipos = [];
            foreach (TipoAhorro::query()->where('activo', true)->get(['id', 'nombre']) as $t) {
                $this->tipos[$this->normalizar((string) $t->nombre)] = ['id' => (int) $t->id, 'nombre' => (string) $t->nombre];
            }
        }
        $t = $this->tipos[$this->normalizar($texto)] ?? null;
        if ($t === null) {
            throw new DomainException('Tipo de ahorro "'.$texto.'" inexistente o inactivo.');
        }

        return $t['id'];
    }

    private function normalizar(string $v): string
    {
        $v = Str::lower(Str::ascii(trim($v)));
        $v = preg_replace('/[^a-z0-9]+/', '_', $v) ?? '';

        return trim($v, '_');
    }
}
