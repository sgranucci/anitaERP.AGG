<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Anita ERP | Login</title>
    <!-- Tell the browser to be responsive to screen width -->
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="{{asset("assets/$theme/plugins/fontawesome-free/css/all.min.css")}}">
    <!-- Ionicons -->
    <link rel="stylesheet" href="https://code.ionicframework.com/ionicons/2.0.1/css/ionicons.min.css">
    <!-- icheck bootstrap -->
    <link rel="stylesheet" href="{{asset("assets/$theme/plugins/icheck-bootstrap/icheck-bootstrap.min.css")}}">
    <!-- Theme style -->
    <link rel="stylesheet" href="{{asset("assets/$theme/dist/css/adminlte.min.css")}}">
    <!-- Google Font: Source Sans Pro -->
    <link href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700" rel="stylesheet">
    <style>
        .login-card-body .btn-toggle-password {
            background-color: transparent;
            border: 1px solid #ced4da;
            border-left: 0;
            color: #6c757d;
            box-shadow: none;
            padding: 0 0.75rem;
            display: flex;
            align-items: center;
            justify-content: center;
            min-width: 2.5rem;
        }
        .login-card-body .btn-toggle-password:hover,
        .login-card-body .btn-toggle-password:focus {
            background-color: #f8f9fa;
            color: #495057;
            border-color: #ced4da;
            box-shadow: none;
            outline: 0;
        }
        .login-card-body .btn-toggle-password:focus-visible {
            outline: 2px solid #80bdff;
            outline-offset: 1px;
        }
        .login-card-body #login-password {
            border-right: 0;
        }
        .login-card-body #login-password:focus {
            border-right: 0;
        }
        .login-card-body #login-password:focus + .input-group-append .btn-toggle-password {
            border-color: #80bdff;
        }
    </style>
</head>
<body class="hold-transition login-page">
    <div class="login-box">
        <div class="login-logo">
            <a href="/">Anita ERP</a>
        </div>
        <!-- /.login-logo -->
        <div class="card">
            <div class="card-body login-card-body">
                <p class="login-box-msg">Inicio de Sesión</p>
                @if (session('status'))
                <div class="alert alert-success" role="alert">
                    {{ session('status') }}
                </div>
                @endif
                @if ($errors->any())
                <div class="alert alert-danger alert-dismissible">
                    <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
                    <div class="alert-text">
                        @foreach ($errors->all() as $error)
                            <div>{{ $error }}</div>
                        @endforeach
                    </div>
                </div>
                @endif
                <form action="{{route('login_post')}}" method="POST" autocomplete="off">
                    @csrf
                    <div class="input-group mb-3">
                        <input type="text" name="usuario" id="login-usuario" class="form-control" value="{{old('usuario')}}"
                            placeholder="Usuario" autofocus autocomplete="username">
                        <div class="input-group-append">
                            <div class="input-group-text">
                                <span class="fas fa-user"></span>
                            </div>
                        </div>
                    </div>
                    <div class="input-group mb-3">
                        <input type="password" name="password" id="login-password" class="form-control"
                            placeholder="Contraseña" autocomplete="current-password">
                        <div class="input-group-append">
                            <button type="button"
                                class="btn btn-toggle-password"
                                id="btn-toggle-login-password"
                                aria-label="Mostrar contraseña"
                                aria-controls="login-password"
                                aria-pressed="false"
                                title="Mostrar contraseña">
                                <i class="fas fa-eye" aria-hidden="true"></i>
                            </button>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-8"></div>
                        <!-- /.col -->
                        <div class="col-4">
                            <button type="submit" class="btn btn-primary btn-block">Login</button>
                        </div>
                        <!-- /.col -->
                    </div>
                </form>
                <div class="social-auth-links text-center mb-3"></div>
                <p class="mb-1">
                    @if (Route::has('password.request'))
                    <a class="btn btn-link" href="{{ route('password.request') }}">
                        {{ __('Forgot Your Password?') }}
                    </a>
                    @endif
                </p>
            </div>
            <!-- /.login-card-body -->
        </div>
    </div>
    <!-- /.login-box -->
    <script src="{{asset("assets/$theme/plugins/jquery/jquery.min.js")}}"></script>
    <!-- Bootstrap 4 -->
    <script src="{{asset("assets/$theme/plugins/bootstrap/js/bootstrap.bundle.min.js")}}"></script>
    <!-- AdminLTE App -->
    <script src="{{asset("assets/$theme/dist/js/adminlte.min.js")}}"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var campoUsuario = document.getElementById('login-usuario');
            if (campoUsuario) {
                campoUsuario.focus();
                campoUsuario.select();
            }

            var campoPassword = document.getElementById('login-password');
            var btnToggle = document.getElementById('btn-toggle-login-password');
            if (!campoPassword || !btnToggle) {
                return;
            }

            var icono = btnToggle.querySelector('i');

            function actualizarToggle(mostrar) {
                campoPassword.setAttribute('type', mostrar ? 'text' : 'password');
                btnToggle.setAttribute('aria-pressed', mostrar ? 'true' : 'false');
                btnToggle.setAttribute('aria-label', mostrar ? 'Ocultar contraseña' : 'Mostrar contraseña');
                btnToggle.setAttribute('title', mostrar ? 'Ocultar contraseña' : 'Mostrar contraseña');
                if (icono) {
                    icono.classList.toggle('fa-eye', !mostrar);
                    icono.classList.toggle('fa-eye-slash', mostrar);
                }
            }

            btnToggle.addEventListener('click', function () {
                var visible = campoPassword.getAttribute('type') === 'text';
                actualizarToggle(!visible);
            });
        });
    </script>
</body>

</html>
