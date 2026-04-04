<!DOCTYPE html>
<html lang="es">
<style>
    /* CSS */
    .boton-lindo {
        background-color: #4CAF50; /* Color de fondo */
        color: white; /* Color de texto */
        padding: 15px 30px; /* Relleno */
        border: none; /* Sin borde */
        border-radius: 50px; /* Esquinas redondeadas */
        font-size: 16px;
        cursor: pointer; /* Cursor de puntero */
        transition: all 0.3s ease; /* Transición suave */
        box-shadow: 0 4px 6px rgba(0,0,0,0.1); /* Sombra */
    }

    .boton-lindo:hover {
        background-color: #45a049; /* Cambio de color en hover */
        transform: translateY(-2px); /* Pequeño salto */
        box-shadow: 0 6px 8px rgba(0,0,0,0.15);
    }
</style>
<script>
    <meta name="csrf-token" content="{{ csrf_token() }}">
</script>
<head>
    <meta charset="UTF-8">
    <title>Encuesta de Calificación</title>
    <style>
        /* Estilo simple para alinear las opciones */
        .pregunta { margin-bottom: 15px; }
        .opciones { display: flex; gap: 10px; }
    </style>
</head>
<body>
    <form action="{{route('guardar_proveedor_encuesta')}}" method="post">
        @csrf
        <h2>{{$encuesta->nombre}}</h2>
        <h2>Proveedor: {{$proveedor->nombre}}</h2>
        <h2>Origen: {{$origen}}</h2>
        @php $numeroPregunta = 1; @endphp
        @foreach($encuesta->encuesta_preguntas as $pregunta)
            <fieldset class="pregunta">
                <legend>{{$numeroPregunta}}. {{$pregunta->nombre}}</legend>
                <div class="opciones">
                    @for ($puntaje = $pregunta->desdepuntaje; $puntaje <= $pregunta->hastapuntaje; $puntaje++)

                    @if ($puntaje == $pregunta->desdepuntaje)
                        <?php
                            echo "<label><input type='radio' name='puntaje{$numeroPregunta}[]' value='$puntaje' required/> $puntaje</label>";
                        ?>
                    @else
                        <?php
                            echo "<label><input type='radio' name='puntaje{$numeroPregunta}[]' value='$puntaje'/> $puntaje</label>";
                        ?>
                    @endif

                    @endfor
                </div>
                <input type="hidden" name="encuesta_pregunta_ids[]" value="{{$pregunta->id}}">
            </fieldset>
            @php $numeroPregunta++; @endphp
        @endforeach
        <div>
            <!-- textarea -->
            <div class="form-group">
                <textarea name="comentario" style="width: 30%;" id="comentario" class="form-control" rows="5" placeholder="Comentarios ..."></textarea>
            </div>
        </div>
        <input type="hidden" name="proveedor_id" value="{{$proveedor->id}}">
        <input type="hidden" name="encuesta_id" value="{{$encuesta->id}}">
        <input type="hidden" name="origen" value="{{$origen}}">
        <button type="submit" class="boton-lindo">Enviar Encuesta</button>
    </form>

</body>
</html>
