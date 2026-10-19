<?php
session_start();

/* =========================================================
   PESAN ERROR (opsional)
   Menerima pesan dari login_process.php lewat salah satu:
   - $_SESSION['login_error'] atau $_SESSION['error']
   - parameter URL ?error=...
========================================================= */

$error = '';

if (!empty($_SESSION['login_error'])) {
    $error = (string) $_SESSION['login_error'];
    unset($_SESSION['login_error']);
} elseif (!empty($_SESSION['error'])) {
    $error = (string) $_SESSION['error'];
    unset($_SESSION['error']);
} elseif (isset($_GET['error']) && $_GET['error'] !== '') {
    $error = (string) $_GET['error'];

    if (in_array($error, ['1', 'true', 'invalid'], true)) {
        $error = 'Username atau password salah.';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Login | Monitoring Maintenance Alat Berat</title>

    <!-- Terapkan tema tersimpan (sama dengan dashboard) sebelum halaman tampil -->
    <script>
        try {
            document.documentElement.setAttribute(
                'data-theme',
                localStorage.getItem('theme') === 'dark' ? 'dark' : 'light'
            );
        } catch (e) {}
    </script>

    <link rel="stylesheet" href="login.css?v=4">

    <!-- Font Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
</head>

<body>

<div class="login-page" id="loginPage">

    <!-- Garis miring pemisah (dua lapis) -->
    <div class="slant-line line-two" aria-hidden="true"></div>
    <div class="slant-line line-one" aria-hidden="true"></div>

    <!-- ==============================
         PANEL KIRI: HERO
    =============================== -->
    <section class="hero-panel" id="heroPanel">

        <div class="hero-bg" id="heroBg" aria-hidden="true"></div>
        <div class="hero-shade" aria-hidden="true"></div>
        <div class="hero-glow" aria-hidden="true"></div>

        <header class="company-brand reveal" style="--d: 0.05s">
            <img src="../assets/logo.png" alt="Logo PT Biringkassi Raya">

            <div class="company-name">
                <strong>PT BIRINGKASSI RAYA</strong>
                <span>HEAVY EQUIPMENT SOLUTION</span>
            </div>
        </header>

        <div class="hero-content">

            <div class="eyebrow reveal" style="--d: 0.15s">
                <span></span>
                MAINTENANCE MANAGEMENT SYSTEM
            </div>

            <h1 class="reveal" style="--d: 0.25s">
                Monitoring<br>
                Pemeliharaan<br>
                <em>Alat Berat</em>
            </h1>

            <p class="hero-description reveal" style="--d: 0.35s">
                Kelola data, jadwal pemeliharaan, biaya operasional,
                dan performa alat berat dalam satu sistem
                yang terintegrasi.
            </p>

        </div>

        <div class="features">

            <article class="feature reveal" style="--d: 0.45s">
                <div class="feature-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24">
                        <circle cx="12" cy="12" r="9"></circle>
                        <path d="M12 7v5l3 2"></path>
                    </svg>
                </div>
                <div>
                    <h3>Monitoring Real-time</h3>
                    <p>Pantau kondisi dan operasional alat berat dengan mudah.</p>
                </div>
            </article>

            <article class="feature reveal" style="--d: 0.55s">
                <div class="feature-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24">
                        <path d="M3 20h18"></path>
                        <rect x="5" y="11" width="3" height="7"></rect>
                        <rect x="11" y="7" width="3" height="11"></rect>
                        <rect x="17" y="3" width="3" height="15"></rect>
                    </svg>
                </div>
                <div>
                    <h3>Data Terintegrasi</h3>
                    <p>Data pemeliharaan, biaya, dan performa dalam satu sistem.</p>
                </div>
            </article>

            <article class="feature reveal" style="--d: 0.65s">
                <div class="feature-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24">
                        <path d="M12 22s8-4 8-11V5l-8-3-8 3v6c0 7 8 11 8 11z"></path>
                        <path d="m9 12 2 2 4-4"></path>
                    </svg>
                </div>
                <div>
                    <h3>Operasional Efisien</h3>
                    <p>Dukung pengambilan keputusan untuk kinerja yang optimal.</p>
                </div>
            </article>

        </div>

        <footer class="hero-footer reveal" style="--d: 0.75s">
            <span class="footer-line"></span>
            <span>PT BIRINGKASSI RAYA</span>
            <span class="footer-divider">|</span>
            <span>HEAVY EQUIPMENT SOLUTION</span>
        </footer>

    </section>


    <!-- ==============================
         PANEL KANAN: FORM LOGIN
    =============================== -->
    <main class="form-panel">

        <div class="form-decoration decoration-top" aria-hidden="true"></div>
        <div class="form-decoration decoration-bottom" aria-hidden="true"></div>

        <!-- Tombol mode malam -->
        <button type="button" class="theme-btn" id="themeBtn" aria-label="Ganti mode terang/gelap" title="Ganti tema">
            <svg class="icon-moon" viewBox="0 0 24 24" aria-hidden="true">
                <path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8Z"></path>
            </svg>
            <svg class="icon-sun" viewBox="0 0 24 24" aria-hidden="true">
                <circle cx="12" cy="12" r="4"></circle>
                <path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"></path>
            </svg>
        </button>

        <div class="login-box<?= $error !== '' ? ' shake' : ''; ?>" id="loginBox">

            <div class="login-brand reveal" style="--d: 0.1s">
                <img src="../assets/logo.png" alt="Logo PT Biringkassi Raya">
                <h2>PT BIRINGKASSI RAYA</h2>
                <p>HEAVY EQUIPMENT SOLUTION</p>
            </div>

            <div class="welcome reveal" style="--d: 0.2s">
                <span class="greeting" id="greeting">Selamat datang</span>
                <h1>Masuk ke Sistem</h1>
                <p>
                    Gunakan akun Anda untuk mengakses sistem monitoring
                    pemeliharaan alat berat.
                </p>
            </div>

            <?php if ($error !== ''): ?>
                <div class="alert" role="alert">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <circle cx="12" cy="12" r="9"></circle>
                        <path d="M12 8v5M12 16.5v.01"></path>
                    </svg>
                    <span><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
            <?php endif; ?>

            <!-- Form tetap terhubung dengan proses login lama -->
            <form action="login_process.php" method="POST" id="loginForm" novalidate>

                <!-- Username -->
                <div class="form-group reveal" style="--d: 0.3s">

                    <label for="username">Username</label>

                    <div class="input-wrapper">
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <circle cx="12" cy="8" r="4"></circle>
                            <path d="M4 21v-2a8 8 0 0 1 16 0v2Z"></path>
                        </svg>

                        <input
                            type="text"
                            id="username"
                            name="username"
                            placeholder="Masukkan username"
                            autocomplete="username"
                            autofocus
                            required
                        >
                    </div>

                    <span class="field-error" id="usernameError" aria-live="polite"></span>
                </div>

                <!-- Password -->
                <div class="form-group reveal" style="--d: 0.4s">

                    <label for="password">Password</label>

                    <div class="input-wrapper">
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <rect x="4" y="10" width="16" height="12" rx="2"></rect>
                            <path d="M8 10V7a4 4 0 0 1 8 0v3"></path>
                            <path d="M12 14v4"></path>
                        </svg>

                        <input
                            type="password"
                            id="password"
                            name="password"
                            placeholder="Masukkan password"
                            autocomplete="current-password"
                            required
                        >

                        <button
                            type="button"
                            class="toggle-password"
                            id="togglePassword"
                            aria-label="Tampilkan password"
                            aria-pressed="false"
                        >
                            <svg class="eye-open" viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                            <svg class="eye-closed" viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M3 3l18 18"></path>
                                <path d="M10.6 5.1A10.4 10.4 0 0 1 12 5c6.4 0 10 7 10 7a17 17 0 0 1-3.2 4"></path>
                                <path d="M6.6 6.7C3.9 8.4 2 12 2 12s3.6 7 10 7c1.6 0 3-.4 4.3-1"></path>
                                <path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"></path>
                            </svg>
                        </button>
                    </div>

                    <span class="field-error" id="passwordError" aria-live="polite"></span>
                    <span class="caps-hint" id="capsHint" aria-live="polite">
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M12 3 4 12h4v5h8v-5h4L12 3Z"></path>
                            <path d="M8 21h8"></path>
                        </svg>
                        Caps Lock sedang aktif
                    </span>
                </div>

                <!-- Opsi -->
                <div class="form-options reveal" style="--d: 0.5s">
                    <label class="remember">
                        <input type="checkbox" id="rememberUser">
                        <span class="check" aria-hidden="true">
                            <svg viewBox="0 0 24 24"><path d="m5 12 5 5 9-10"></path></svg>
                        </span>
                        Ingat username
                    </label>
                </div>

                <!-- Tombol masuk -->
                <button type="submit" class="login-button reveal" id="loginButton" style="--d: 0.6s">
                    <span class="btn-label">Masuk</span>
                    <span class="spinner" aria-hidden="true"></span>
                    <svg class="btn-arrow" viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M5 12h14m-7-7 7 7-7 7"></path>
                    </svg>
                </button>

            </form>

            <p class="login-footnote reveal" style="--d: 0.7s">
                &copy; <?= date('Y'); ?> PT Biringkassi Raya
            </p>

        </div>

    </main>

</div>

<script>
(function () {

    var page      = document.getElementById('loginPage');
    var hero      = document.getElementById('heroPanel');
    var heroBg    = document.getElementById('heroBg');
    var box       = document.getElementById('loginBox');
    var form      = document.getElementById('loginForm');
    var userInput = document.getElementById('username');
    var passInput = document.getElementById('password');
    var toggleBtn = document.getElementById('togglePassword');
    var capsHint  = document.getElementById('capsHint');
    var remember  = document.getElementById('rememberUser');
    var loginBtn  = document.getElementById('loginButton');
    var themeBtn  = document.getElementById('themeBtn');
    var greeting  = document.getElementById('greeting');

    var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var finePointer  = window.matchMedia('(hover: hover) and (pointer: fine)').matches;


    /* ---------- Sapaan sesuai jam ---------- */
    var hour = new Date().getHours();
   

    if (hour >= 4 && hour < 11)       sapaan = 'Selamat pagi';
    else if (hour >= 11 && hour < 15) sapaan = 'PUNYA VANES';
   

    greeting.textContent = sapaan + ' \uD83D\uDC4B';


    /* ---------- Ganti tema (sinkron dengan dashboard) ---------- */
    themeBtn.addEventListener('click', function () {
        var isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        var next = isDark ? 'light' : 'dark';

        document.documentElement.setAttribute('data-theme', next);

        try { localStorage.setItem('theme', next); } catch (e) {}
    });


    /* ---------- Tampil / sembunyi password ---------- */
    toggleBtn.addEventListener('click', function () {
        var show = passInput.type === 'password';

        passInput.type = show ? 'text' : 'password';
        toggleBtn.classList.toggle('is-visible', show);

        toggleBtn.setAttribute('aria-pressed', String(show));
        toggleBtn.setAttribute('aria-label', show ? 'Sembunyikan password' : 'Tampilkan password');

        passInput.focus();
    });


    /* ---------- Peringatan Caps Lock ---------- */
    function checkCaps(e) {
        if (!e.getModifierState) return;
        capsHint.classList.toggle('show', e.getModifierState('CapsLock'));
    }

    passInput.addEventListener('keydown', checkCaps);
    passInput.addEventListener('keyup', checkCaps);
    passInput.addEventListener('blur', function () {
        capsHint.classList.remove('show');
    });


    /* ---------- Ingat username (hanya username, tidak pernah password) ---------- */
    try {
        var saved = localStorage.getItem('login_username');

        if (saved) {
            userInput.value = saved;
            remember.checked = true;
            passInput.focus();
        }
    } catch (e) {}


    /* ---------- Validasi ringan + getar ---------- */
    function setError(input, id, message) {
        var field = input.closest('.form-group');
        document.getElementById(id).textContent = message;
        field.classList.toggle('has-error', message !== '');
    }

    function shake() {
        box.classList.remove('shake');
        void box.offsetWidth;
        box.classList.add('shake');
    }

    userInput.addEventListener('input', function () { setError(userInput, 'usernameError', ''); });
    passInput.addEventListener('input', function () { setError(passInput, 'passwordError', ''); });

    form.addEventListener('submit', function (e) {

        var valid = true;

        if (userInput.value.trim() === '') {
            setError(userInput, 'usernameError', 'Username wajib diisi.');
            valid = false;
        }

        if (passInput.value === '') {
            setError(passInput, 'passwordError', 'Password wajib diisi.');
            valid = false;
        }

        if (!valid) {
            e.preventDefault();
            shake();
            (userInput.value.trim() === '' ? userInput : passInput).focus();
            return;
        }

        try {
            if (remember.checked) {
                localStorage.setItem('login_username', userInput.value.trim());
            } else {
                localStorage.removeItem('login_username');
            }
        } catch (err) {}

        /* status loading + cegah klik ganda */
        loginBtn.classList.add('loading');
        loginBtn.setAttribute('aria-busy', 'true');
        loginBtn.querySelector('.btn-label').textContent = 'Memproses...';
    });

    /* kalau pengguna menekan tombol Back, pulihkan tombol */
    window.addEventListener('pageshow', function (e) {
        if (e.persisted) {
            loginBtn.classList.remove('loading');
            loginBtn.removeAttribute('aria-busy');
            loginBtn.querySelector('.btn-label').textContent = 'Masuk';
        }
    });

    box.addEventListener('animationend', function (e) {
        if (e.animationName === 'shake') box.classList.remove('shake');
    });


    /* ---------- Efek riak pada tombol ---------- */
    loginBtn.addEventListener('click', function (e) {
        if (reduceMotion) return;

        var rect = loginBtn.getBoundingClientRect();
        var size = Math.max(rect.width, rect.height);
        var ripple = document.createElement('span');

        ripple.className = 'ripple';
        ripple.style.width = ripple.style.height = size + 'px';
        ripple.style.left = (e.clientX - rect.left - size / 2) + 'px';
        ripple.style.top  = (e.clientY - rect.top - size / 2) + 'px';

        loginBtn.appendChild(ripple);
        setTimeout(function () { ripple.remove(); }, 650);
    });


    /* ---------- Efek mouse: parallax hero + cahaya kartu ---------- */
    if (finePointer && !reduceMotion) {

        var ticking = false;
        var mx = 0, my = 0, gx = 50, gy = 50;

        page.addEventListener('mousemove', function (e) {
            mx = e.clientX / window.innerWidth - 0.5;
            my = e.clientY / window.innerHeight - 0.5;

            var rect = hero.getBoundingClientRect();
            gx = ((e.clientX - rect.left) / rect.width) * 100;
            gy = ((e.clientY - rect.top) / rect.height) * 100;

            if (!ticking) {
                ticking = true;

                requestAnimationFrame(function () {
                    heroBg.style.transform =
                        'translate3d(' + (mx * -26) + 'px,' + (my * -18) + 'px,0) scale(1.08)';

                    hero.style.setProperty('--gx', gx + '%');
                    hero.style.setProperty('--gy', gy + '%');

                    ticking = false;
                });
            }
        });

        box.addEventListener('mousemove', function (e) {
            var rect = box.getBoundingClientRect();

            box.style.setProperty('--cx', (e.clientX - rect.left) + 'px');
            box.style.setProperty('--cy', (e.clientY - rect.top) + 'px');
        });
    }

})();
</script>

</body>
</html>