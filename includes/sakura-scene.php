<?php
/*
|--------------------------------------------------------------------------
| ANIMASI TAMAN SAKURA (ilustrasi vektor, bukan pixel)
|--------------------------------------------------------------------------
| Cara pakai di halaman mana pun (taruh di dalam <main>):
|
|   <?php include 'includes/sakura-scene.php'; ?>
|
| Isi: deretan pohon sakura berwajah lucu, kelopak berjatuhan, awan,
| matahari / bulan, lampu lalu lintas, dan dump truck kuning yang berhenti
| di lampu merah lalu jalan lagi saat hijau.
|
| Ubah --cycle untuk mempercepat / memperlambat satu siklus truk.
*/
?>
<style>

    .sakura-box {
        padding: 0;
        overflow: hidden;
    }

    .sakura-scene {
        --cycle: 14s;
        --stop: 26%;           /* posisi truk saat berhenti */
        --truck-w: 200px;      /* lebar gambar truk */

        --sky-top: #ffe3ef;
        --sky-bottom: #fff8fb;
        --cloud: rgba(255, 255, 255, 0.95);
        --hill-back: #dff3e6;
        --hill-front: #c8ebd5;
        --curb: #f7c6da;
        --road: #7c7488;
        --dash: #fff3b0;
        --line: #ffffff;

        position: relative;
        height: 240px;
        overflow: hidden;
        background: linear-gradient(180deg, var(--sky-top), var(--sky-bottom));
    }

    [data-theme="dark"] .sakura-scene {
        --sky-top: #1b1030;
        --sky-bottom: #33204d;
        --cloud: rgba(255, 170, 210, 0.18);
        --hill-back: #2a2748;
        --hill-front: #231f3e;
        --curb: #4d3a60;
        --road: #251d30;
        --dash: #b8860b;
        --line: #a18bb5;
    }

    .sakura-scene svg {
        display: block;
    }

    /* ---------- matahari / bulan ---------- */

    .s-sun,
    .s-moon {
        position: absolute;
        top: 14px;
        left: 7%;
        width: 66px;
        z-index: 0;
    }

    .s-moon {
        display: none;
    }

    [data-theme="dark"] .s-sun {
        display: none;
    }

    [data-theme="dark"] .s-moon {
        display: block;
    }

    .sun-rays {
        transform-box: fill-box;
        transform-origin: center;
        animation: s-spin 30s linear infinite;
    }

    @keyframes s-spin {
        to { transform: rotate(360deg); }
    }

    /* bintang (mode malam) */

    .s-star {
        display: none;
        position: absolute;
        width: 5px;
        height: 5px;
        border-radius: 50%;
        background: #fff3b0;
        box-shadow: 0 0 6px rgba(255, 243, 176, 0.8);
        animation: s-twinkle 2.6s ease-in-out infinite;
    }

    [data-theme="dark"] .s-star {
        display: block;
    }

    .s-star.a { top: 18px; left: 24%; animation-delay: -0.4s; }
    .s-star.b { top: 40px; left: 36%; animation-delay: -1.3s; }
    .s-star.c { top: 14px; left: 52%; animation-delay: -0.8s; }
    .s-star.d { top: 46px; left: 68%; animation-delay: -1.9s; }
    .s-star.e { top: 20px; left: 82%; animation-delay: -0.1s; }
    .s-star.f { top: 38px; left: 94%; animation-delay: -1.5s; }

    @keyframes s-twinkle {
        0%, 100% { opacity: 1; transform: scale(1); }
        50%      { opacity: 0.3; transform: scale(0.7); }
    }

    /* ---------- awan ---------- */

    .s-cloud {
        position: absolute;
        width: 92px;
        z-index: 0;
        animation: s-drift linear infinite;
    }

    .s-cloud.c1 { top: 22px; animation-duration: 52s; animation-delay: -18s; }
    .s-cloud.c2 { top: 56px; width: 70px; animation-duration: 70s; animation-delay: -46s; }
    .s-cloud.c3 { top: 30px; width: 80px; animation-duration: 60s; animation-delay: -4s; }

    @keyframes s-drift {
        from { left: 106%; }
        to   { left: -120px; }
    }

    /* ---------- bukit ---------- */

    .s-hills {
        position: absolute;
        left: 0;
        bottom: 30px;
        width: 100%;
        height: 130px;
        z-index: 0;
    }

    /* ---------- pohon sakura ---------- */

    .s-tree {
        --blossom: #ff9ec4;
        --blossom-dark: #f27aa8;
        --blossom-light: #ffc9dc;
        --blossom-dot: #e85a93;
        --trunk: #8a5a44;

        position: absolute;
        bottom: 28px;
        width: var(--w, 110px);
        z-index: 1;
        transform-origin: 50% 100%;
        animation: s-sway 6s ease-in-out infinite;
        animation-delay: var(--sd, 0s);
    }

    .s-tree.far {
        --blossom: #ffb6d0;
        --blossom-dark: #f7a1c0;
        --blossom-light: #ffdbe8;
        --blossom-dot: #f07aa6;
        --trunk: #a77563;
    }

    .s-tree svg {
        width: 100%;
        height: auto;
    }

    [data-theme="dark"] .s-tree {
        filter: brightness(0.62) saturate(1.1);
    }

    @keyframes s-sway {
        0%, 100% { transform: rotate(-1.6deg); }
        50%      { transform: rotate(1.6deg); }
    }

    /* ---------- jalan ---------- */

    .s-road {
        position: absolute;
        left: 0;
        right: 0;
        bottom: 0;
        height: 34px;
        z-index: 2;
        background: var(--road);
        border-top: 4px solid var(--curb);
    }

    .s-road::after {
        content: "";
        position: absolute;
        left: 0;
        right: 0;
        top: 13px;
        height: 4px;
        border-radius: 2px;
        background: repeating-linear-gradient(
            90deg,
            var(--dash) 0 22px,
            transparent 22px 44px
        );
    }

    /* kelopak yang sudah jatuh di tepi jalan */

    .s-fallen {
        position: absolute;
        left: 0;
        right: 0;
        bottom: 30px;
        height: 6px;
        z-index: 3;
        background:
            radial-gradient(ellipse 5px 2.5px at 40px 4px, #ffb3d1 90%, transparent 100%) 0 0 / 130px 6px repeat-x,
            radial-gradient(ellipse 4px 2px at 20px 3px, #ff8fb8 90%, transparent 100%) 0 0 / 97px 6px repeat-x,
            radial-gradient(ellipse 4px 2px at 12px 4px, #ffe0ec 90%, transparent 100%) 0 0 / 71px 6px repeat-x;
    }

    .s-stopline {
        position: absolute;
        left: calc(var(--stop) + var(--truck-w) + 6px);
        bottom: 0;
        width: 6px;
        height: 30px;
        z-index: 3;
        border-radius: 2px;
        background: var(--line);
    }

    /* ---------- lampu lalu lintas ---------- */

    .s-light {
        position: absolute;
        left: calc(var(--stop) + var(--truck-w) + 34px);
        bottom: 30px;
        width: 34px;
        z-index: 3;
    }

    .s-light svg {
        width: 100%;
        height: auto;
    }

    .lamp {
        animation-duration: var(--cycle);
        animation-iteration-count: infinite;
        animation-timing-function: steps(1, end);
    }

    .lamp-red    { opacity: 1;    animation-name: s-lamp-red; }
    .lamp-yellow { opacity: 0.22; animation-name: s-lamp-yellow; }
    .lamp-green  { opacity: 0.22; animation-name: s-lamp-green; }

    @keyframes s-lamp-red {
        0%   { opacity: 1; }
        56%  { opacity: 0.22; }
        90%  { opacity: 1; }
    }

    @keyframes s-lamp-yellow {
        0%   { opacity: 0.22; }
        82%  { opacity: 1; }
        90%  { opacity: 0.22; }
    }

    @keyframes s-lamp-green {
        0%   { opacity: 0.22; }
        56%  { opacity: 1; }
        82%  { opacity: 0.22; }
    }

    /* ---------- truk ---------- */
    /* datang dari kiri, berhenti di lampu merah, lalu jalan ke kanan */

    .s-truck {
        position: absolute;
        bottom: 25px;
        left: -260px;
        width: var(--truck-w);
        z-index: 4;
        animation: s-truck-drive var(--cycle) infinite;
    }

    .s-truck svg {
        width: 100%;
        height: auto;
        transform-origin: 50% 100%;
        animation: s-truck-bounce 0.45s ease-in-out infinite alternate;
    }

    @keyframes s-truck-drive {
        0%   { left: -260px;          animation-timing-function: cubic-bezier(0.2, 0.6, 0.3, 1); }
        38%  { left: var(--stop); }
        58%  { left: var(--stop);     animation-timing-function: cubic-bezier(0.6, 0, 0.9, 0.6); }
        95%  { left: 106%; }
        100% { left: 106%; }
    }

    @keyframes s-truck-bounce {
        from { transform: translateY(0) rotate(0deg); }
        to   { transform: translateY(-2px) rotate(-0.35deg); }
    }

    /* roda berputar hanya saat truk bergerak */

    .spin {
        transform-box: fill-box;
        transform-origin: center;
        animation: s-wheel var(--cycle) linear infinite;
    }

    @keyframes s-wheel {
        0%   { transform: rotate(0deg);    animation-timing-function: cubic-bezier(0.2, 0.6, 0.3, 1); }
        38%  { transform: rotate(1500deg); }
        58%  { transform: rotate(1500deg); animation-timing-function: cubic-bezier(0.6, 0, 0.9, 0.6); }
        95%  { transform: rotate(4500deg); }
        100% { transform: rotate(4500deg); }
    }

    /* hati kecil dari knalpot */

    .heart {
        transform-box: fill-box;
        transform-origin: center;
        animation: s-heart 1.8s ease-out infinite;
    }

    .heart.h2 { animation-delay: -0.9s; }

    @keyframes s-heart {
        0%   { transform: translate(0, 0) scale(0.6);      opacity: 0; }
        20%  { opacity: 1; }
        100% { transform: translate(-6px, -16px) scale(1); opacity: 0; }
    }

    /* ---------- kelopak berjatuhan ---------- */

    .s-petals {
        position: absolute;
        inset: 0;
        z-index: 5;
        pointer-events: none;
    }

    .s-petal {
        --p1: #ffd0e1;
        --p2: #ff9ec4;

        position: absolute;
        top: 0;
        left: var(--x);
        width: 9px;
        height: 9px;
        border-radius: 100% 0 100% 0;
        background: linear-gradient(135deg, var(--p1), var(--p2));
        opacity: 0;
        scale: var(--sc, 1);
        animation: s-fall var(--d) linear infinite;
        animation-delay: var(--delay);
    }

    .s-petal.pc1 { --p1: #ff9ec4; --p2: #f27aa8; }
    .s-petal.pc2 { --p1: #fff0f6; --p2: #ffc2d9; }

    @keyframes s-fall {
        0%   { transform: translate(0, -14px) rotate(0deg);      opacity: 0; }
        8%   { opacity: 1; }
        25%  { transform: translate(-26px, 54px) rotate(110deg); }
        50%  { transform: translate(10px, 112px) rotate(220deg); }
        75%  { transform: translate(-30px, 168px) rotate(330deg); }
        92%  { opacity: 1; }
        100% { transform: translate(-8px, 232px) rotate(440deg); opacity: 0; }
    }

    /* ---------- layar kecil ---------- */

    @media (max-width: 650px) {

        .sakura-scene {
            --truck-w: 150px;
            --stop: 3%;
        }

        .s-tree:nth-of-type(n+5) {
            display: none;
        }
    }

    /* hormati pengguna yang mematikan animasi */

    @media (prefers-reduced-motion: reduce) {

        .sakura-scene *,
        .sakura-scene *::before,
        .sakura-scene *::after {
            animation: none !important;
        }

        .s-petals {
            display: none;
        }

        .s-truck {
            left: var(--stop);
        }
    }

</style>


<section
    class="box sakura-box"
    role="img"
    aria-label="Animasi taman sakura: pohon-pohon sakura berwajah lucu, kelopak berjatuhan, dan dump truck kuning yang berhenti di lampu merah"
>

    <div class="sakura-scene" aria-hidden="true">

        <!-- ===== kumpulan gambar yang dipakai ulang ===== -->
        <svg width="0" height="0" style="position:absolute">
            <defs>

                <!-- pohon sakura polos -->
                <symbol id="sk-tree" viewBox="0 0 100 120">

                    <!-- batang -->
                    <path d="M43 120 C45 100 46 90 45 76 L55 76 C54 90 55 100 58 120 Z" fill="var(--trunk, #8a5a44)"/>
                    <path d="M52 76 C52 92 53 104 55 120 L58 120 C55 100 54 90 55 76 Z" fill="#000" opacity="0.12"/>
                    <path d="M50 98 C56 92 62 88 69 85" fill="none" stroke="var(--trunk, #8a5a44)" stroke-width="3.2" stroke-linecap="round"/>
                    <path d="M48 92 C42 86 37 84 31 81" fill="none" stroke="var(--trunk, #8a5a44)" stroke-width="3" stroke-linecap="round"/>

                    <!-- tajuk: lapisan bayangan -->
                    <g fill="var(--blossom-dark, #f27aa8)">
                        <circle cx="53" cy="55" r="30"/>
                        <circle cx="31" cy="63" r="20"/>
                        <circle cx="75" cy="63" r="20"/>
                        <circle cx="39" cy="39" r="20"/>
                        <circle cx="65" cy="38" r="20"/>
                        <circle cx="51" cy="27" r="18"/>
                    </g>

                    <!-- tajuk: lapisan utama -->
                    <g fill="var(--blossom, #ff9ec4)">
                        <circle cx="51" cy="52" r="30"/>
                        <circle cx="29" cy="60" r="20"/>
                        <circle cx="73" cy="60" r="20"/>
                        <circle cx="37" cy="36" r="20"/>
                        <circle cx="63" cy="35" r="20"/>
                        <circle cx="49" cy="24" r="18"/>
                    </g>

                    <!-- sisi terang -->
                    <g fill="var(--blossom-light, #ffc9dc)">
                        <circle cx="36" cy="30" r="9"/>
                        <circle cx="25" cy="52" r="7"/>
                        <circle cx="50" cy="16" r="8"/>
                        <circle cx="60" cy="40" r="5"/>
                        <circle cx="71" cy="30" r="5"/>
                    </g>

                    <!-- bunga kecil -->
                    <g fill="var(--blossom-dot, #e85a93)">
                        <circle cx="30" cy="44" r="1.8"/>
                        <circle cx="44" cy="20" r="1.8"/>
                        <circle cx="62" cy="22" r="1.8"/>
                        <circle cx="76" cy="46" r="1.8"/>
                        <circle cx="66" cy="70" r="1.8"/>
                        <circle cx="38" cy="72" r="1.8"/>
                        <circle cx="52" cy="40" r="1.6"/>
                    </g>

                </symbol>

                <!-- pohon sakura tersenyum -->
                <symbol id="sk-tree-face" viewBox="0 0 100 120">

                    <use href="#sk-tree" width="100" height="120"/>

                    <!-- mata -->
                    <ellipse cx="43" cy="55" rx="2.5" ry="3.2" fill="#3a2330"/>
                    <ellipse cx="61" cy="55" rx="2.5" ry="3.2" fill="#3a2330"/>
                    <circle cx="43.9" cy="53.9" r="0.9" fill="#ffffff"/>
                    <circle cx="61.9" cy="53.9" r="0.9" fill="#ffffff"/>

                    <!-- senyum -->
                    <path d="M47 62 Q52 68 57 62" fill="none" stroke="#3a2330" stroke-width="1.7" stroke-linecap="round"/>

                    <!-- pipi merona -->
                    <ellipse cx="36" cy="61" rx="4.2" ry="2.6" fill="#ff6f9f" opacity="0.55"/>
                    <ellipse cx="68" cy="61" rx="4.2" ry="2.6" fill="#ff6f9f" opacity="0.55"/>

                </symbol>

            </defs>
        </svg>


        <!-- bintang (mode malam) -->
        <i class="s-star a"></i>
        <i class="s-star b"></i>
        <i class="s-star c"></i>
        <i class="s-star d"></i>
        <i class="s-star e"></i>
        <i class="s-star f"></i>


        <!-- matahari tersenyum (mode terang) -->
        <svg class="s-sun" viewBox="0 0 60 60">
            <g class="sun-rays" stroke="#ffc933" stroke-width="3" stroke-linecap="round">
                <path d="M30 3v6"/>
                <path d="M30 51v6"/>
                <path d="M3 30h6"/>
                <path d="M51 30h6"/>
                <path d="M11 11l4 4"/>
                <path d="M45 45l4 4"/>
                <path d="M49 11l-4 4"/>
                <path d="M15 45l-4 4"/>
            </g>
            <circle cx="30" cy="30" r="14" fill="#ffd95a"/>
            <circle cx="30" cy="30" r="14" fill="none" stroke="#ffc933" stroke-width="1.5"/>
            <circle cx="25" cy="29" r="1.5" fill="#6b3a2a"/>
            <circle cx="35" cy="29" r="1.5" fill="#6b3a2a"/>
            <path d="M26 34 Q30 38 34 34" fill="none" stroke="#6b3a2a" stroke-width="1.5" stroke-linecap="round"/>
            <circle cx="22" cy="33" r="2.4" fill="#ff9ec4" opacity="0.7"/>
            <circle cx="38" cy="33" r="2.4" fill="#ff9ec4" opacity="0.7"/>
        </svg>

        <!-- bulan mengantuk (mode malam) -->
        <svg class="s-moon" viewBox="0 0 60 60">
            <path d="M38 8 A22 22 0 1 0 52 40 A17 17 0 0 1 38 8 Z" fill="#fff3c4"/>
            <path d="M22 32 q2.5 2.5 5 0" fill="none" stroke="#6b5a3a" stroke-width="1.4" stroke-linecap="round"/>
            <path d="M31 38 q2.5 2.5 5 0" fill="none" stroke="#6b5a3a" stroke-width="1.4" stroke-linecap="round"/>
            <path d="M25 40 q2 2 4 0" fill="none" stroke="#6b5a3a" stroke-width="1.3" stroke-linecap="round"/>
            <circle cx="19" cy="38" r="2.2" fill="#ff9ec4" opacity="0.6"/>
            <circle cx="37" cy="44" r="2.2" fill="#ff9ec4" opacity="0.6"/>
        </svg>


        <!-- awan -->
        <svg class="s-cloud c1" viewBox="0 0 80 36">
            <g fill="var(--cloud)">
                <circle cx="22" cy="22" r="12"/>
                <circle cx="38" cy="16" r="15"/>
                <circle cx="57" cy="22" r="12"/>
                <rect x="12" y="22" width="56" height="12" rx="6"/>
            </g>
            <circle cx="33" cy="23" r="1.4" fill="#8a6a7a"/>
            <circle cx="45" cy="23" r="1.4" fill="#8a6a7a"/>
            <path d="M36 26 Q39 29 42 26" fill="none" stroke="#8a6a7a" stroke-width="1.3" stroke-linecap="round"/>
            <circle cx="29" cy="26" r="2" fill="#ff9ec4" opacity="0.6"/>
            <circle cx="49" cy="26" r="2" fill="#ff9ec4" opacity="0.6"/>
        </svg>

        <svg class="s-cloud c2" viewBox="0 0 80 36">
            <g fill="var(--cloud)">
                <circle cx="22" cy="22" r="12"/>
                <circle cx="38" cy="16" r="15"/>
                <circle cx="57" cy="22" r="12"/>
                <rect x="12" y="22" width="56" height="12" rx="6"/>
            </g>
        </svg>

        <svg class="s-cloud c3" viewBox="0 0 80 36">
            <g fill="var(--cloud)">
                <circle cx="22" cy="22" r="12"/>
                <circle cx="38" cy="16" r="15"/>
                <circle cx="57" cy="22" r="12"/>
                <rect x="12" y="22" width="56" height="12" rx="6"/>
            </g>
        </svg>


        <!-- bukit -->
        <svg class="s-hills" viewBox="0 0 1000 130" preserveAspectRatio="none">
            <path d="M0 70 C120 15 250 15 370 58 S 640 105 770 50 S 930 25 1000 55 L1000 130 L0 130 Z" fill="var(--hill-back)"/>
            <path d="M0 96 C140 62 270 72 390 92 S 650 118 790 84 S 940 68 1000 88 L1000 130 L0 130 Z" fill="var(--hill-front)"/>
        </svg>


        <!-- deretan pohon sakura -->
        <div class="s-tree" style="left: 1%;  --w: 122px; --sd: -0.8s;"><svg viewBox="0 0 100 120"><use href="#sk-tree-face"/></svg></div>
        <div class="s-tree far" style="left: 14%; --w: 92px;  --sd: -2.4s;"><svg viewBox="0 0 100 120"><use href="#sk-tree"/></svg></div>
        <div class="s-tree" style="left: 25%; --w: 134px; --sd: -4.1s;"><svg viewBox="0 0 100 120"><use href="#sk-tree"/></svg></div>
        <div class="s-tree far" style="left: 40%; --w: 102px; --sd: -1.7s;"><svg viewBox="0 0 100 120"><use href="#sk-tree-face"/></svg></div>
        <div class="s-tree" style="left: 52%; --w: 120px; --sd: -3.3s;"><svg viewBox="0 0 100 120"><use href="#sk-tree"/></svg></div>
        <div class="s-tree far" style="left: 65%; --w: 96px;  --sd: -5.0s;"><svg viewBox="0 0 100 120"><use href="#sk-tree"/></svg></div>
        <div class="s-tree" style="left: 76%; --w: 138px; --sd: -2.0s;"><svg viewBox="0 0 100 120"><use href="#sk-tree-face"/></svg></div>
        <div class="s-tree far" style="left: 90%; --w: 104px; --sd: -3.8s;"><svg viewBox="0 0 100 120"><use href="#sk-tree"/></svg></div>


        <!-- jalan -->
        <div class="s-road"></div>
        <div class="s-fallen"></div>
        <div class="s-stopline"></div>


        <!-- lampu lalu lintas -->
        <div class="s-light">
            <svg viewBox="0 0 32 90">

                <!-- tiang -->
                <rect x="14" y="50" width="4" height="38" rx="2" fill="#7b7f8c"/>
                <rect x="9" y="86" width="14" height="4" rx="2" fill="#5d6170"/>

                <!-- kotak lampu -->
                <rect x="4" y="0" width="24" height="54" rx="10" fill="#2b2f3a"/>
                <rect x="6" y="2" width="20" height="50" rx="8" fill="#1a1d25"/>

                <!-- merah -->
                <g class="lamp lamp-red">
                    <circle cx="16" cy="13" r="10" fill="#ff5d73" opacity="0.28"/>
                    <circle cx="16" cy="13" r="7" fill="#ff5d73"/>
                    <circle cx="14" cy="11" r="2" fill="#ffffff" opacity="0.6"/>
                </g>

                <!-- kuning -->
                <g class="lamp lamp-yellow">
                    <circle cx="16" cy="28" r="10" fill="#ffd23f" opacity="0.28"/>
                    <circle cx="16" cy="28" r="7" fill="#ffd23f"/>
                    <circle cx="14" cy="26" r="2" fill="#ffffff" opacity="0.6"/>
                </g>

                <!-- hijau -->
                <g class="lamp lamp-green">
                    <circle cx="16" cy="43" r="10" fill="#4cd964" opacity="0.28"/>
                    <circle cx="16" cy="43" r="7" fill="#4cd964"/>
                    <circle cx="14" cy="41" r="2" fill="#ffffff" opacity="0.6"/>
                </g>

            </svg>
        </div>


        <!-- dump truck kuning (menghadap kanan) -->
        <div class="s-truck">
            <svg viewBox="0 0 196 126">

                <defs>
                    <linearGradient id="skBody" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0" stop-color="#ffcb3d"/>
                        <stop offset="1" stop-color="#f2a900"/>
                    </linearGradient>
                    <linearGradient id="skGlass" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0" stop-color="#d9f1ff"/>
                        <stop offset="1" stop-color="#9fd4f5"/>
                    </linearGradient>
                </defs>

                <!-- bayangan di jalan -->
                <ellipse cx="98" cy="122" rx="88" ry="4" fill="#000" opacity="0.18"/>

                <!-- hati kecil dari knalpot -->
                <g fill="#ff7aa8">
                    <path class="heart" d="M114 12 C109 7 111 3 114 6 C117 3 119 7 114 12Z"/>
                </g>
                <g fill="#ffb3d1">
                    <path class="heart h2" d="M114 12 C109 7 111 3 114 6 C117 3 119 7 114 12Z"/>
                </g>

                <!-- knalpot -->
                <rect x="109" y="14" width="5" height="28" rx="2" fill="#4b5058"/>
                <rect x="108" y="13" width="7" height="3" rx="1.5" fill="#5d636c"/>

                <!-- roda belakang (lapisan belakang) -->
                <g transform="translate(86 100)">
                    <circle r="21" fill="#14161a"/>
                    <g class="spin">
                        <circle r="18" fill="none" stroke="#262930" stroke-width="3" stroke-dasharray="3 3"/>
                        <circle r="11" fill="#e0a300"/>
                    </g>
                </g>

                <!-- muatan bunga sakura di dalam bak -->
                <g>
                    <circle cx="24" cy="40" r="5.5" fill="#ff9ec4"/>
                    <circle cx="34" cy="37" r="6.5" fill="#ffb3d1"/>
                    <circle cx="46" cy="33" r="7" fill="#ff9ec4"/>
                    <circle cx="58" cy="29" r="7" fill="#ffc2d9"/>
                    <circle cx="70" cy="25" r="6.5" fill="#ff9ec4"/>
                    <circle cx="82" cy="22" r="6" fill="#ffb3d1"/>
                    <circle cx="92" cy="19.5" r="5.5" fill="#ff9ec4"/>
                    <circle cx="40" cy="31" r="4" fill="#ffe0ec"/>
                    <circle cx="52" cy="26" r="4" fill="#ffe0ec"/>
                    <circle cx="64" cy="22" r="4" fill="#ffe0ec"/>
                    <circle cx="76" cy="19" r="4" fill="#ffe0ec"/>
                    <circle cx="30" cy="35" r="1.3" fill="#e85a93"/>
                    <circle cx="50" cy="29" r="1.3" fill="#e85a93"/>
                    <circle cx="67" cy="22" r="1.3" fill="#e85a93"/>
                    <circle cx="86" cy="18" r="1.3" fill="#e85a93"/>
                </g>

                <!-- bak dump -->
                <polygon points="12,46 96,21 150,19 152,27 106,31 110,74 24,74" fill="url(#skBody)"/>
                <polygon points="12,46 96,21 150,19 150,23 98,26 16,53" fill="#ffe08a" opacity="0.7"/>
                <polygon points="24,66 110,66 110,74 24,74" fill="#d99a00" opacity="0.5"/>
                <polygon points="106,31 152,27 152,29 107,34" fill="#000" opacity="0.15"/>

                <!-- rusuk bak -->
                <g stroke="#d99a00" stroke-width="2.4" stroke-linecap="round">
                    <path d="M36 42 L38 72"/>
                    <path d="M92 24 L96 72"/>
                    <path d="M76 29 L78 72"/>
                </g>

                <!-- plat nomor unit -->
                <rect x="46" y="44" width="30" height="13" rx="3.5" fill="#ffffff"/>
                <text x="61" y="53.8" text-anchor="middle" font-family="Arial, Helvetica, sans-serif" font-size="8" font-weight="700" fill="#2a2d34">DT-05</text>

                <!-- rangka -->
                <rect x="22" y="73" width="162" height="10" rx="3" fill="#3b3f47"/>

                <!-- badan depan / kap mesin -->
                <rect x="118" y="50" width="62" height="26" rx="6" fill="url(#skBody)"/>
                <rect x="118" y="66" width="62" height="2" fill="#d99a00" opacity="0.6"/>

                <!-- kabin -->
                <rect x="122" y="28" width="44" height="24" rx="6" fill="#2a2d34"/>
                <rect x="128" y="32" width="34" height="16" rx="4" fill="url(#skGlass)"/>
                <polygon points="130,33 141,33 135,47 130,47" fill="#ffffff" opacity="0.35"/>

                <!-- sopir lucu -->
                <circle cx="147" cy="42" r="5.4" fill="#ffd9b8"/>
                <path d="M141.6 41 A5.4 5.4 0 0 1 152.4 41 Z" fill="#ffcf33"/>
                <circle cx="145.2" cy="43" r="0.8" fill="#3a2330"/>
                <circle cx="149" cy="43" r="0.8" fill="#3a2330"/>
                <path d="M145.6 45.2 Q147.2 46.8 148.8 45.2" fill="none" stroke="#3a2330" stroke-width="0.8" stroke-linecap="round"/>
                <circle cx="143.6" cy="44.8" r="1.1" fill="#ff8fb1" opacity="0.8"/>
                <circle cx="150.6" cy="44.8" r="1.1" fill="#ff8fb1" opacity="0.8"/>

                <!-- spion -->
                <rect x="167" y="38" width="2" height="11" fill="#23262c"/>
                <rect x="168" y="36" width="6" height="7" rx="1.5" fill="#23262c"/>

                <!-- tangga -->
                <g stroke="#2a2d34" stroke-width="1.6" stroke-linecap="round">
                    <path d="M123 52 L119 75"/>
                    <path d="M122 58 H127"/>
                    <path d="M121 64 H126"/>
                    <path d="M120 70 H125"/>
                </g>

                <!-- gril depan + lampu -->
                <rect x="166" y="54" width="12" height="20" rx="2.5" fill="#23262c"/>
                <g fill="#4a4f58">
                    <rect x="168" y="57" width="8" height="1.4" rx="0.7"/>
                    <rect x="168" y="61" width="8" height="1.4" rx="0.7"/>
                    <rect x="168" y="65" width="8" height="1.4" rx="0.7"/>
                    <rect x="168" y="69" width="8" height="1.4" rx="0.7"/>
                </g>
                <circle cx="175" cy="52.5" r="4" fill="#fff7c2" opacity="0.35"/>
                <circle cx="175" cy="52.5" r="2.2" fill="#fff7c2"/>

                <!-- bumper -->
                <rect x="150" y="76" width="36" height="8" rx="3" fill="url(#skBody)"/>
                <rect x="150" y="76" width="36" height="8" rx="3" fill="none" stroke="#d99a00" stroke-width="1"/>

                <!-- roda belakang utama -->
                <g transform="translate(58 96)">
                    <circle r="25" fill="#1c1e23"/>
                    <g class="spin">
                        <circle r="22" fill="none" stroke="#2c2f36" stroke-width="3.2" stroke-dasharray="3 3"/>
                        <circle r="13.5" fill="#ffc52e"/>
                        <circle r="13.5" fill="none" stroke="#d99a00" stroke-width="1.5"/>
                        <g fill="#ff9ec4">
                            <ellipse cx="0" cy="-5.2" rx="2.7" ry="3.7"/>
                            <ellipse cx="0" cy="-5.2" rx="2.7" ry="3.7" transform="rotate(72)"/>
                            <ellipse cx="0" cy="-5.2" rx="2.7" ry="3.7" transform="rotate(144)"/>
                            <ellipse cx="0" cy="-5.2" rx="2.7" ry="3.7" transform="rotate(216)"/>
                            <ellipse cx="0" cy="-5.2" rx="2.7" ry="3.7" transform="rotate(288)"/>
                        </g>
                        <circle r="1.9" fill="#fff3a0"/>
                    </g>
                </g>

                <!-- roda depan -->
                <g transform="translate(160 99)">
                    <circle r="22" fill="#1c1e23"/>
                    <g class="spin">
                        <circle r="19.5" fill="none" stroke="#2c2f36" stroke-width="3" stroke-dasharray="3 3"/>
                        <circle r="12" fill="#ffc52e"/>
                        <circle r="12" fill="none" stroke="#d99a00" stroke-width="1.4"/>
                        <g fill="#ff9ec4">
                            <ellipse cx="0" cy="-4.7" rx="2.4" ry="3.3"/>
                            <ellipse cx="0" cy="-4.7" rx="2.4" ry="3.3" transform="rotate(72)"/>
                            <ellipse cx="0" cy="-4.7" rx="2.4" ry="3.3" transform="rotate(144)"/>
                            <ellipse cx="0" cy="-4.7" rx="2.4" ry="3.3" transform="rotate(216)"/>
                            <ellipse cx="0" cy="-4.7" rx="2.4" ry="3.3" transform="rotate(288)"/>
                        </g>
                        <circle r="1.7" fill="#fff3a0"/>
                    </g>
                </g>

            </svg>
        </div>


        <!-- kelopak sakura berjatuhan -->
        <div class="s-petals">
            <?php for ($i = 0; $i < 24; $i++):

                $petal_x     = 3 + (($i * 37) % 94);
                $petal_dur   = 8 + (($i * 5) % 7);
                $petal_delay = -1 * fmod($i * 1.7, $petal_dur);
                $petal_scale = [1, 1.4, 0.8][$i % 3];

            ?>
                <i
                    class="s-petal pc<?= $i % 3; ?>"
                    style="--x: <?= $petal_x; ?>%; --d: <?= $petal_dur; ?>s; --delay: <?= round($petal_delay, 1); ?>s; --sc: <?= $petal_scale; ?>;"
                ></i>
            <?php endfor; ?>
        </div>

    </div>

</section>