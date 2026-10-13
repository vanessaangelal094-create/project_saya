
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Login | Monitoring Maintenance Alat Berat</title>

    <!-- Menghubungkan file CSS pada folder auth -->
    <link rel="stylesheet" href="login.css?v=3">

    <!-- Font Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
</head>

<body>

<div class="login-page">

    <!-- ==============================
         PANEL KIRI: HERO
    =============================== -->
    <section class="hero-panel">

        <div class="hero-overlay"></div>

        <!-- Logo dan identitas perusahaan -->
        <header class="company-brand">
            <img
                src="../assets/logo.png"
                alt="Logo PT Biringkassi Raya"
            >

            <div class="company-name">
                <strong>PT BIRINGKASSI RAYA</strong>
                <span>HEAVY EQUIPMENT SOLUTION</span>
            </div>
        </header>

        <!-- Judul dan deskripsi -->
        <div class="hero-content">

            <div class="eyebrow">
                <span></span>
                MAINTENANCE MANAGEMENT SYSTEM
            </div>

            <h1>
                Monitoring<br>
                Pemeliharaan<br>
                <span>Alat Berat</span>
            </h1>

            <p class="hero-description">
                Kelola data, jadwal pemeliharaan, biaya operasional,
                dan performa alat berat dalam satu sistem
                yang terintegrasi.
            </p>

        </div>

        <!-- Keunggulan pertama -->
        <div class="features">

            <article class="feature">
                <div class="feature-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24">
                        <circle cx="12" cy="12" r="3"></circle>
                        <path d="M19 13a7 7 0 0 0 0-2l2-1.5-2-3.5-2.3 1a7 7 0 0 0-2-1.2L14.3 3h-4.6l-.4 2.8a7 7 0 0 0-2 1.2l-2.3-1-2 3.5 2 1.5a7 7 0 0 0 0 2l-2 1.5 2 3.5 2.3-1a7 7 0 0 0 2 1.2l.4 2.8h4.6l.4-2.8a7 7 0 0 0 2-1.2l2.3 1 2-3.5z"></path>
                    </svg>
                </div>

                <div>
                    <h3>Monitoring<br>Real-time</h3>
                    <p>
                        Pantau kondisi dan operasional alat berat
                        dengan mudah.
                    </p>
                </div>
            </article>

            <!-- Keunggulan kedua -->
            <article class="feature">
                <div class="feature-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24">
                        <path d="M3 20h18"></path>
                        <rect x="5" y="11" width="3" height="7"></rect>
                        <rect x="11" y="7" width="3" height="11"></rect>
                        <rect x="17" y="3" width="3" height="15"></rect>
                    </svg>
                </div>

                <div>
                    <h3>Data<br>Terintegrasi</h3>
                    <p>
                        Data pemeliharaan, biaya, dan performa
                        dalam satu sistem.
                    </p>
                </div>
            </article>

            <!-- Keunggulan ketiga -->
            <article class="feature">
                <div class="feature-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24">
                        <path d="M12 22s8-4 8-11V5l-8-3-8 3v6c0 7 8 11 8 11z"></path>
                        <path d="m9 12 2 2 4-4"></path>
                    </svg>
                </div>

                <div>
                    <h3>Operasional<br>Lebih Efisien</h3>
                    <p>
                        Dukung pengambilan keputusan untuk
                        kinerja yang optimal.
                    </p>
                </div>
            </article>

        </div>

        <!-- Footer hero -->
        <footer class="hero-footer">
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

        <!-- Dekorasi background -->
        <div class="form-decoration decoration-top"></div>
        <div class="form-decoration decoration-bottom"></div>

        <div class="login-box">

            <!-- Logo perusahaan -->
            <div class="login-brand">
                <img
                    src="../assets/logo.png"
                    alt="Logo PT Biringkassi Raya"
                >

                <h2>PT BIRINGKASSI RAYA</h2>
                <p>HEAVY EQUIPMENT SOLUTION</p>
            </div>

            <!-- Sambutan -->
            <div class="welcome">
                <h1>Selamat Datang Kembali</h1>

                <p>
                    Masuk untuk mengakses sistem monitoring
                    pemeliharaan alat berat.
                </p>
            </div>

            <!-- Form tetap terhubung dengan proses login lama -->
            <form action="login_process.php" method="POST">

                <!-- Username -->
                <div class="form-group">

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
                            required
                        >

                    </div>
                </div>

                <!-- Password -->
                <div class="form-group">

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

                        <!-- Tombol tampilkan password -->
                        <button
                            type="button"
                            class="toggle-password"
                            id="togglePassword"
                            aria-label="Tampilkan password"
                            aria-pressed="false"
                        >
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                        </button>

                    </div>
                </div>

                <!-- Tombol masuk -->
                <button type="submit" class="login-button">

                    <span>Masuk</span>

                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M5 12h14m-7-7 7 7-7 7"></path>
                    </svg>

                </button>

            </form>

        </div>

    </main>

</div>

<!-- Interaksi tampil/sembunyi password -->
<script>
    const passwordInput = document.getElementById('password');
    const toggleButton = document.getElementById('togglePassword');

    toggleButton.addEventListener('click', function () {

        const showPassword = passwordInput.type === 'password';

        passwordInput.type = showPassword ? 'text' : 'password';

        toggleButton.setAttribute(
            'aria-pressed',
            String(showPassword)
        );

        toggleButton.setAttribute(
            'aria-label',
            showPassword
                ? 'Sembunyikan password'
                : 'Tampilkan password'
        );

    });
</script>

</body>
</html>
