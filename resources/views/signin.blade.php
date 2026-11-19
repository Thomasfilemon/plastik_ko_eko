<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Plasindo Mart - Masuk</title>
    <link rel="icon" href="{{ asset('Logo.JPG') }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root {
            --brand-red: #e3211c;
            --brand-blue: #0b57b0;
            --brand-yellow: #fcc80a;
            --text: #1f2937;
            --muted: #6b7280;
            --border: #d1d5db;
        }

        * { box-sizing: border-box; }

        html, body {
            margin: 0;
            min-height: 100vh;
            font-family: "Segoe UI", system-ui, -apple-system, Roboto, Arial, sans-serif;
            color: var(--text);
            background: #f3f4f6;
        }

        .login-wrapper {
            min-height: 100vh;
            display: flex;
        }

        /* Left: brand cover */
        .cover {
            flex: 1 1 55%;
            position: relative;
            background: #fff;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 48px;
            overflow: hidden;
        }

        .cover::before,
        .cover::after {
            content: "";
            position: absolute;
            left: 0;
            right: 0;
            height: 14px;
        }

        .cover::before {
            top: 0;
            background: linear-gradient(to right,
                var(--brand-red) 0 33.33%,
                var(--brand-blue) 33.33% 66.66%,
                var(--brand-yellow) 66.66% 100%);
        }

        .cover::after {
            bottom: 0;
            background: linear-gradient(to right,
                var(--brand-yellow) 0 33.33%,
                var(--brand-blue) 33.33% 66.66%,
                var(--brand-red) 66.66% 100%);
        }

        .cover-logo {
            width: 100%;
            max-width: 620px;
            height: auto;
            display: block;
        }

        .cover-tagline {
            margin-top: 12px;
            text-align: center;
            color: var(--muted);
            font-size: 1.05rem;
            letter-spacing: 0.3px;
        }

        .cover-tagline strong {
            display: block;
            color: var(--brand-blue);
            font-size: 1.35rem;
            margin-bottom: 4px;
        }

        /* Right: form */
        .form-side {
            flex: 1 1 45%;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 48px 24px;
            background: linear-gradient(160deg, var(--brand-blue) 0%, #08417f 100%);
        }

        .login-card {
            width: 100%;
            max-width: 420px;
            background: #fff;
            border-radius: 16px;
            padding: 40px 36px 32px;
            box-shadow: 0 20px 45px rgba(0, 0, 0, 0.25);
        }

        .login-card h1 {
            margin: 0 0 6px;
            font-size: 1.7rem;
            font-weight: 700;
        }

        .login-card .subtitle {
            margin: 0 0 28px;
            color: var(--muted);
            font-size: 0.95rem;
        }

        .field {
            margin-bottom: 18px;
        }

        .field label {
            display: block;
            font-size: 0.9rem;
            font-weight: 600;
            margin-bottom: 6px;
        }

        .input-wrap {
            position: relative;
        }

        .input-wrap .icon {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
            font-size: 0.95rem;
            pointer-events: none;
        }

        .input-wrap input {
            width: 100%;
            padding: 12px 44px 12px 40px;
            font-size: 1rem;
            border: 1.5px solid var(--border);
            border-radius: 10px;
            outline: none;
            transition: border-color .15s, box-shadow .15s;
            background: #fff;
        }

        .input-wrap input:focus {
            border-color: var(--brand-blue);
            box-shadow: 0 0 0 4px rgba(11, 87, 176, 0.15);
        }

        .input-wrap input.is-invalid {
            border-color: var(--brand-red);
        }

        .toggle-pass {
            position: absolute;
            right: 8px;
            top: 50%;
            transform: translateY(-50%);
            border: 0;
            background: transparent;
            color: #9ca3af;
            cursor: pointer;
            padding: 6px 8px;
            font-size: 0.95rem;
        }

        .toggle-pass:hover { color: var(--brand-blue); }

        .field-error {
            color: var(--brand-red);
            font-size: 0.82rem;
            margin-top: 5px;
        }

        .alert {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #b91c1c;
            padding: 12px 14px;
            border-radius: 10px;
            font-size: 0.92rem;
            margin-bottom: 20px;
        }

        .btn-login {
            width: 100%;
            margin-top: 6px;
            padding: 13px;
            font-size: 1.05rem;
            font-weight: 600;
            color: #fff;
            background: var(--brand-red);
            border: 0;
            border-radius: 10px;
            cursor: pointer;
            transition: background .15s, transform .05s;
        }

        .btn-login:hover { background: #c81b17; }
        .btn-login:active { transform: translateY(1px); }
        .btn-login:disabled { opacity: .75; cursor: wait; }

        .footer-note {
            margin-top: 24px;
            text-align: center;
            color: #9ca3af;
            font-size: 0.8rem;
        }

        @media (max-width: 900px) {
            .login-wrapper { flex-direction: column; }
            .cover { flex: 0 0 auto; padding: 36px 24px 28px; }
            .cover-logo { max-width: 360px; }
            .cover-tagline strong { font-size: 1.1rem; }
            .form-side { flex: 1 1 auto; padding: 32px 16px 40px; align-items: flex-start; }
            .login-card { padding: 32px 24px 28px; }
        }
    </style>
</head>
<body>
    <div class="login-wrapper">
        <section class="cover">
            <img src="{{ asset('Logo.JPG') }}" alt="Plasindo Mart" class="cover-logo">
            <div class="cover-tagline">
                <strong>Sistem Manajemen Toko</strong>
                Penjualan, pembelian, stok &amp; piutang dalam satu tempat
            </div>
        </section>

        <section class="form-side">
            <div class="login-card">
                <h1>Selamat Datang</h1>
                <p class="subtitle">Masuk ke akun Anda untuk melanjutkan</p>

                @if(session('loginError'))
                    <div class="alert">
                        <i class="fas fa-exclamation-circle" style="margin-top:2px;"></i>
                        <span>{{ session('loginError') }}</span>
                    </div>
                @endif

                <form action="/signin" method="POST" id="loginForm">
                    @csrf
                    <div class="field">
                        <label for="email">Email</label>
                        <div class="input-wrap">
                            <i class="fas fa-envelope icon"></i>
                            <input type="email" id="email" name="email" value="{{ old('email') }}"
                                   placeholder="nama@email.com" autocomplete="username" required autofocus
                                   class="{{ $errors->has('email') ? 'is-invalid' : '' }}">
                        </div>
                        @error('email')
                            <div class="field-error">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="field">
                        <label for="password">Password</label>
                        <div class="input-wrap">
                            <i class="fas fa-lock icon"></i>
                            <input type="password" id="password" name="password"
                                   placeholder="Masukkan password" autocomplete="current-password" required
                                   class="{{ $errors->has('password') ? 'is-invalid' : '' }}">
                            <button type="button" class="toggle-pass" id="togglePass" aria-label="Tampilkan password">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        @error('password')
                            <div class="field-error">{{ $message }}</div>
                        @enderror
                    </div>

                    <button type="submit" class="btn-login" id="btnLogin">
                        <i class="fas fa-sign-in-alt" style="margin-right:6px;"></i> Masuk
                    </button>
                </form>

                <div class="footer-note">&copy; {{ date('Y') }} Plasindo Mart</div>
            </div>
        </section>
    </div>

    <script>
        document.getElementById('togglePass').addEventListener('click', function () {
            const input = document.getElementById('password');
            const show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            this.querySelector('i').className = show ? 'fas fa-eye-slash' : 'fas fa-eye';
        });

        document.getElementById('loginForm').addEventListener('submit', function () {
            const btn = document.getElementById('btnLogin');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin" style="margin-right:6px;"></i> Memproses...';
        });
    </script>
</body>
</html>
