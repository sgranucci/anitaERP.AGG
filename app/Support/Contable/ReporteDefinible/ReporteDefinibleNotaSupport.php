<?php

namespace App\Support\Contable\ReporteDefinible;

use App\Models\Contable\ReporteContable;
use App\Models\Contable\ReporteContableNota;
use App\Models\Contable\ReporteContableRubro;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Notas al pie del informe.
 *
 * Cada edición crea una versión nueva y deja la anterior guardada: el número publicado
 * de un balance viejo queda acompañado por el texto que se escribió en ese momento.
 * La nota vigente de una cadena es la de mayor "version".
 */
class ReporteDefinibleNotaSupport
{
    /**
     * Notas vigentes (última versión de cada cadena), ordenadas para la UI.
     *
     * @return Collection<int, ReporteContableNota>
     */
    public function listar(int $reporteId): Collection
    {
        return $this->ultimasVersiones($this->todas($reporteId))
            ->sortBy([['orden', 'asc'], ['id', 'asc']])
            ->values();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function payloadUi(int $reporteId): array
    {
        $rubros = $this->rubrosPorId($reporteId);
        $versionesPorCadena = $this->todas($reporteId)->groupBy(fn (ReporteContableNota $n) => $n->cadenaId());

        $out = [];
        foreach ($this->listar($reporteId) as $nota) {
            $rubro = $nota->reporte_contable_rubro_id !== null
                ? ($rubros[(int) $nota->reporte_contable_rubro_id] ?? null)
                : null;

            $out[] = [
                'id' => (int) $nota->id,
                'cadena_id' => $nota->cadenaId(),
                'rubro_id' => $nota->reporte_contable_rubro_id !== null ? (int) $nota->reporte_contable_rubro_id : null,
                'codigo_linea' => (string) ($nota->codigo_linea ?? ''),
                'linea_texto' => $this->lineaTexto($nota, $rubro),
                'texto' => (string) $nota->texto,
                'periodo_desde' => $nota->periodo_desde !== null ? (int) $nota->periodo_desde : null,
                'periodo_hasta' => $nota->periodo_hasta !== null ? (int) $nota->periodo_hasta : null,
                'vigencia_texto' => $nota->vigenciaTexto(),
                'activo' => (bool) $nota->activo,
                'orden' => (int) $nota->orden,
                'version' => (int) $nota->version,
                'versiones' => ($versionesPorCadena[$nota->cadenaId()] ?? collect())->count(),
                'actualizado' => $nota->updated_at?->format('d/m/Y H:i'),
            ];
        }

        return $out;
    }

    /**
     * Historial completo de una cadena, de la versión más nueva a la más vieja.
     *
     * @return list<array<string, mixed>>
     */
    public function historial(int $reporteId, int $notaId): array
    {
        $nota = $this->buscar($reporteId, $notaId);
        if (! $nota) {
            return [];
        }

        $cadena = $nota->cadenaId();

        $out = [];
        foreach ($this->todas($reporteId)->filter(fn (ReporteContableNota $n) => $n->cadenaId() === $cadena)
            ->sortByDesc('version') as $version) {
            $out[] = [
                'id' => (int) $version->id,
                'version' => (int) $version->version,
                'texto' => (string) $version->texto,
                'vigencia_texto' => $version->vigenciaTexto(),
                'activo' => (bool) $version->activo,
                'usuario' => (string) ($version->usuario->nombre ?? ''),
                'fecha' => $version->created_at?->format('d/m/Y H:i'),
                'vigente' => (int) $version->id === (int) $nota->id,
            ];
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    public function crear(int $reporteId, array $datos, ?int $usuarioId): ReporteContableNota
    {
        $atributos = $this->normalizar($reporteId, $datos);
        $atributos['reporte_contable_id'] = $reporteId;
        $atributos['usuario_id'] = $usuarioId;
        $atributos['version'] = 1;
        $atributos['orden'] = $atributos['orden'] ?? ((int) (ReporteContableNota::query()
            ->where('reporte_contable_id', $reporteId)
            ->max('orden') ?? -1) + 1);

        return ReporteContableNota::query()->create($atributos);
    }

    /**
     * Editar = versionar. La fila anterior queda inactiva pero se conserva.
     *
     * @param  array<string, mixed>  $datos
     */
    public function actualizar(int $reporteId, int $notaId, array $datos, ?int $usuarioId): ReporteContableNota
    {
        $vigente = $this->buscar($reporteId, $notaId);
        if (! $vigente) {
            throw ValidationException::withMessages(['nota' => 'Nota no encontrada.']);
        }

        $atributos = $this->normalizar($reporteId, $datos, $vigente);

        // Solo cambió el interruptor de mostrar/no mostrar: no amerita una versión nueva.
        if ($this->soloCambiaActivo($vigente, $atributos)) {
            $vigente->activo = (bool) $atributos['activo'];
            $vigente->save();

            return $vigente;
        }

        $nueva = ReporteContableNota::query()->create(array_merge($atributos, [
            'reporte_contable_id' => $reporteId,
            'usuario_id' => $usuarioId,
            'version' => (int) $vigente->version + 1,
            'nota_original_id' => $vigente->cadenaId(),
            'orden' => $atributos['orden'] ?? (int) $vigente->orden,
        ]));

        $vigente->activo = false;
        $vigente->save();

        return $nueva;
    }

    /**
     * Baja definitiva: se va la cadena completa (todas las versiones).
     */
    public function eliminar(int $reporteId, int $notaId): void
    {
        $nota = $this->buscar($reporteId, $notaId);
        if (! $nota) {
            return;
        }
        $cadena = $nota->cadenaId();

        ReporteContableNota::query()
            ->where('reporte_contable_id', $reporteId)
            ->where(function ($q) use ($cadena) {
                $q->whereKey($cadena)->orWhere('nota_original_id', $cadena);
            })
            ->delete();
    }

    /**
     * Notas que salen en una corrida, con su número de llamada.
     *
     * Solo se listan las notas de líneas que efectivamente aparecen en el informe:
     * una llamada que apunta a una línea oculta por "ocultar si cero" sería ruido.
     * Las notas generales (sin línea) van siempre, al final.
     *
     * @param  array<string, mixed>  $resultado
     * @return array{notas: list<array<string, mixed>>, marcas: array<string, string>}
     */
    public function paraResultado(int $reporteId, array $resultado): array
    {
        $vacio = ['notas' => [], 'marcas' => []];
        if ($reporteId <= 0) {
            return $vacio;
        }

        $vigentes = $this->listar($reporteId)->filter(fn (ReporteContableNota $n) => (bool) $n->activo);
        if ($vigentes->isEmpty()) {
            return $vacio;
        }

        $periodo = $this->periodoReferencia($resultado);
        $vigentes = $vigentes->filter(fn (ReporteContableNota $n) => $this->rigeEnPeriodo($n, $periodo));
        if ($vigentes->isEmpty()) {
            return $vacio;
        }

        // Orden de aparición de las líneas en el informe: así las llamadas quedan 1, 2, 3
        // leyendo el estado de arriba hacia abajo.
        $ordenLinea = [];
        $posicion = 0;
        foreach ($resultado['filas'] ?? [] as $fila) {
            if (($fila['kind'] ?? 'rubro') !== 'rubro') {
                continue;
            }
            $codigo = $this->normalizarCodigo((string) ($fila['codigo'] ?? ''));
            if ($codigo !== '' && ! array_key_exists($codigo, $ordenLinea)) {
                $ordenLinea[$codigo] = $posicion++;
            }
            $rubroId = (int) ($fila['rubro_id'] ?? 0);
            if ($rubroId > 0 && ! array_key_exists('#'.$rubroId, $ordenLinea)) {
                $ordenLinea['#'.$rubroId] = $posicion++;
            }
        }

        $conLinea = [];
        $generales = [];
        foreach ($vigentes as $nota) {
            $clave = $this->claveLineaEnInforme($nota, $ordenLinea);
            if ($clave === null) {
                if ($this->esGeneral($nota)) {
                    $generales[] = $nota;
                }
                continue;
            }
            $conLinea[] = ['nota' => $nota, 'pos' => $ordenLinea[$clave], 'clave' => $clave];
        }

        usort($conLinea, static function (array $a, array $b): int {
            return [$a['pos'], (int) $a['nota']->orden, (int) $a['nota']->id]
                <=> [$b['pos'], (int) $b['nota']->orden, (int) $b['nota']->id];
        });

        $notas = [];
        $marcas = [];
        $marca = 1;
        foreach ($conLinea as $item) {
            /** @var ReporteContableNota $nota */
            $nota = $item['nota'];
            $codigoLinea = $this->normalizarCodigo((string) ($nota->codigo_linea ?? ''));
            $notas[] = [
                'marca' => $marca,
                'codigo_linea' => $codigoLinea,
                'texto' => (string) $nota->texto,
                'vigencia_texto' => $nota->vigenciaTexto(),
            ];
            foreach ($this->clavesDeMarca($nota) as $clave) {
                $marcas[$clave] = isset($marcas[$clave]) ? $marcas[$clave].','.$marca : (string) $marca;
            }
            $marca++;
        }

        foreach ($generales as $nota) {
            $notas[] = [
                'marca' => $marca,
                'codigo_linea' => '',
                'texto' => (string) $nota->texto,
                'vigencia_texto' => $nota->vigenciaTexto(),
            ];
            $marca++;
        }

        return ['notas' => $notas, 'marcas' => $marcas];
    }

    /**
     * Rubros elegibles para colgar una nota (líneas del informe).
     *
     * @return list<array<string, mixed>>
     */
    public function lineasDisponibles(int $reporteId): array
    {
        $out = [];
        foreach ($this->rubros($reporteId) as $rubro) {
            $codigo = (string) ($rubro->codigo_linea ?? '');
            $out[] = [
                'rubro_id' => (int) $rubro->id,
                'codigo_linea' => $codigo,
                'label' => trim(($codigo !== '' ? $codigo.' — ' : '').(string) $rubro->nombre),
            ];
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    private function normalizar(int $reporteId, array $datos, ?ReporteContableNota $base = null): array
    {
        $texto = trim((string) ($datos['texto'] ?? ($base->texto ?? '')));
        if ($texto === '') {
            throw ValidationException::withMessages(['texto' => 'El texto de la nota es obligatorio.']);
        }

        $rubroId = array_key_exists('rubro_id', $datos)
            ? ($datos['rubro_id'] !== null && (int) $datos['rubro_id'] > 0 ? (int) $datos['rubro_id'] : null)
            : ($base?->reporte_contable_rubro_id !== null ? (int) $base->reporte_contable_rubro_id : null);

        $codigoLinea = null;
        if ($rubroId !== null) {
            $rubro = $this->rubrosPorId($reporteId)[$rubroId] ?? null;
            if (! $rubro) {
                throw ValidationException::withMessages(['rubro_id' => 'La línea elegida no pertenece al informe.']);
            }
            $codigoLinea = $this->normalizarCodigo((string) ($rubro->codigo_linea ?? '')) ?: null;
        }

        $desde = $this->periodo($datos, 'periodo_desde', $base?->periodo_desde);
        $hasta = $this->periodo($datos, 'periodo_hasta', $base?->periodo_hasta);
        if ($desde !== null && $hasta !== null && $desde > $hasta) {
            throw ValidationException::withMessages([
                'periodo_hasta' => 'El período "hasta" no puede ser anterior al "desde".',
            ]);
        }

        $atributos = [
            'reporte_contable_rubro_id' => $rubroId,
            'codigo_linea' => $codigoLinea,
            'texto' => $texto,
            'periodo_desde' => $desde,
            'periodo_hasta' => $hasta,
            'activo' => array_key_exists('activo', $datos)
                ? (bool) $datos['activo']
                : (bool) ($base->activo ?? true),
        ];

        if (array_key_exists('orden', $datos) && $datos['orden'] !== null && $datos['orden'] !== '') {
            $atributos['orden'] = (int) $datos['orden'];
        }

        return $atributos;
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function periodo(array $datos, string $clave, mixed $actual): ?int
    {
        if (! array_key_exists($clave, $datos)) {
            return $actual !== null ? (int) $actual : null;
        }

        $valor = trim((string) ($datos[$clave] ?? ''));
        if ($valor === '') {
            return null;
        }

        // Admite 202601 y 2026-01 (input month del navegador).
        $limpio = str_replace('-', '', $valor);
        if (! preg_match('/^\d{6}$/', $limpio)) {
            throw ValidationException::withMessages([$clave => 'El período debe tener formato AAAAMM (ej. 202601).']);
        }
        $mes = (int) substr($limpio, 4, 2);
        if ($mes < 1 || $mes > 12) {
            throw ValidationException::withMessages([$clave => 'El mes del período es inválido.']);
        }

        return (int) $limpio;
    }

    /**
     * @param  array<string, mixed>  $atributos
     */
    private function soloCambiaActivo(ReporteContableNota $vigente, array $atributos): bool
    {
        return trim((string) $atributos['texto']) === trim((string) $vigente->texto)
            && ($atributos['reporte_contable_rubro_id'] ?? null) == $vigente->reporte_contable_rubro_id
            && ($atributos['periodo_desde'] ?? null) == $vigente->periodo_desde
            && ($atributos['periodo_hasta'] ?? null) == $vigente->periodo_hasta
            && (! array_key_exists('orden', $atributos) || (int) $atributos['orden'] === (int) $vigente->orden);
    }

    /**
     * @return Collection<int, ReporteContableNota>
     */
    private function todas(int $reporteId): Collection
    {
        return ReporteContableNota::query()
            ->with('usuario:id,nombre')
            ->where('reporte_contable_id', $reporteId)
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  Collection<int, ReporteContableNota>  $notas
     * @return Collection<int, ReporteContableNota>
     */
    private function ultimasVersiones(Collection $notas): Collection
    {
        return $notas
            ->groupBy(fn (ReporteContableNota $n) => $n->cadenaId())
            ->map(fn (Collection $cadena) => $cadena->sortBy([['version', 'asc'], ['id', 'asc']])->last())
            ->filter()
            ->values();
    }

    private function buscar(int $reporteId, int $notaId): ?ReporteContableNota
    {
        return ReporteContableNota::query()
            ->where('reporte_contable_id', $reporteId)
            ->whereKey($notaId)
            ->first();
    }

    /**
     * @return Collection<int, ReporteContableRubro>
     */
    private function rubros(int $reporteId): Collection
    {
        return ReporteContableRubro::query()
            ->where('reporte_contable_id', $reporteId)
            ->orderBy('orden')
            ->orderBy('id')
            ->get(['id', 'reporte_contable_id', 'codigo_linea', 'nombre', 'orden']);
    }

    /**
     * @return array<int, ReporteContableRubro>
     */
    private function rubrosPorId(int $reporteId): array
    {
        return $this->rubros($reporteId)->keyBy('id')->all();
    }

    private function lineaTexto(ReporteContableNota $nota, ?ReporteContableRubro $rubro): string
    {
        if ($rubro) {
            $codigo = (string) ($rubro->codigo_linea ?? '');

            return trim(($codigo !== '' ? $codigo.' — ' : '').(string) $rubro->nombre);
        }

        $codigo = (string) ($nota->codigo_linea ?? '');

        return $codigo !== '' ? $codigo.' (línea eliminada)' : 'Nota general';
    }

    private function esGeneral(ReporteContableNota $nota): bool
    {
        return $nota->reporte_contable_rubro_id === null
            && $this->normalizarCodigo((string) ($nota->codigo_linea ?? '')) === '';
    }

    /**
     * Claves con las que la vista busca la llamada de una fila: código de línea y #rubro_id.
     *
     * @return list<string>
     */
    private function clavesDeMarca(ReporteContableNota $nota): array
    {
        $claves = [];
        $codigo = $this->normalizarCodigo((string) ($nota->codigo_linea ?? ''));
        if ($codigo !== '') {
            $claves[] = $codigo;
        }
        if ($nota->reporte_contable_rubro_id !== null) {
            $claves[] = '#'.(int) $nota->reporte_contable_rubro_id;
        }

        return $claves;
    }

    /**
     * @param  array<string, int>  $ordenLinea
     */
    private function claveLineaEnInforme(ReporteContableNota $nota, array $ordenLinea): ?string
    {
        foreach ($this->clavesDeMarca($nota) as $clave) {
            if (array_key_exists($clave, $ordenLinea)) {
                return $clave;
            }
        }

        return null;
    }

    private function normalizarCodigo(string $codigo): string
    {
        return strtoupper(trim($codigo));
    }

    /**
     * Período con el que se decide la vigencia: el cierre de la ventana de la corrida.
     *
     * @param  array<string, mixed>  $resultado
     */
    private function periodoReferencia(array $resultado): ?int
    {
        $fecha = trim((string) ($resultado['fecha_hasta'] ?? ''));
        if ($fecha === '') {
            return null;
        }
        $ts = strtotime($fecha);

        return $ts === false ? null : (int) date('Ym', $ts);
    }

    private function rigeEnPeriodo(ReporteContableNota $nota, ?int $periodo): bool
    {
        if ($periodo === null) {
            return true;
        }
        if ($nota->periodo_desde !== null && $periodo < (int) $nota->periodo_desde) {
            return false;
        }
        if ($nota->periodo_hasta !== null && $periodo > (int) $nota->periodo_hasta) {
            return false;
        }

        return true;
    }

    /**
     * Copia de las notas vigentes de un informe a otro (usada al copiar el informe).
     */
    public function copiar(ReporteContable $origen, ReporteContable $destino): int
    {
        $rubrosDestinoPorCodigo = $this->rubros((int) $destino->id)
            ->keyBy(fn (ReporteContableRubro $r) => $this->normalizarCodigo((string) ($r->codigo_linea ?? '')));

        $copiadas = 0;
        foreach ($this->listar((int) $origen->id) as $nota) {
            $codigo = $this->normalizarCodigo((string) ($nota->codigo_linea ?? ''));
            $rubroDestino = $codigo !== '' ? ($rubrosDestinoPorCodigo[$codigo] ?? null) : null;

            ReporteContableNota::query()->create([
                'reporte_contable_id' => (int) $destino->id,
                'reporte_contable_rubro_id' => $rubroDestino?->id,
                'codigo_linea' => $codigo !== '' ? $codigo : null,
                'texto' => (string) $nota->texto,
                'periodo_desde' => $nota->periodo_desde,
                'periodo_hasta' => $nota->periodo_hasta,
                'activo' => (bool) $nota->activo,
                'orden' => (int) $nota->orden,
                'version' => 1,
                'usuario_id' => auth()->id(),
            ]);
            $copiadas++;
        }

        return $copiadas;
    }
}
