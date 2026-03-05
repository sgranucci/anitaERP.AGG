@extends("theme.$theme.layout")
@section('titulo')
    Crea ZPL desde PNG
@endsection

@section("scripts")

<script>
	$("#ordenestrabajo").focus();

    // public website: gather usage insights, leave header links visible, use public API
    (function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){
    (i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),
    m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)
    })(window,document,'script','//www.google-analytics.com/analytics.js','ga');
    ga('create', 'UA-50317107-1', 'labelary.com');
    ga('send', 'pageview');

    var apiServer = 'https://api.labelary.com';

    const ERROR_DOWN_FOR_MAINTENANCE = 'ERROR: Temporarily down for maintenance';

    const MIME_PNG = 'image/png';
    const MIME_PDF = 'application/pdf';
    const MIME_ZPL = 'application/zpl';
    const MIME_EPL = 'application/epl';
    const MIME_JSON = 'application/json';

    const FACTORS = {
        inches: 1,
        cm: 0.393701,
        mm: 0.0393701
    };

    var debugOn = false;

    function refreshVersion() {
        var xhr = new XMLHttpRequest();
        xhr.open('GET', apiServer + '/version');
        xhr.onload = function() { $id('version').textContent = 'Powered by Labelary API version ' + this.response };
        xhr.send();
    }

    // http://stackoverflow.com/questions/166221/how-can-i-upload-files-asynchronously-with-jquery
    function uploadImage() {
	    var fileLength = this.files.length;
        var match= ["image/png"];
		var nombreArchivos = '';
        var i;
        for(i = 0; i < fileLength; i++){
            var file = this.files[i];
            var imagefile = file.type;
            if(!imagefile==match[0]){
                alert('Tipo de archivo erroneo. (PNG).');
            }
			else
			{
        		var formData = new FormData();
        		formData.append('file', file);
        		var xhr = new XMLHttpRequest();
        		xhr.open('POST', apiServer + '/v1/graphics', false);
        		xhr.setRequestHeader('Accept', MIME_JSON);
        		xhr.onreadystatechange = function () {
            		if (xhr.readyState != XMLHttpRequest.DONE) return;
            		if (xhr.status == 200) {
                		var data = JSON.parse(xhr.responseText);
                		var cmd =
                      		'\n'
                    		+ '\n'
                    		+ '^FO670,30^GFA,' + data.totalBytes + ',' + data.totalBytes + ',' + data.rowBytes + ',' + data.data + '^FS\n'
                    		+ '\n'
                    		+ '\n';

						nombreArchivos = file.name + '\n' + nombreArchivos;
						$("#zpl").val(nombreArchivos);

						var zpl = cmd;
        				var wurl = window.URL || window.webkitURL;
        				var blob = new Blob([ zpl ], { type: MIME_ZPL });
        				var url = wurl.createObjectURL(blob);
        				triggerDownload(url, file.name.replace("png","zpl"));
        				wurl.revokeObjectURL(url);

            		} else if (xhr.status == 0) {
                		// see note in refreshLabel() for details on when this might happen
                		alert(ERROR_DOWN_FOR_MAINTENANCE);
            		} else {
                		alert(xhr.responseText);
            		}
        		};
        		xhr.send(formData);
			 }
		}
    }

    function triggerDownload(url, filename) {
        var a = document.createElement('a');
        a.href = url;
        a.download = filename;
        a.style = 'display: none';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
    }

    function track(action) {
        if (window.ga && ga.loaded) ga('send', 'event', 'Viewer', action);
    }

    function debug(s) {
        if (debugOn) console.log(s);
    }

    function $id(id) {
        return document.getElementById(id);
    }

	bind('#addImage',       'click',                [function() { $id('imageFile').click() }]);
    bind('#imageFile',      'change',               [uploadImage], 'Add Image');

    function bind(selector, eventTypes, handlers, action) {
        eventTypes = eventTypes.split(' ');
        var elements = document.querySelectorAll(selector);
        for (var i = 0; i < elements.length; i++) {
            var element = elements[i];
            for (var j = 0; j < eventTypes.length; j++) {
                var eventType = eventTypes[j];
                for (var k = 0; k < handlers.length; k++) {
                    element.addEventListener(eventType, handlers[k]);
                }
                if (action) {
                    element.addEventListener(eventType, function() { track(action) });
                }
            }
        }
    }

</script>

@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-danger">
            <div class="card-header">
                <h3 class="card-title">Crea ZPL desde imagen PNG</h3>
                <div class="card-tools">
                	<a href="{{  URL::previous() }}" class="btn btn-outline-info btn-sm">
                   		<i class="fa fa-fw fa-reply-all"></i> Volver a etiquetas
                   	</a>
                </div>
            </div>
            <form action="#" id="form-general" class="form-horizontal form--label-right" method="POST" autocomplete="off">
                @csrf @method("post")
                <div class="card-body">
                    @include('ventas.repetiquetaot.formzpl')
                </div>
                <div class="card-footer">
                    <div class="row">
                        <div class="col-lg-3"></div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
