<?php
/*
|--------------------------------------------------------------------------
| TEMA LAUT (khusus halaman Spare Part)
|--------------------------------------------------------------------------
| Cara pakai:
|
| 1. Di <head> halaman, SEBELUM <link ... app.css>, tambahkan:
|
|      <script>
|          try {
|              if (localStorage.getItem('sparepart_ocean_theme') === 'on') {
|                  document.documentElement.classList.add('ocean-on');
|              }
|          } catch (e) {}
|      </script>
|
| 2. Di <body>, SEBELUM include sidebar:
|
|      <?php
|      $base   = '../';
|      $active = 'sparepart';
|      include $base . 'includes/ocean-theme.php';
|      include $base . 'includes/sidebar.php';
|      ?>
|
| 3. Di header halaman, taruh tombolnya:
|
|      <button type="button" class="ocean-btn" id="oceanToggle" aria-pressed="false">
|          ...ikon...
|          <span class="ocean-btn-label">Aktifkan Tema Laut</span>
|      </button>
|
| Tema hanya aktif saat tombol dinyalakan. Semua gaya dibatasi oleh
| html.ocean-on, jadi halaman lain dan sidebar.php TIDAK ikut berubah.
*/
?>
<style>

    /* =====================================================
       TOMBOL AKTIFKAN (selalu terlihat)
    ===================================================== */

    .ocean-btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        height: 38px;
        padding: 0 16px;
        border: 1px solid var(--primary);
        border-radius: var(--radius-sm);
        background: var(--surface);
        color: var(--primary);
        font-family: inherit;
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        transition: background 0.2s, color 0.2s, transform 0.2s, box-shadow 0.2s;
    }

    .ocean-btn:hover {
        background: var(--primary-soft);
        transform: translateY(-1px);
    }

    .ocean-btn svg {
        width: 18px;
        height: 18px;
        fill: none;
        stroke: currentColor;
        stroke-width: 1.8;
        stroke-linecap: round;
        stroke-linejoin: round;
    }

    html.ocean-on .ocean-btn {
        background: linear-gradient(135deg, #ff9a8b, #ff6f91);
        border-color: transparent;
        color: #ffffff;
        box-shadow: 0 6px 16px rgba(255, 111, 145, 0.4);
    }

    html.ocean-on .ocean-btn:hover {
        background: linear-gradient(135deg, #ffa99c, #ff7f9d);
    }


    /* =====================================================
       LAPISAN LATAR & DEPAN
    ===================================================== */

    .ocean-bg,
    .ocean-fg {
        display: none;
        position: fixed;
        inset: 0;
        pointer-events: none;
    }

    html.ocean-on .ocean-bg,
    html.ocean-on .ocean-fg {
        display: block;
    }

    .ocean-bg {
        z-index: -1;
        overflow: hidden;
    }

    .ocean-fg {
        z-index: 60;
        overflow: hidden;
    }

    /* warna air */

    html.ocean-on body {
        background: linear-gradient(180deg, #62d2f1 0%, #2aa0d9 34%, #0f6cb5 70%, #0a4a86 100%) fixed;
    }

    html.ocean-on[data-theme="dark"] body {
        background: linear-gradient(180deg, #11406c 0%, #0b2c52 46%, #061a35 100%) fixed;
    }

    /* berkas cahaya */

    .oc-rays {
        position: absolute;
        inset: 0;
        opacity: 0.55;
        background: repeating-linear-gradient(
            105deg,
            rgba(255, 255, 255, 0.12) 0 70px,
            transparent 70px 190px
        );
        -webkit-mask-image: linear-gradient(180deg, #000 0%, transparent 70%);
        mask-image: linear-gradient(180deg, #000 0%, transparent 70%);
        animation: oc-rays 18s linear infinite;
    }

    html.ocean-on[data-theme="dark"] .oc-rays {
        opacity: 0.25;
    }

    @keyframes oc-rays {
        from { background-position: 0 0; }
        to   { background-position: 380px 0; }
    }

    /* gelembung latar */

    .oc-bub {
        position: absolute;
        bottom: -24px;
        left: var(--x);
        width: var(--s);
        height: var(--s);
        border-radius: 50%;
        border: 1.5px solid rgba(255, 255, 255, 0.55);
        background: radial-gradient(circle at 30% 30%, rgba(255, 255, 255, 0.55), rgba(255, 255, 255, 0.06));
        opacity: 0;
        animation: oc-rise var(--d) linear infinite;
        animation-delay: var(--delay);
    }

    @keyframes oc-rise {
        0%   { transform: translate(0, 0);               opacity: 0; }
        10%  { opacity: 0.9; }
        50%  { transform: translate(16px, -52vh); }
        100% { transform: translate(-8px, -108vh);       opacity: 0.35; }
    }


    /* =====================================================
       PASIR & TERUMBU KARANG
    ===================================================== */

    .oc-sand {
        position: absolute;
        left: 0;
        right: 0;
        bottom: 0;
        height: 34px;
        border-radius: 60% 60% 0 0 / 16px 16px 0 0;
        background: linear-gradient(180deg, #f6dea8, #e6c27c);
    }

    html.ocean-on[data-theme="dark"] .oc-sand {
        background: linear-gradient(180deg, #6d5e55, #4e4239);
    }

    .oc-coral {
        position: absolute;
        bottom: 20px;
        left: var(--l);
        width: var(--w);
    }

    .oc-coral svg {
        display: block;
        width: 100%;
        height: auto;
        transform-origin: 50% 100%;
        animation: oc-sway 5s ease-in-out infinite alternate;
        animation-delay: var(--sd, 0s);
    }

    .oc-coral.weed svg {
        animation-duration: 3.6s;
    }

    .oc-coral.star {
        bottom: 8px;
    }

    .oc-coral.star svg {
        animation: none;
    }

    .oc-coral.poke svg {
        animation: oc-poke 0.7s ease;
    }

    .oc-coral.star.poke svg {
        animation: oc-star-spin 0.8s ease;
    }

    @keyframes oc-sway {
        from { transform: rotate(-2.4deg); }
        to   { transform: rotate(2.4deg); }
    }

    @keyframes oc-poke {
        0%   { transform: rotate(0deg) scale(1); }
        20%  { transform: rotate(-7deg) scale(1.06, 0.94); }
        45%  { transform: rotate(6deg) scale(0.96, 1.05); }
        70%  { transform: rotate(-3deg) scale(1.02); }
        100% { transform: rotate(0deg) scale(1); }
    }

    @keyframes oc-star-spin {
        from { transform: rotate(0deg) scale(1); }
        50%  { transform: rotate(180deg) scale(1.2); }
        to   { transform: rotate(360deg) scale(1); }
    }


    /* =====================================================
       KUDA LAUT DI ANTARA RUMPUT LAUT
    ===================================================== */

    .oc-seahorse {
        position: absolute;
        bottom: var(--b, 48px);
        left: var(--l, 20%);
        width: var(--w, 34px);
    }

    .oc-seahorse svg {
        display: block;
        width: 100%;
        height: auto;
        overflow: visible;
        transform-origin: 50% 90%;
        animation: oc-seahorse-sway 3.8s ease-in-out infinite alternate;
        animation-delay: var(--sd, 0s);
    }

    .oc-seahorse.flip {
        transform: scaleX(-1);
    }

    .oc-seahorse .sh-fin {
        transform-box: fill-box;
        transform-origin: 100% 50%;
        animation: oc-sh-fin 0.35s ease-in-out infinite alternate;
    }

    .oc-seahorse.poke svg {
        animation: oc-poke 0.7s ease;
    }

    @keyframes oc-seahorse-sway {
        0%   { transform: translateY(0) rotate(-6deg); }
        50%  { transform: translateY(-7px) rotate(2deg); }
        100% { transform: translateY(-2px) rotate(7deg); }
    }

    @keyframes oc-sh-fin {
        from { transform: rotate(-16deg); }
        to   { transform: rotate(16deg); }
    }


    /* =====================================================
       GURITA PEMALU
    ===================================================== */

    .oc-octopus {
        position: absolute;
        bottom: 14px;
        left: 79%;
        width: 78px;
        transition: transform 0.35s ease;
    }

    .oc-octopus svg {
        display: block;
        width: 100%;
        height: auto;
        overflow: visible;
        animation: oc-octo-bob 4s ease-in-out infinite;
    }

    .oc-octopus .octo-body {
        fill: #c77dff;
        transition: fill 0.45s ease;
    }

    .oc-octopus .octo-leg {
        stroke: #b565f5;
        transition: stroke 0.45s ease, transform 0.35s ease;
    }

    .oc-octopus .octo-shy-arm {
        opacity: 0;
        transform: translateY(10px);
        transition: opacity 0.3s ease, transform 0.35s cubic-bezier(0.3, 1.5, 0.5, 1);
    }

    .oc-octopus .octo-eyes-open {
        transition: opacity 0.25s ease;
    }

    .oc-octopus .octo-blush {
        opacity: 0.45;
        transition: opacity 0.3s ease, transform 0.3s ease;
        transform-box: fill-box;
        transform-origin: center;
    }

    .oc-octopus.shy {
        transform: translateY(5px) scale(0.95);
    }

    .oc-octopus.shy .octo-body {
        fill: #ff5c8a;
    }

    .oc-octopus.shy .octo-leg {
        stroke: #e04472;
    }

    .oc-octopus.shy .octo-shy-arm {
        opacity: 1;
        transform: translateY(0);
    }

    .oc-octopus.shy .octo-eyes-open {
        opacity: 0;
    }

    .oc-octopus.shy .octo-blush {
        opacity: 0.95;
        transform: scale(1.35);
    }

    @keyframes oc-octo-bob {
        0%, 100% { transform: translateY(0); }
        50%      { transform: translateY(-4px); }
    }


    /* =====================================================
       KEPITING LUCU DI PASIR
    ===================================================== */

    .oc-crab {
        position: absolute;
        left: 0;
        bottom: 6px;
        width: 58px;
        will-change: transform;
    }

    .oc-crab-in {
        transition: transform 0.35s cubic-bezier(0.3, 1.4, 0.5, 1), opacity 0.3s ease;
    }

    .oc-crab svg {
        display: block;
        width: 100%;
        height: auto;
        overflow: visible;
    }

    .oc-crab .claw-wave {
        transform-box: fill-box;
        transform-origin: 50% 90%;
        animation: oc-crab-wave 0.65s ease-in-out infinite alternate;
    }

    .oc-crab .crab-legs {
        animation: oc-crab-step 0.28s steps(2, end) infinite;
    }

    .oc-crab.hide .oc-crab-in {
        transform: translateY(20px) scale(0.82);
    }

    .oc-crab.hide .crab-upper {
        opacity: 0.15;
        transition: opacity 0.25s ease;
    }

    @keyframes oc-crab-wave {
        from { transform: rotate(-18deg); }
        to   { transform: rotate(22deg); }
    }

    @keyframes oc-crab-step {
        from { transform: translateY(0); }
        to   { transform: translateY(-2px); }
    }


    /* =====================================================
       UBUR-UBUR BERCAHAYA
    ===================================================== */

    .oc-jelly {
        position: absolute;
        left: 0;
        top: 0;
        width: var(--w, 54px);
        will-change: transform;
        filter: drop-shadow(0 0 8px rgba(170, 240, 255, 0.45));
        transition: filter 0.35s ease;
    }

    html.ocean-on[data-theme="dark"] .oc-jelly {
        filter: drop-shadow(0 0 18px var(--glow, #72f5ff)) drop-shadow(0 0 34px var(--glow, #72f5ff));
    }

    .oc-jelly.blink {
        animation: oc-jelly-flash 0.75s ease;
    }

    .oc-jelly svg {
        display: block;
        width: 100%;
        height: auto;
        overflow: visible;
    }

    .oc-jelly .jelly-bell {
        transform-box: fill-box;
        transform-origin: 50% 80%;
        animation: oc-jelly-pulse 2.6s ease-in-out infinite;
    }

    .oc-jelly .jelly-tentacles {
        transform-box: fill-box;
        transform-origin: 50% 0%;
        animation: oc-jelly-tent 2.6s ease-in-out infinite;
    }

    .oc-jelly .eye-open {
        opacity: 1;
    }

    .oc-jelly .eye-blink {
        opacity: 0;
    }

    .oc-jelly.blink .eye-open {
        opacity: 0;
    }

    .oc-jelly.blink .eye-blink {
        opacity: 1;
    }

    @keyframes oc-jelly-pulse {
        0%, 100% { transform: scale(1, 1); }
        45%      { transform: scale(1.08, 0.91); }
    }

    @keyframes oc-jelly-tent {
        0%, 100% { transform: rotate(-4deg) scaleY(1); }
        50%      { transform: rotate(4deg) scaleY(1.08); }
    }

    @keyframes oc-jelly-flash {
        0%, 100% { filter: drop-shadow(0 0 10px #fff); }
        40%      { filter: drop-shadow(0 0 28px #fff) drop-shadow(0 0 48px #ff9ec4) brightness(1.45); }
    }


    /* =====================================================
       IKAN & KAWANAN IKAN KECIL (MINNOWS)
    ===================================================== */

    .oc-fish {
        position: absolute;
        left: 0;
        top: 0;
        width: var(--w);
        will-change: transform;
    }

    .oc-fish svg {
        display: block;
        width: 100%;
        height: auto;
        overflow: visible;
    }

    .oc-fish .tail {
        transform-box: fill-box;
        transform-origin: 0% 50%;
        animation: oc-tail 0.5s ease-in-out infinite alternate;
    }

    @keyframes oc-tail {
        from { transform: rotate(-16deg); }
        to   { transform: rotate(16deg); }
    }

    .oc-fish .puff-body {
        transform-box: fill-box;
        transform-origin: center;
        transition: transform 0.35s cubic-bezier(0.3, 1.6, 0.5, 1);
    }

    .oc-fish .spikes {
        opacity: 0;
        transition: opacity 0.25s;
    }

    .oc-fish.puffed .puff-body {
        transform: scale(1.5);
    }

    .oc-fish.puffed .spikes {
        opacity: 1;
    }

    .oc-fish.boop .oc-fish-in {
        animation: oc-spin 0.7s ease;
    }

    .oc-fish.chomp .oc-fish-in {
        animation: oc-chomp 0.3s ease;
    }

    @keyframes oc-spin {
        from { transform: rotate(0deg); }
        to   { transform: rotate(360deg); }
    }

    @keyframes oc-chomp {
        50% { transform: scale(1.15); }
    }

    /* kawanan ikan kecil pengikut paus */
    .oc-minnow {
        position: absolute;
        left: 0;
        top: 0;
        width: 24px;
        will-change: transform;
    }

    .oc-minnow svg {
        display: block;
        width: 100%;
        height: auto;
    }

    /* makanan ikan */

    .oc-food {
        position: absolute;
        left: 0;
        top: 0;
        width: 7px;
        height: 7px;
        border-radius: 50%;
        background: #ffb26b;
        box-shadow: 0 0 0 1px rgba(255, 255, 255, 0.5);
        will-change: transform;
    }

    /* efek klik & not musik */

    .oc-pop {
        position: absolute;
        width: var(--s);
        height: var(--s);
        border-radius: 50%;
        border: 1.5px solid rgba(255, 255, 255, 0.85);
        background: rgba(255, 255, 255, 0.25);
        animation: oc-pop 1.4s ease-out forwards;
    }

    @keyframes oc-pop {
        from { transform: translate(0, 0) scale(0.6);              opacity: 1; }
        to   { transform: translate(var(--dx), -72px) scale(1.1);  opacity: 0; }
    }

    .oc-heart {
        position: absolute;
        width: 16px;
        height: 16px;
        animation: oc-heart 1.3s ease-out forwards;
    }

    .oc-heart svg {
        display: block;
        width: 100%;
        height: 100%;
    }

    @keyframes oc-heart {
        from { transform: translate(0, 0) scale(0.6);   opacity: 1; }
        to   { transform: translate(var(--dx, 0px), -48px) scale(1.3); opacity: 0; }
    }

    .oc-note {
        position: absolute;
        font-size: 18px;
        font-weight: 700;
        color: #fff6a9;
        text-shadow: 0 2px 8px rgba(0, 40, 95, 0.45);
        animation: oc-note-float 2.4s ease-out forwards;
    }

    @keyframes oc-note-float {
        0%   { transform: translate(0, 0) scale(0.5) rotate(-12deg); opacity: 0; }
        20%  { opacity: 1; }
        100% { transform: translate(var(--dx, 24px), -75px) scale(1.25) rotate(14deg); opacity: 0; }
    }

    .oc-say {
        position: absolute;
        padding: 4px 11px;
        border-radius: 12px;
        background: #ffffff;
        color: #12466e;
        font-size: 12px;
        font-weight: 700;
        white-space: nowrap;
        box-shadow: 0 6px 16px rgba(0, 40, 90, 0.25);
        animation: oc-say 1.7s ease-out forwards;
    }

    .oc-say::after {
        content: "";
        position: absolute;
        left: 50%;
        bottom: -5px;
        width: 10px;
        height: 10px;
        background: #ffffff;
        transform: translateX(-50%) rotate(45deg);
    }

    @keyframes oc-say {
        0%   { transform: translate(-50%, -100%) scale(0.6);   opacity: 0; }
        15%  { transform: translate(-50%, -120%) scale(1);     opacity: 1; }
        80%  { transform: translate(-50%, -130%) scale(1);     opacity: 1; }
        100% { transform: translate(-50%, -150%) scale(1);     opacity: 0; }
    }


    /* =====================================================
       PAUS LUCU & ANAK PAUS YANG LEWAT
    ===================================================== */

    .oc-whale {
        position: absolute;
        left: 0;
        top: 20%;
        width: 300px;
        opacity: 0;
        will-change: transform;
    }

    .oc-whale svg {
        display: block;
        width: 100%;
        height: auto;
        overflow: visible;
    }

    .oc-whale .bob {
        animation: oc-bob 3.6s ease-in-out infinite;
    }

    .oc-whale.hop .bob {
        animation: oc-hop 0.9s ease;
    }

    .oc-whale .flip {
        transform-origin: 50% 50%;
    }

    .oc-whale.go-ltr {
        opacity: 0.96;
        animation: oc-whale-ltr 26s linear forwards;
    }

    .oc-whale.go-rtl {
        opacity: 0.96;
        animation: oc-whale-rtl 26s linear forwards;
    }

    .oc-whale.go-rtl .flip {
        transform: scaleX(-1);
    }

    /* anak paus kecil mengikuti di belakang/bawah */
    .oc-whale-calf {
        position: absolute;
        left: -105px;
        top: 68px;
        width: 135px;
        animation: oc-calf-bob 2.6s ease-in-out infinite alternate;
    }

    @keyframes oc-calf-bob {
        from { transform: translateY(-5px) rotate(-2deg); }
        to   { transform: translateY(7px) rotate(2deg); }
    }

    @keyframes oc-whale-ltr {
        from { transform: translateX(-420px); }
        to   { transform: translateX(calc(100vw + 160px)); }
    }

    @keyframes oc-whale-rtl {
        from { transform: translateX(calc(100vw + 160px)); }
        to   { transform: translateX(-420px); }
    }

    @keyframes oc-bob {
        0%, 100% { transform: translateY(0) rotate(-1.5deg); }
        50%      { transform: translateY(-16px) rotate(2deg); }
    }

    @keyframes oc-hop {
        0%   { transform: translateY(0) rotate(0deg) scale(1); }
        35%  { transform: translateY(-34px) rotate(-6deg) scale(1.06, 0.95); }
        65%  { transform: translateY(4px) rotate(3deg) scale(0.97, 1.04); }
        100% { transform: translateY(0) rotate(0deg) scale(1); }
    }

    .whale-tail {
        transform-box: fill-box;
        transform-origin: 90% 80%;
        animation: oc-fluke 1.5s ease-in-out infinite alternate;
    }

    @keyframes oc-fluke {
        from { transform: rotate(-9deg); }
        to   { transform: rotate(9deg); }
    }

    .whale-flipper {
        transform-box: fill-box;
        transform-origin: 20% 5%;
        animation: oc-flipper 1.1s ease-in-out infinite alternate;
    }

    @keyframes oc-flipper {
        from { transform: rotate(-14deg); }
        to   { transform: rotate(12deg); }
    }

    .whale-spout {
        transform-box: fill-box;
        transform-origin: 50% 100%;
        animation: oc-spout 5s ease-out infinite;
    }

    @keyframes oc-spout {
        0%, 55% { transform: scale(0);   opacity: 0; }
        70%     { transform: scale(1);   opacity: 1; }
        100%    { transform: scale(1.25); opacity: 0; }
    }


    /* =====================================================
       PENYU YANG SUKA MENYAPA
    ===================================================== */

    .oc-turtle {
        position: absolute;
        left: 0;
        top: 38%;
        width: 115px;
        opacity: 0;
        will-change: transform;
    }

    .oc-turtle svg {
        display: block;
        width: 100%;
        height: auto;
        overflow: visible;
    }

    .oc-turtle .turtle-bob {
        animation: oc-bob 3.2s ease-in-out infinite;
    }

    .oc-turtle .turtle-flip {
        transform-origin: 50% 50%;
    }

    .oc-turtle.go-ltr {
        opacity: 0.96;
        animation: oc-turtle-ltr 22s linear forwards;
    }

    .oc-turtle.go-rtl {
        opacity: 0.96;
        animation: oc-turtle-rtl 22s linear forwards;
    }

    .oc-turtle.go-rtl .turtle-flip {
        transform: scaleX(-1);
    }

    .oc-turtle .turtle-fin {
        transform-box: fill-box;
        transform-origin: 30% 20%;
        animation: oc-turtle-swim 1.3s ease-in-out infinite alternate;
    }

    .oc-turtle .turtle-head {
        transform-box: fill-box;
        transform-origin: 15% 50%;
    }

    .oc-turtle.nod .turtle-head {
        animation: oc-turtle-nod 0.55s ease-in-out 2;
    }

    @keyframes oc-turtle-ltr {
        from { transform: translateX(-160px); }
        to   { transform: translateX(calc(100vw + 160px)); }
    }

    @keyframes oc-turtle-rtl {
        from { transform: translateX(calc(100vw + 160px)); }
        to   { transform: translateX(-160px); }
    }

    @keyframes oc-turtle-swim {
        from { transform: rotate(-18deg); }
        to   { transform: rotate(20deg); }
    }

    @keyframes oc-turtle-nod {
        0%, 100% { transform: rotate(0deg); }
        50%      { transform: rotate(22deg) translateY(3px); }
    }


    /* =====================================================
       KARTU KACA & HEADER (hanya saat tema aktif)
    ===================================================== */

    html.ocean-on .header {
        padding: 16px 22px;
        border: 1px solid rgba(255, 255, 255, 0.38);
        border-radius: 16px;
        background: rgba(255, 255, 255, 0.16);
        -webkit-backdrop-filter: blur(6px);
        backdrop-filter: blur(6px);
    }


    html.ocean-on .header-left h1 {
        color: #ffffff;
        text-shadow: 0 2px 8px rgba(0, 50, 100, 0.35);
    }

    html.ocean-on .header-left p,
    html.ocean-on .breadcrumb,
    html.ocean-on .breadcrumb a,
    html.ocean-on .breadcrumb span {
        color: rgba(255, 255, 255, 0.9);
    }

    html.ocean-on .breadcrumb a:hover {
        color: #ffffff;
    }

    html.ocean-on .box,
    html.ocean-on .filter-box,
    html.ocean-on .table-box,
    html.ocean-on .card {
        border-color: rgba(255, 255, 255, 0.55);
        background: rgba(255, 255, 255, 0.86);
        -webkit-backdrop-filter: blur(5px);
        backdrop-filter: blur(5px);
    }

    html.ocean-on[data-theme="dark"] .box,
    html.ocean-on[data-theme="dark"] .filter-box,
    html.ocean-on[data-theme="dark"] .table-box,
    html.ocean-on[data-theme="dark"] .card {
        border-color: rgba(120, 170, 230, 0.25);
        background: rgba(14, 28, 52, 0.84);
    }

    html.ocean-on[data-theme="dark"] .header {
        border-color: rgba(120, 170, 230, 0.3);
        background: rgba(14, 28, 52, 0.45);
    }


    /* =====================================================
       SIDEBAR KHUSUS TEMA LAUT (sidebar.php tidak diubah)
    ===================================================== */

    html.ocean-on .sidebar {
        border-right: 1px solid rgba(255, 255, 255, 0.18);
        background: linear-gradient(180deg, #0c5a9a 0%, #0a4378 55%, #07305b 100%);
        color: #ffffff;
    }

    html.ocean-on .sidebar::before {
        content: "";
        position: absolute;
        inset: 0;
        pointer-events: none;
        opacity: 0.6;
        background-image:
            radial-gradient(circle at 18% 82%, rgba(255, 255, 255, 0.45) 0 3px, transparent 4px),
            radial-gradient(circle at 72% 42%, rgba(255, 255, 255, 0.35) 0 2.5px, transparent 3.5px),
            radial-gradient(circle at 42% 14%, rgba(255, 255, 255, 0.4) 0 4px, transparent 5px);
        background-size: 240px 420px;
        animation: oc-side 14s linear infinite;
    }

    @keyframes oc-side {
        from { background-position: 0 0; }
        to   { background-position: 0 -420px; }
    }

    html.ocean-on .sidebar > * {
        position: relative;
        z-index: 1;
    }

    html.ocean-on .sidebar .logo-text {
        color: #ffffff;
    }

    html.ocean-on .sidebar .menu-title {
        color: rgba(255, 255, 255, 0.62);
    }

    html.ocean-on .sidebar .menu a {
        color: rgba(255, 255, 255, 0.88);
    }

    html.ocean-on .sidebar .menu a:hover {
        background: rgba(255, 255, 255, 0.14);
        color: #ffffff;
    }

    html.ocean-on .sidebar .menu a.active {
        background: linear-gradient(135deg, #ff9a8b, #ff6f91);
        color: #ffffff;
        box-shadow: 0 6px 14px rgba(255, 111, 145, 0.35);
    }

    html.ocean-on .sidebar .menu a.active svg {
        stroke: #ffffff;
    }

    html.ocean-on .sidebar .theme-toggle {
        border-color: rgba(255, 255, 255, 0.35);
        color: #ffffff;
    }

    html.ocean-on .sidebar .theme-toggle:hover {
        background: rgba(255, 255, 255, 0.16);
    }

    html.ocean-on .sidebar .sidebar-footer {
        border-top-color: rgba(255, 255, 255, 0.2);
    }

    html.ocean-on .sidebar .sidebar-user {
        color: #ffffff;
    }

    html.ocean-on .sidebar .sidebar-user small {
        color: rgba(255, 255, 255, 0.65);
    }

    html.ocean-on .sidebar .sidebar-logout {
        color: rgba(255, 255, 255, 0.75);
    }


    /* =====================================================
       LAYAR KECIL & PENGURANGAN GERAK
    ===================================================== */

    @media (max-width: 800px) {

        .oc-coral:nth-child(even) {
            display: none;
        }

        .oc-whale {
            width: 200px;
        }
    }

    @media (prefers-reduced-motion: reduce) {

        .ocean-bg *,
        .ocean-fg *,
        html.ocean-on .sidebar::before {
            animation: none !important;
        }

        .oc-bub {
            display: none;
        }
    }

</style>


<!-- =========================================================
     LAPISAN LATAR (di belakang konten)
========================================================== -->

<div class="ocean-bg" aria-hidden="true">

    <!-- kumpulan gambar terumbu karang & kuda laut -->
    <svg width="0" height="0" style="position:absolute">
        <defs>

            <!-- rumput laut -->
            <symbol id="oc-weed" viewBox="0 0 40 140">
                <path d="M12 140 C0 124 22 112 12 94" fill="none" stroke="var(--c2, #2fa866)" stroke-width="6" stroke-linecap="round"/>
                <path d="M29 140 C40 120 18 110 30 90" fill="none" stroke="var(--c2, #2fa866)" stroke-width="6" stroke-linecap="round"/>
                <path d="M20 140 C4 118 36 102 20 78 C6 56 34 40 20 10" fill="none" stroke="var(--c1, #3fc27a)" stroke-width="9" stroke-linecap="round"/>
            </symbol>

            <!-- kuda laut -->
            <symbol id="oc-seahorse-sym" viewBox="0 0 50 90">
                <!-- sirip punggung -->
                <path class="sh-fin" d="M18 38 L7 33 L10 43 L6 48 L18 49 Z" fill="var(--c2, #ff7597)"/>
                <!-- tubuh & ekor melingkar -->
                <path d="M26 12 C17 12 15 20 20 27 C14 34 13 48 22 57 C28 63 27 76 19 79 C14 80 13 74 18 72 C21 71 21 75 18 75"
                      fill="none" stroke="var(--c1, #ffa85c)" stroke-width="9" stroke-linecap="round" stroke-linejoin="round"/>
                <!-- kepala & moncong -->
                <circle cx="27" cy="18" r="7.5" fill="var(--c1, #ffa85c)"/>
                <path d="M32 16 L43 18 L43 21 L31 22 Z" fill="var(--c1, #ffa85c)"/>
                <!-- mahkota kecil -->
                <path d="M22 12 L24 6 L28 11 L31 7 L31 13" fill="none" stroke="var(--c2, #ff7597)" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
                <!-- garis perut -->
                <path d="M24 31 Q29 41 24 51" fill="none" stroke="#fff3d1" stroke-width="3" stroke-linecap="round"/>
                <!-- mata & pipi -->
                <circle cx="29" cy="17" r="2" fill="#2b1b2e"/>
                <circle cx="29.6" cy="16.4" r="0.7" fill="#ffffff"/>
                <circle cx="26" cy="21" r="2.2" fill="#ff5c85" opacity="0.55"/>
            </symbol>

            <!-- karang bercabang -->
            <symbol id="oc-branch" viewBox="0 0 100 120">
                <g fill="none" stroke="var(--c1, #ff7aa2)" stroke-width="9" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M50 120 V72"/>
                    <path d="M50 94 L26 64 L18 38"/>
                    <path d="M26 64 L36 36"/>
                    <path d="M50 82 L76 52 L70 26"/>
                    <path d="M76 52 L88 30"/>
                    <path d="M50 72 V34"/>
                </g>
                <g fill="var(--c2, #ffc2d4)">
                    <circle cx="18" cy="36" r="5.5"/>
                    <circle cx="36" cy="34" r="5.5"/>
                    <circle cx="70" cy="24" r="5.5"/>
                    <circle cx="88" cy="28" r="5.5"/>
                    <circle cx="50" cy="32" r="5.5"/>
                </g>
            </symbol>

            <!-- karang otak tersenyum -->
            <symbol id="oc-dome" viewBox="0 0 110 70">
                <path d="M4 70 C4 22 106 22 106 70 Z" fill="var(--c1, #ffb35c)"/>
                <g fill="none" stroke="var(--c2, #f08a2c)" stroke-width="3" stroke-linecap="round" opacity="0.5">
                    <path d="M14 58 C18 44 28 44 30 54"/>
                    <path d="M80 54 C84 44 94 44 96 56"/>
                    <path d="M30 34 C36 28 44 30 44 38"/>
                    <path d="M64 33 C70 28 78 31 78 38"/>
                </g>
                <ellipse cx="45" cy="50" rx="2.6" ry="3.2" fill="#3a2330"/>
                <ellipse cx="65" cy="50" rx="2.6" ry="3.2" fill="#3a2330"/>
                <circle cx="45.9" cy="48.9" r="0.9" fill="#ffffff"/>
                <circle cx="65.9" cy="48.9" r="0.9" fill="#ffffff"/>
                <path d="M50 58 Q55 63 60 58" fill="none" stroke="#3a2330" stroke-width="1.7" stroke-linecap="round"/>
                <ellipse cx="37" cy="57" rx="4.5" ry="2.7" fill="#ff6f9f" opacity="0.55"/>
                <ellipse cx="73" cy="57" rx="4.5" ry="2.7" fill="#ff6f9f" opacity="0.55"/>
            </symbol>

            <!-- karang tabung -->
            <symbol id="oc-tube" viewBox="0 0 90 110">
                <g fill="var(--c1, #b58cff)">
                    <rect x="8" y="40" width="20" height="70" rx="10"/>
                    <rect x="32" y="14" width="22" height="96" rx="11"/>
                    <rect x="58" y="34" width="20" height="76" rx="10"/>
                </g>
                <g fill="var(--c2, #7a4fd6)">
                    <ellipse cx="18" cy="42" rx="7" ry="4"/>
                    <ellipse cx="43" cy="16" rx="8" ry="4.5"/>
                    <ellipse cx="68" cy="36" rx="7" ry="4"/>
                </g>
                <g fill="#ffffff" opacity="0.28">
                    <rect x="12" y="54" width="4" height="40" rx="2"/>
                    <rect x="37" y="30" width="4" height="64" rx="2"/>
                    <rect x="62" y="48" width="4" height="44" rx="2"/>
                </g>
            </symbol>

            <!-- karang kipas -->
            <symbol id="oc-fan" viewBox="0 0 110 120">
                <rect x="52" y="96" width="6" height="24" rx="3" fill="var(--c2, #c0456a)"/>
                <path d="M55 104 L6 46 A66 66 0 0 1 104 46 Z" fill="var(--c1, #ff6f91)"/>
                <g stroke="var(--c2, #c0456a)" stroke-width="2" stroke-linecap="round" opacity="0.5">
                    <path d="M55 104 L22 40"/>
                    <path d="M55 104 L38 30"/>
                    <path d="M55 104 L55 26"/>
                    <path d="M55 104 L72 30"/>
                    <path d="M55 104 L88 40"/>
                </g>
            </symbol>

            <!-- anemon laut -->
            <symbol id="oc-anemone" viewBox="0 0 120 80">
                <ellipse cx="60" cy="74" rx="34" ry="8" fill="var(--c2, #d9609a)"/>
                <g fill="none" stroke="var(--c1, #ff9fb8)" stroke-width="5" stroke-linecap="round">
                    <path d="M36 72 C26 50 34 34 22 20"/>
                    <path d="M44 72 C40 48 48 32 40 14"/>
                    <path d="M52 72 C50 50 56 34 52 10"/>
                    <path d="M60 72 C60 48 66 30 64 8"/>
                    <path d="M68 72 C72 50 70 32 78 12"/>
                    <path d="M76 72 C84 50 78 36 90 20"/>
                    <path d="M84 72 C96 56 94 44 102 34"/>
                </g>
                <g fill="#fff0f5">
                    <circle cx="22" cy="20" r="3.2"/>
                    <circle cx="40" cy="14" r="3.2"/>
                    <circle cx="52" cy="10" r="3.2"/>
                    <circle cx="64" cy="8" r="3.2"/>
                    <circle cx="78" cy="12" r="3.2"/>
                    <circle cx="90" cy="20" r="3.2"/>
                    <circle cx="102" cy="34" r="3.2"/>
                </g>
            </symbol>

            <!-- bintang laut -->
            <symbol id="oc-star" viewBox="0 0 50 50">
                <path d="M25 4 L30.58 18.31 L45.92 19.2 L34.04 28.94 L37.93 43.8 L25 35.5 L12.07 43.8 L15.96 28.94 L4.08 19.2 L19.42 18.31 Z"
                      fill="#ff8a65" stroke="#ff8a65" stroke-width="4" stroke-linejoin="round"/>
                <circle cx="21" cy="25" r="1.6" fill="#3a2330"/>
                <circle cx="29" cy="25" r="1.6" fill="#3a2330"/>
                <path d="M22 30 Q25 33.5 28 30" fill="none" stroke="#3a2330" stroke-width="1.4" stroke-linecap="round"/>
                <circle cx="17.5" cy="29" r="2" fill="#ff5c85" opacity="0.6"/>
                <circle cx="32.5" cy="29" r="2" fill="#ff5c85" opacity="0.6"/>
            </symbol>

        </defs>
    </svg>

    <div class="oc-rays"></div>

    <!-- gelembung -->
    <?php for ($i = 0; $i < 16; $i++):

        $bub_x     = 4 + (($i * 53) % 92);
        $bub_size  = 6 + (($i * 7) % 14);
        $bub_dur   = 9 + (($i * 3) % 8);
        $bub_delay = -1 * fmod($i * 1.9, $bub_dur);

    ?>
        <i
            class="oc-bub"
            style="--x: <?= $bub_x; ?>%; --s: <?= $bub_size; ?>px; --d: <?= $bub_dur; ?>s; --delay: <?= round($bub_delay, 1); ?>s;"
        ></i>
    <?php endfor; ?>

    <div class="oc-sand"></div>

    <!-- terumbu karang (klik untuk menggoyangkan) -->
    <div class="oc-coral weed" style="--l: 1.5%; --w: 46px; --sd: -0.5s;"><svg viewBox="0 0 40 140"><use href="#oc-weed"/></svg></div>
    <div class="oc-coral" style="--l: 5%;   --w: 104px; --sd: -1.2s; --c1: #ff6f91; --c2: #c0456a;"><svg viewBox="0 0 110 120"><use href="#oc-fan"/></svg></div>
    <div class="oc-coral" style="--l: 13%;  --w: 118px; --sd: -2.1s; --c1: #ffb35c; --c2: #f08a2c;"><svg viewBox="0 0 110 70"><use href="#oc-dome"/></svg></div>
    <div class="oc-coral weed" style="--l: 22%; --w: 40px; --sd: -2.0s; --c1: #4fd08a; --c2: #36b673;"><svg viewBox="0 0 40 140"><use href="#oc-weed"/></svg></div>
    <div class="oc-coral" style="--l: 27%;  --w: 100px; --sd: -0.8s; --c1: #ff7aa2; --c2: #ffc2d4;"><svg viewBox="0 0 100 120"><use href="#oc-branch"/></svg></div>
    <div class="oc-coral" style="--l: 37%;  --w: 84px;  --sd: -3.0s; --c1: #b58cff; --c2: #7a4fd6;"><svg viewBox="0 0 90 110"><use href="#oc-tube"/></svg></div>
    <div class="oc-coral star" style="--l: 44%; --w: 42px; --sd: 0s;"><svg viewBox="0 0 50 50"><use href="#oc-star"/></svg></div>
    <div class="oc-coral" style="--l: 50%;  --w: 126px; --sd: -1.6s; --c1: #ff9fb8; --c2: #d9609a;"><svg viewBox="0 0 120 80"><use href="#oc-anemone"/></svg></div>
    <div class="oc-coral" style="--l: 61%;  --w: 92px;  --sd: -2.6s; --c1: #ff9a5c; --c2: #ffd0a8;"><svg viewBox="0 0 100 120"><use href="#oc-branch"/></svg></div>
    <div class="oc-coral weed" style="--l: 69%; --w: 44px; --sd: -1.0s;"><svg viewBox="0 0 40 140"><use href="#oc-weed"/></svg></div>
    <div class="oc-coral" style="--l: 74%;  --w: 112px; --sd: -0.3s; --c1: #ff8fb8; --c2: #e85a93;"><svg viewBox="0 0 110 70"><use href="#oc-dome"/></svg></div>
    <div class="oc-coral" style="--l: 84%;  --w: 88px;  --sd: -1.9s; --c1: #5fd0e0; --c2: #2aa3b8;"><svg viewBox="0 0 90 110"><use href="#oc-tube"/></svg></div>
    <div class="oc-coral" style="--l: 91%;  --w: 108px; --sd: -2.8s; --c1: #b58cff; --c2: #7a4fd6;"><svg viewBox="0 0 110 120"><use href="#oc-fan"/></svg></div>
    <div class="oc-coral weed" style="--l: 96%; --w: 38px; --sd: -0.2s; --c1: #4fd08a; --c2: #36b673;"><svg viewBox="0 0 40 140"><use href="#oc-weed"/></svg></div>

    <!-- kuda laut yang bergoyang di antara rumput laut -->
    <div class="oc-seahorse" style="--l: 20.3%; --b: 46px; --w: 34px; --sd: -0.7s; --c1: #ffa85c; --c2: #ff6f91;">
        <svg viewBox="0 0 50 90"><use href="#oc-seahorse-sym"/></svg>
    </div>
    <div class="oc-seahorse flip" style="--l: 67.5%; --b: 52px; --w: 32px; --sd: -2.1s; --c1: #ff8fb8; --c2: #b58cff;">
        <svg viewBox="0 0 50 90"><use href="#oc-seahorse-sym"/></svg>
    </div>

    <!-- gurita pemalu yang berubah warna & menutup wajah saat didekati -->
    <div class="oc-octopus" id="ocOctopus">
        <svg viewBox="0 0 90 80">
            <!-- tentakel bawah -->
            <path class="octo-leg" d="M22 52 Q10 64 14 72 Q19 76 25 66" fill="none" stroke-width="6.5" stroke-linecap="round"/>
            <path class="octo-leg" d="M34 55 Q28 70 34 75 Q40 76 40 65" fill="none" stroke-width="6.5" stroke-linecap="round"/>
            <path class="octo-leg" d="M56 55 Q62 70 56 75 Q50 76 50 65" fill="none" stroke-width="6.5" stroke-linecap="round"/>
            <path class="octo-leg" d="M68 52 Q80 64 76 72 Q71 76 65 66" fill="none" stroke-width="6.5" stroke-linecap="round"/>
            <!-- kepala/badan -->
            <ellipse class="octo-body" cx="45" cy="36" rx="26" ry="23"/>
            <!-- kilau kepala -->
            <ellipse cx="35" cy="23" rx="6" ry="3.2" fill="#ffffff" opacity="0.32" transform="rotate(-20 35 23)"/>
            <!-- mata terbuka -->
            <g class="octo-eyes-open">
                <circle cx="35" cy="38" r="4.5" fill="#fff"/>
                <circle cx="55" cy="38" r="4.5" fill="#fff"/>
                <circle cx="35.8" cy="38.5" r="2.6" fill="#2b1b2e"/>
                <circle cx="55.8" cy="38.5" r="2.6" fill="#2b1b2e"/>
                <circle cx="36.6" cy="37.4" r="1" fill="#fff"/>
                <circle cx="56.6" cy="37.4" r="1" fill="#fff"/>
                <path d="M41 44 Q45 47.5 49 44" fill="none" stroke="#2b1b2e" stroke-width="1.8" stroke-linecap="round"/>
            </g>
            <!-- pipi merona -->
            <ellipse class="octo-blush" cx="27" cy="43" rx="4.5" ry="2.6" fill="#ff3b6b"/>
            <ellipse class="octo-blush" cx="63" cy="43" rx="4.5" ry="2.6" fill="#ff3b6b"/>
            <!-- tentakel penutup wajah saat malu -->
            <g class="octo-shy-arm">
                <path d="M18 52 C18 32 34 30 41 39" fill="none" stroke="#ff3b6b" stroke-width="7" stroke-linecap="round"/>
                <path d="M72 52 C72 32 56 30 49 39" fill="none" stroke="#ff3b6b" stroke-width="7" stroke-linecap="round"/>
                <circle cx="38" cy="38" r="2" fill="#ffc2d4"/>
                <circle cx="52" cy="38" r="2" fill="#ffc2d4"/>
            </g>
        </svg>
    </div>

</div>


<!-- =========================================================
     LAPISAN DEPAN (ikan, ubur-ubur, kepiting, penyu, paus)
========================================================== -->

<div class="ocean-fg" id="oceanFg" aria-hidden="true">

    <!-- kepiting lucu di pasir -->
    <div class="oc-crab" id="ocCrab">
        <div class="oc-crab-in">
            <svg viewBox="0 0 80 55">
                <g class="crab-upper">
                    <!-- kaki kiri & kanan -->
                    <g class="crab-legs" stroke="#d93829" stroke-width="3" stroke-linecap="round" fill="none">
                        <path d="M22 36 L10 44 L7 50"/>
                        <path d="M25 40 L14 48 L12 53"/>
                        <path d="M58 36 L70 44 L73 50"/>
                        <path d="M55 40 L66 48 L68 53"/>
                    </g>
                    <!-- capit kiri (melambai) -->
                    <g class="claw-wave">
                        <path d="M24 28 Q12 18 14 10" fill="none" stroke="#e64833" stroke-width="3.5" stroke-linecap="round"/>
                        <path d="M14 12 C7 8 8 2 14 5 C17 2 22 7 16 12 Z" fill="#ff5a43"/>
                    </g>
                    <!-- capit kanan -->
                    <g>
                        <path d="M56 28 Q68 20 66 12" fill="none" stroke="#e64833" stroke-width="3.5" stroke-linecap="round"/>
                        <path d="M66 14 C72 10 71 4 65 7 C62 4 58 9 64 14 Z" fill="#ff5a43"/>
                    </g>
                    <!-- cangkang badan -->
                    <ellipse cx="40" cy="34" rx="20" ry="13" fill="#ff5a43"/>
                    <path d="M25 30 Q40 23 55 30" fill="none" stroke="#ff8b78" stroke-width="2.5" stroke-linecap="round" opacity="0.7"/>
                    <!-- senyum & pipi -->
                    <path d="M36 36 Q40 40 44 36" fill="none" stroke="#2b1b2e" stroke-width="1.8" stroke-linecap="round"/>
                    <ellipse cx="29" cy="35" rx="3.2" ry="1.8" fill="#ff9ec4" opacity="0.75"/>
                    <ellipse cx="51" cy="35" rx="3.2" ry="1.8" fill="#ff9ec4" opacity="0.75"/>
                </g>
                <!-- tangkai mata (tetap mengintip sedikit saat sembunyi) -->
                <line x1="33" y1="24" x2="31" y2="15" stroke="#e64833" stroke-width="3" stroke-linecap="round"/>
                <line x1="47" y1="24" x2="49" y2="15" stroke="#e64833" stroke-width="3" stroke-linecap="round"/>
                <circle cx="31" cy="14" r="4" fill="#fff"/>
                <circle cx="49" cy="14" r="4" fill="#fff"/>
                <circle cx="31.5" cy="14" r="2.2" fill="#2b1b2e"/>
                <circle cx="49.5" cy="14" r="2.2" fill="#2b1b2e"/>
                <circle cx="32.2" cy="13.2" r="0.8" fill="#fff"/>
                <circle cx="50.2" cy="13.2" r="0.8" fill="#fff"/>
            </svg>
        </div>
    </div>

    <!-- penyu yang sesekali lewat dan mengangguk menyapa -->
    <div class="oc-turtle" id="ocTurtle">
        <div class="turtle-bob">
            <div class="turtle-flip">
                <svg viewBox="0 0 130 80">
                    <!-- kaki belakang -->
                    <ellipse cx="24" cy="52" rx="11" ry="5" transform="rotate(20 24 52)" fill="#4ca96c"/>
                    <!-- sirip depan (berenang) -->
                    <g class="turtle-fin">
                        <path d="M74 44 C86 58 80 72 66 68 C62 60 66 50 74 44 Z" fill="#5ec280"/>
                    </g>
                    <!-- kepala yang bisa mengangguk -->
                    <g class="turtle-head">
                        <path d="M90 38 C104 34 116 38 116 46 C116 54 102 56 90 50 Z" fill="#5ec280"/>
                        <circle cx="107" cy="43" r="2.4" fill="#1e2d24"/>
                        <circle cx="107.8" cy="42.2" r="0.8" fill="#fff"/>
                        <ellipse cx="103" cy="48" rx="3" ry="1.7" fill="#ff8fb1" opacity="0.65"/>
                        <path d="M107 49 Q111 51 114 48" fill="none" stroke="#1e2d24" stroke-width="1.4" stroke-linecap="round"/>
                    </g>
                    <!-- tempurung -->
                    <path d="M22 46 C22 18 94 18 96 46 Z" fill="#8d5b3c"/>
                    <path d="M32 43 C36 25 80 25 86 43" fill="none" stroke="#b57c56" stroke-width="3" stroke-linecap="round"/>
                    <path d="M58 23 V44 M40 28 L46 44 M76 28 L70 44" stroke="#6b4229" stroke-width="2.2"/>
                    <path d="M18 46 Q58 51 99 46" fill="none" stroke="#e3c58e" stroke-width="4" stroke-linecap="round"/>
                </svg>
            </div>
        </div>
    </div>

    <!-- paus lucu + anak paus kecil yang mengikuti -->
    <div class="oc-whale" id="ocWhale">
        <div class="bob">
            <div class="flip">
                <svg viewBox="0 0 300 150">

                    <!-- semburan air -->
                    <g class="whale-spout">
                        <path d="M232 40 C230 26 220 18 210 14" fill="none" stroke="#d6f1ff" stroke-width="5" stroke-linecap="round"/>
                        <path d="M232 40 C234 24 244 16 254 12" fill="none" stroke="#d6f1ff" stroke-width="5" stroke-linecap="round"/>
                        <path d="M232 40 V12" fill="none" stroke="#d6f1ff" stroke-width="5" stroke-linecap="round"/>
                        <circle cx="208" cy="12" r="4" fill="#eaf8ff"/>
                        <circle cx="256" cy="10" r="4" fill="#eaf8ff"/>
                        <circle cx="232" cy="6" r="4" fill="#eaf8ff"/>
                    </g>

                    <!-- ekor -->
                    <g class="whale-tail">
                        <path d="M64 74 C48 74 28 62 20 42 C34 44 48 50 58 60 C56 46 58 32 68 20 C74 38 76 54 74 66 Z" fill="#3f8fdc"/>
                    </g>

                    <!-- badan -->
                    <path d="M250 40 C285 40 300 75 292 100 C284 128 250 138 215 138 C170 138 120 132 88 112 C70 102 62 90 58 76 L76 62 C100 44 180 36 250 40 Z" fill="#5aa9f0"/>

                    <!-- perut -->
                    <path d="M100 114 C150 134 230 134 276 108 C266 130 244 138 215 138 C170 138 130 132 100 114 Z" fill="#d5ecff"/>

                    <!-- kilau punggung -->
                    <path d="M112 54 C150 42 200 40 240 46" fill="none" stroke="#9fd0ff" stroke-width="5" stroke-linecap="round" opacity="0.7"/>

                    <!-- sirip -->
                    <g class="whale-flipper">
                        <path d="M178 104 C198 108 212 122 204 132 C188 130 176 118 178 104 Z" fill="#3f8fdc"/>
                    </g>

                    <!-- wajah -->
                    <path d="M244 80 Q252 71 260 80" fill="none" stroke="#1b3a5c" stroke-width="3.2" stroke-linecap="round"/>
                    <ellipse cx="236" cy="96" rx="9" ry="5.5" fill="#ff8fb1" opacity="0.6"/>
                    <path d="M256 100 Q268 110 280 96" fill="none" stroke="#1b3a5c" stroke-width="3" stroke-linecap="round"/>

                </svg>

                <!-- anak paus kecil yang mengikuti paus besar -->
                <div class="oc-whale-calf">
                    <svg viewBox="0 0 300 150">
                        <g class="whale-tail">
                            <path d="M64 74 C48 74 28 62 20 42 C34 44 48 50 58 60 C56 46 58 32 68 20 C74 38 76 54 74 66 Z" fill="#5aa9f0"/>
                        </g>
                        <path d="M250 40 C285 40 300 75 292 100 C284 128 250 138 215 138 C170 138 120 132 88 112 C70 102 62 90 58 76 L76 62 C100 44 180 36 250 40 Z" fill="#7ec2ff"/>
                        <path d="M100 114 C150 134 230 134 276 108 C266 130 244 138 215 138 C170 138 130 132 100 114 Z" fill="#eaf6ff"/>
                        <g class="whale-flipper">
                            <path d="M178 104 C198 108 212 122 204 132 C188 130 176 118 178 104 Z" fill="#5aa9f0"/>
                        </g>
                        <circle cx="252" cy="78" r="5" fill="#1b3a5c"/>
                        <circle cx="254" cy="76" r="1.8" fill="#ffffff"/>
                        <ellipse cx="238" cy="95" rx="10" ry="6" fill="#ff8fb1" opacity="0.7"/>
                        <path d="M256 98 Q268 108 278 96" fill="none" stroke="#1b3a5c" stroke-width="3.2" stroke-linecap="round"/>
                    </svg>
                </div>

            </div>
        </div>
    </div>

</div>


<script>
(function () {

    var html = document.documentElement;
    var STORAGE_KEY = 'sparepart_ocean_theme';

    var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var finePointer  = window.matchMedia('(hover: hover) and (pointer: fine)').matches;

    var fg, whale, turtle, crabEl, octoEl, btn, label;
    var fishes   = [];
    var jellies  = [];
    var minnows  = [];
    var foods    = [];
    var running  = false;
    var raf = 0;
    var last = 0;
    var whaleTimer = 0;
    var whaleSongTimer = 0;
    var turtleTimer = 0;
    var turtleGreetTimer = 0;
    var W = window.innerWidth;
    var H = window.innerHeight;
    var mouse = { x: -999, y: -999, t: -99999 };
    var crab = { x: 180, vx: 22, dir: 1, hide: false };

    function rand(a, b) {
        return a + Math.random() * (b - a);
    }

    function pick(list) {
        return list[Math.floor(Math.random() * list.length)];
    }


    /* ---------- gambar ikan ---------- */

    function face(ex, ey) {
        return '' +
            '<circle cx="' + ex + '" cy="' + ey + '" r="5.4" fill="#fff"/>' +
            '<circle cx="' + (ex + 1.2) + '" cy="' + (ey + 0.6) + '" r="3.2" fill="#2b1b2e"/>' +
            '<circle cx="' + (ex + 2.3) + '" cy="' + (ey - 0.6) + '" r="1.2" fill="#fff"/>' +
            '<ellipse cx="' + (ex - 3) + '" cy="' + (ey + 11) + '" rx="4.2" ry="2.5" fill="#ff5c85" opacity=".5"/>' +
            '<path d="M' + (ex - 11) + ' ' + (ey + 9) + ' Q' + (ex - 7) + ' ' + (ey + 13.5) + ' ' + (ex - 2.5) + ' ' + (ey + 11) +
            '" fill="none" stroke="#2b1b2e" stroke-width="1.6" stroke-linecap="round"/>';
    }

    function spikes() {
        var out = '';

        for (var i = 0; i < 10; i++) {
            var a1 = (i * 36 - 7) * Math.PI / 180;
            var a2 = (i * 36 + 7) * Math.PI / 180;
            var at = (i * 36) * Math.PI / 180;

            out += '<path d="M' +
                (46 + 22 * Math.cos(a1)).toFixed(1) + ' ' + (30 + 22 * Math.sin(a1)).toFixed(1) + ' L' +
                (46 + 31 * Math.cos(at)).toFixed(1) + ' ' + (30 + 31 * Math.sin(at)).toFixed(1) + ' L' +
                (46 + 22 * Math.cos(a2)).toFixed(1) + ' ' + (30 + 22 * Math.sin(a2)).toFixed(1) + 'Z"/>';
        }

        return out;
    }

    function fishSVG(type) {

        if (type === 'clown') {
            return '<svg viewBox="0 0 100 60">' +
                '<g class="tail"><path d="M76 30 L98 12 Q92 30 98 48 Z" fill="#ff7a1a"/></g>' +
                '<path d="M36 10 Q48 -2 64 11 Z" fill="#ff7a1a"/>' +
                '<path d="M36 51 Q44 60 54 51 Z" fill="#ff7a1a"/>' +
                '<ellipse cx="48" cy="30" rx="32" ry="22" fill="#ff9a3c"/>' +
                '<path d="M38 9.5 Q33 30 38 50.5 L46 50 Q41 30 46 10 Z" fill="#fff"/>' +
                '<path d="M62 12 Q58 30 62 48 L68 46 Q64 30 68 14 Z" fill="#fff"/>' +
                face(28, 25) +
                '</svg>';
        }

        if (type === 'blue') {
            return '<svg viewBox="0 0 100 60">' +
                '<g class="tail"><path d="M72 30 L97 10 Q90 30 97 50 Z" fill="#2f86e0"/></g>' +
                '<path d="M38 8 Q52 -5 66 12 Z" fill="#2f86e0"/>' +
                '<ellipse cx="46" cy="30" rx="30" ry="24" fill="#4aa8ff"/>' +
                '<ellipse cx="46" cy="42" rx="22" ry="11" fill="#a9dcff"/>' +
                '<path d="M46 32 Q58 38 54 50 Q44 46 46 32 Z" fill="#2f86e0" opacity=".9"/>' +
                face(27, 24) +
                '</svg>';
        }

        if (type === 'pink') {
            return '<svg viewBox="0 0 100 60">' +
                '<g class="tail"><path d="M80 30 Q90 8 100 6 Q94 30 100 54 Q90 52 80 30 Z" fill="#ff6f9f"/></g>' +
                '<path d="M34 14 Q46 -2 66 14 Z" fill="#ff6f9f"/>' +
                '<ellipse cx="48" cy="30" rx="34" ry="19" fill="#ff8fb8"/>' +
                '<ellipse cx="48" cy="38" rx="24" ry="9" fill="#ffd0e1"/>' +
                '<circle cx="52" cy="24" r="2" fill="#ffd0e1"/>' +
                '<circle cx="62" cy="28" r="1.8" fill="#ffd0e1"/>' +
                '<circle cx="46" cy="21" r="1.6" fill="#ffd0e1"/>' +
                face(27, 26) +
                '</svg>';
        }

        /* ikan buntal (membesar saat didekati / diklik) */
        return '<svg viewBox="0 0 100 60">' +
            '<g class="tail"><path d="M70 30 L90 19 Q85 30 90 41 Z" fill="#f2b81f"/></g>' +
            '<g class="puff-body">' +
            '<g class="spikes" fill="#f2b81f">' + spikes() + '</g>' +
            '<circle cx="46" cy="30" r="24" fill="#ffd95a"/>' +
            '<ellipse cx="46" cy="40" rx="17" ry="9" fill="#fff0b3"/>' +
            '<circle cx="54" cy="20" r="2" fill="#f2b81f"/>' +
            '<circle cx="60" cy="28" r="2" fill="#f2b81f"/>' +
            '<circle cx="50" cy="26" r="1.6" fill="#f2b81f"/>' +
            face(36, 25) +
            '</g>' +
            '</svg>';
    }

    function jellySVG(c1, c2) {
        return '<svg viewBox="0 0 70 95">' +
            '<g class="jelly-tentacles" fill="none" stroke="' + c2 + '" stroke-width="3" stroke-linecap="round" opacity="0.85">' +
                '<path d="M22 46 Q16 64 23 84"/>' +
                '<path d="M31 48 Q37 68 29 90"/>' +
                '<path d="M40 48 Q34 68 42 90"/>' +
                '<path d="M48 46 Q54 64 47 84"/>' +
            '</g>' +
            '<g class="jelly-bell">' +
                '<path d="M10 42 C10 12 60 12 60 42 C53 47 45 43 35 46 C25 43 17 47 10 42 Z" fill="' + c1 + '" opacity="0.88"/>' +
                '<path d="M18 28 C22 18 40 16 48 24" fill="none" stroke="#ffffff" stroke-width="3" stroke-linecap="round" opacity="0.55"/>' +
                '<g class="eye-open">' +
                    '<circle cx="27" cy="34" r="2.6" fill="#2b1b2e"/>' +
                    '<circle cx="43" cy="34" r="2.6" fill="#2b1b2e"/>' +
                    '<circle cx="27.8" cy="33.2" r="0.9" fill="#fff"/>' +
                    '<circle cx="43.8" cy="33.2" r="0.9" fill="#fff"/>' +
                '</g>' +
                '<g class="eye-blink" fill="none" stroke="#2b1b2e" stroke-width="2" stroke-linecap="round">' +
                    '<path d="M23 34 Q27 30 31 34"/>' +
                    '<path d="M39 34 Q43 30 47 34"/>' +
                '</g>' +
                '<ellipse cx="21" cy="37" rx="3.2" ry="1.8" fill="#ff6f9f" opacity="0.6"/>' +
                '<ellipse cx="49" cy="37" rx="3.2" ry="1.8" fill="#ff6f9f" opacity="0.6"/>' +
                '<path d="M32 37 Q35 40 38 37" fill="none" stroke="#2b1b2e" stroke-width="1.6" stroke-linecap="round"/>' +
            '</g>' +
        '</svg>';
    }

    function minnowSVG(color) {
        return '<svg viewBox="0 0 40 22">' +
            '<path d="M30 11 L39 4 L37 11 L39 18 Z" fill="' + color + '"/>' +
            '<ellipse cx="18" cy="11" rx="14" ry="6.5" fill="' + color + '"/>' +
            '<path d="M10 9 Q18 7 27 9" fill="none" stroke="#ffffff" stroke-width="1.5" opacity="0.6"/>' +
            '<circle cx="9" cy="9.5" r="1.8" fill="#1b3a5c"/>' +
            '<circle cx="9.5" cy="9" r="0.6" fill="#fff"/>' +
        '</svg>';
    }

    var HEART = '<svg viewBox="0 0 24 24"><path d="M12 21 C4 14 3 9 6 6.5 C8.6 4.6 11 6 12 8 C13 6 15.4 4.6 18 6.5 C21 9 20 14 12 21 Z" fill="#ff6f9f"/></svg>';


    /* ---------- membuat ikan, ubur-ubur & kawanan ikan kecil ---------- */

    function buildFish() {

        var types = ['clown', 'blue', 'pink', 'puffer', 'clown', 'blue', 'pink', 'puffer', 'blue'];

        types.forEach(function (type) {

            var w = type === 'puffer' ? rand(58, 70) : rand(58, 88);
            var el = document.createElement('div');

            el.className = 'oc-fish';
            el.style.setProperty('--w', w.toFixed(0) + 'px');
            el.innerHTML = '<div class="oc-fish-in">' + fishSVG(type) + '</div>';

            fg.appendChild(el);

            var f = {
                el: el,
                type: type,
                w: w,
                h: w * 0.6,
                x: rand(0, Math.max(60, W - w)),
                y: rand(70, Math.max(120, H - 170)),
                vx: rand(-30, 30),
                vy: rand(-10, 10),
                speed: rand(36, 74),
                dir: 1,
                tx: 0,
                ty: 0,
                nextPick: 0,
                puffUntil: 0
            };

            pickTarget(f, 0);
            fishes.push(f);
            draw(f);
        });

        /* ubur-ubur bercahaya */
        var jellyColors = [
            { c1: '#80f5ff', c2: '#48cae4', glow: '#72f5ff' },
            { c1: '#ff9ebb', c2: '#f765a3', glow: '#ff85b3' },
            { c1: '#d0a9ff', c2: '#9d65ff', glow: '#c28fff' },
            { c1: '#8affc1', c2: '#38d399', glow: '#6bffb8' }
        ];

        jellyColors.forEach(function (jc, idx) {
            var w = rand(48, 62);
            var el = document.createElement('div');
            el.className = 'oc-jelly';
            el.style.setProperty('--w', w.toFixed(0) + 'px');
            el.style.setProperty('--glow', jc.glow);
            el.innerHTML = jellySVG(jc.c1, jc.c2);
            fg.appendChild(el);

            var j = {
                el: el,
                w: w,
                h: w * 1.3,
                x: rand(80, Math.max(160, W - 120)),
                y: rand(80, Math.max(160, H - 220)),
                vx: rand(-10, 10),
                vy: rand(-16, -9),
                phase: rand(0, 6.28) + idx
            };
            jellies.push(j);
        });

        /* kawanan ikan kecil yang berenang mengikuti paus */
        var minnowColors = ['#7be3ff', '#ffe082', '#99f7d2', '#ffb3d1'];
        for (var m = 0; m < 12; m++) {
            var mel = document.createElement('div');
            mel.className = 'oc-minnow';
            mel.innerHTML = minnowSVG(minnowColors[m % minnowColors.length]);
            fg.appendChild(mel);

            minnows.push({
                el: mel,
                x: rand(60, Math.max(140, W - 80)),
                y: rand(90, Math.max(180, H * 0.55)),
                vx: rand(-25, 25),
                vy: rand(-10, 10),
                ox: rand(-110, 110),
                oy: rand(-55, 65),
                dir: 1,
                scatterUntil: 0
            });
        }
    }

    function pickTarget(f, now) {
        f.tx = rand(f.w, Math.max(f.w + 40, W - f.w));
        f.ty = rand(70, Math.max(120, H - 170));
        f.nextPick = now + rand(2500, 6500);
    }

    function draw(f) {

        var ang = Math.max(-18, Math.min(18, f.vy * 0.14));

        f.el.style.transform =
            'translate3d(' + f.x.toFixed(1) + 'px,' + f.y.toFixed(1) + 'px,0) ' +
            'scaleX(' + f.dir + ') rotate(' + ang.toFixed(1) + 'deg)';
    }


    /* ---------- efek kecil ---------- */

    function spawn(cls, x, y, css) {

        var el = document.createElement('i');

        el.className = cls;
        el.style.left = x + 'px';
        el.style.top  = y + 'px';

        if (css) {
            Object.keys(css).forEach(function (k) {
                el.style.setProperty(k, css[k]);
            });
        }

        fg.appendChild(el);

        return el;
    }

    function bubbles(x, y, n) {

        for (var i = 0; i < n; i++) {

            var b = spawn('oc-pop', x + rand(-14, 14), y + rand(-8, 8), {
                '--s': rand(6, 14).toFixed(0) + 'px',
                '--dx': rand(-26, 26).toFixed(0) + 'px'
            });

            b.addEventListener('animationend', function () {
                this.remove();
            });
        }
    }

    function hearts(x, y, n) {

        for (var i = 0; i < n; i++) {

            var h = spawn('oc-heart', x + rand(-18, 18), y + rand(-10, 6), {
                '--dx': rand(-18, 18).toFixed(0) + 'px'
            });

            h.innerHTML = HEART;

            h.addEventListener('animationend', function () {
                this.remove();
            });
        }
    }

    function musicNotes(x, y, count) {
        var symbols = ['♪', '♫', '♬', '♩'];
        for (var i = 0; i < count; i++) {
            var n = spawn('oc-note', x + rand(-24, 24), y + rand(-12, 8), {
                '--dx': rand(-36, 36).toFixed(0) + 'px'
            });
            n.textContent = pick(symbols);
            n.addEventListener('animationend', function () {
                this.remove();
            });
        }
    }

    function say(x, y, text) {

        var s = spawn('oc-say', x, y);

        s.textContent = text;

        setTimeout(function () {
            s.remove();
        }, 1800);
    }


    /* ---------- makanan ikan ---------- */

    function dropFood(x, y) {

        for (var i = 0; i < 6; i++) {

            var el = document.createElement('i');

            el.className = 'oc-food';
            el.style.background = pick(['#ffb26b', '#ff9ec4', '#ffe08a']);
            fg.appendChild(el);

            foods.push({
                el: el,
                x: x + rand(-18, 18),
                y: y + rand(-10, 6),
                vy: rand(20, 36),
                phase: rand(0, 6.28)
            });
        }

        bubbles(x, y, 3);
    }

    function nearestFood(cx, cy) {

        var best = null;
        var bestD = 1e9;

        foods.forEach(function (fd) {
            var d = Math.hypot(fd.x - cx, fd.y - cy);

            if (d < bestD) {
                bestD = d;
                best = fd;
            }
        });

        return best && bestD < 460 ? best : null;
    }

    function removeFood(fd) {

        var i = foods.indexOf(fd);

        if (i > -1) {
            foods.splice(i, 1);
        }

        fd.el.remove();
    }


    /* ---------- gerak semua biota ---------- */

    function update(dt, now) {

        var i, fd;

        /* 1. Makanan */
        for (i = foods.length - 1; i >= 0; i--) {

            fd = foods[i];

            fd.y += fd.vy * dt;
            fd.x += Math.sin(now / 420 + fd.phase) * 12 * dt;

            fd.el.style.transform = 'translate3d(' + fd.x.toFixed(1) + 'px,' + fd.y.toFixed(1) + 'px,0)';

            if (fd.y > H - 48) {
                removeFood(fd);
            }
        }

        /* 2. Ikan Utama */
        fishes.forEach(function (f) {

            var cx = f.x + f.w / 2;
            var cy = f.y + f.h / 2;

            if (now > f.nextPick || Math.hypot(f.tx - cx, f.ty - cy) < 30) {
                pickTarget(f, now);
            }

            var dx = f.tx - cx;
            var dy = f.ty - cy;
            var d  = Math.hypot(dx, dy) || 1;

            var desX = dx / d * f.speed;
            var desY = dy / d * f.speed * 0.6;

            /* mengejar makanan */
            var food = nearestFood(cx, cy);

            if (food) {

                dx = food.x - cx;
                dy = food.y - cy;
                d  = Math.hypot(dx, dy) || 1;

                desX = dx / d * f.speed * 1.9;
                desY = dy / d * f.speed * 1.9;

                if (d < 16) {
                    removeFood(food);
                    f.el.classList.add('chomp');
                    setTimeout(function () { f.el.classList.remove('chomp'); }, 320);
                    bubbles(cx, cy, 2);

                    if (Math.random() < 0.35) {
                        hearts(cx, cy, 1);
                    }
                }
            }

            /* menghindari kursor */
            if (finePointer && now - mouse.t < 4000) {

                var mx = cx - mouse.x;
                var my = cy - mouse.y;
                var md = Math.hypot(mx, my);

                if (md < 150) {

                    var k = 1 - md / 150;

                    desX += mx / (md || 1) * f.speed * 3.2 * k;
                    desY += my / (md || 1) * f.speed * 3.2 * k;

                    if (f.type === 'puffer' && md < 110) {
                        f.puffUntil = now + 900;
                    }
                }
            }

            var s = Math.min(1, dt * 2.6);

            f.vx += (desX - f.vx) * s;
            f.vy += (desY - f.vy) * s;

            f.x += f.vx * dt;
            f.y += f.vy * dt;

            /* batas layar */
            if (f.x < -f.w * 0.2)     { f.x = -f.w * 0.2;     f.vx = Math.abs(f.vx); }
            if (f.x > W - f.w * 0.8)  { f.x = W - f.w * 0.8;  f.vx = -Math.abs(f.vx); }
            if (f.y < 50)             { f.y = 50;             f.vy = Math.abs(f.vy); }
            if (f.y > H - 130)        { f.y = H - 130;        f.vy = -Math.abs(f.vy); }

            if (f.vx > 6)       { f.dir = 1; }
            else if (f.vx < -6) { f.dir = -1; }

            if (f.type === 'puffer') {
                f.el.classList.toggle('puffed', now < f.puffUntil);
            }

            draw(f);
        });

        /* 3. Ubur-ubur bercahaya (melayang pelan) */
        jellies.forEach(function (j) {
            j.y += j.vy * dt;
            j.x += Math.sin(now / 900 + j.phase) * 14 * dt + j.vx * dt;
            j.vx *= 0.98;

            if (j.y < -j.h) {
                j.y = H - 90;
                j.x = rand(60, Math.max(120, W - 80));
            }
            if (j.x < 20) { j.x = 20; j.vx = Math.abs(j.vx); }
            if (j.x > W - j.w - 20) { j.x = W - j.w - 20; j.vx = -Math.abs(j.vx); }

            var rot = Math.sin(now / 700 + j.phase) * 6;
            j.el.style.transform = 'translate3d(' + j.x.toFixed(1) + 'px,' + j.y.toFixed(1) + 'px,0) rotate(' + rot.toFixed(1) + 'deg)';
        });

        /* 4. Kawanan ikan kecil (mengikuti paus atau berenang bersama) */
        var wActive = whaleActive();
        var wr = wActive ? whale.getBoundingClientRect() : null;
        var wVisible = wr && wr.right > 0 && wr.left < W;
        var schoolX = wVisible ? (wr.left + wr.width * 0.5) : (W * 0.5 + Math.cos(now / 2600) * (W * 0.28));
        var schoolY = wVisible ? (wr.top + wr.height * 0.55) : (H * 0.32 + Math.sin(now / 1800) * 70);

        minnows.forEach(function (m) {
            if (now > m.scatterUntil) {
                var tx = schoolX + m.ox;
                var ty = schoolY + m.oy;
                var dx = tx - m.x;
                var dy = ty - m.y;
                var dist = Math.hypot(dx, dy) || 1;
                var spd = wVisible ? 115 : 65;

                var desVx = (dx / dist) * Math.min(spd, dist * 1.8);
                var desVy = (dy / dist) * Math.min(spd, dist * 1.8);

                m.vx += (desVx - m.vx) * Math.min(1, dt * 3.5);
                m.vy += (desVy - m.vy) * Math.min(1, dt * 3.5);
            } else {
                m.vx *= 0.985;
                m.vy *= 0.985;
            }

            m.x += m.vx * dt;
            m.y += m.vy * dt;

            if (m.vx > 4) { m.dir = -1; }
            else if (m.vx < -4) { m.dir = 1; }

            m.el.style.transform = 'translate3d(' + m.x.toFixed(1) + 'px,' + m.y.toFixed(1) + 'px,0) scaleX(' + m.dir + ')';
        });

        /* 5. Kepiting berjalan menyamping di pasir & sembunyi saat didekati */
        if (crabEl) {
            var crabScreenY = H - 30;
            var distMouseCrab = Math.hypot((crab.x + 29) - mouse.x, crabScreenY - mouse.y);
            var shouldHide = (finePointer && now - mouse.t < 4000 && distMouseCrab < 130);

            if (shouldHide !== crab.hide) {
                crab.hide = shouldHide;
                crabEl.classList.toggle('hide', shouldHide);
            }

            if (!crab.hide) {
                crab.x += crab.vx * crab.dir * dt;
                var minX = 260;
                var maxX = Math.max(minX + 80, W - 120);
                if (crab.x > maxX) { crab.x = maxX; crab.dir = -1; }
                if (crab.x < minX) { crab.x = minX; crab.dir = 1; }
            }
            crabEl.style.transform = 'translate3d(' + crab.x.toFixed(1) + 'px, 0, 0)';
        }

        /* 6. Gurita pemalu (berubah warna & menutup wajah saat kursor mendekat) */
        if (octoEl && finePointer) {
            var or = octoEl.getBoundingClientRect();
            var ocx = or.left + or.width / 2;
            var ocy = or.top + or.height / 2;
            var od = Math.hypot(ocx - mouse.x, ocy - mouse.y);
            octoEl.classList.toggle('shy', (now - mouse.t < 4000 && od < 145));
        }
    }

    function loop(now) {

        if (!running) {
            return;
        }

        var dt = Math.min(0.05, (now - last) / 1000);

        last = now;

        update(dt, now);

        raf = requestAnimationFrame(loop);
    }


    /* ---------- paus & nyanyian not musik ---------- */

    function whaleActive() {
        return whale.classList.contains('go-ltr') || whale.classList.contains('go-rtl');
    }

    function passWhale() {

        if (!running || whaleActive()) {
            return;
        }

        var ltr = Math.random() < 0.5;

        whale.style.top = rand(H * 0.14, H * 0.4).toFixed(0) + 'px';
        whale.className = 'oc-whale ' + (ltr ? 'go-ltr' : 'go-rtl');
    }

    function scheduleWhale(delay) {

        clearTimeout(whaleTimer);

        whaleTimer = setTimeout(function () {
            passWhale();
            scheduleWhale(rand(32000, 46000));
        }, delay);
    }

    function scheduleWhaleSong() {
        clearInterval(whaleSongTimer);
        whaleSongTimer = setInterval(function () {
            if (!running || !whaleActive()) {
                return;
            }
            var wr = whale.getBoundingClientRect();
            if (wr.right > 60 && wr.left < W - 60) {
                var isRtl = whale.classList.contains('go-rtl');
                var headX = isRtl ? wr.left + 45 : wr.right - 45;
                var headY = wr.top + 50;
                musicNotes(headX, headY, 3);
            }
        }, 3400);
    }

    function hopWhale(x, y) {

        whale.classList.add('hop');

        setTimeout(function () {
            whale.classList.remove('hop');
        }, 950);

        say(x, y - 6, pick(['Bloop!', 'Halo!', 'Hai, ikan-ikan!', 'Blub blub~']));
        musicNotes(x, y - 16, 4);
        hearts(x, y, 4);
        bubbles(x, y, 6);
    }


    /* ---------- penyu lewat & mengangguk menyapa ---------- */

    function turtleActive() {
        return turtle && (turtle.classList.contains('go-ltr') || turtle.classList.contains('go-rtl'));
    }

    function nodTurtle(customX, customY) {
        if (!turtle) return;
        var tr = turtle.getBoundingClientRect();
        var tx = customX || (tr.left + tr.width * 0.7);
        var ty = customY || (tr.top + 10);

        turtle.classList.add('nod');
        setTimeout(function () {
            turtle.classList.remove('nod');
        }, 1200);

        say(tx, ty, pick(['Halo~ 🐢', 'Permisi~', 'Selamat bekerja!', 'Santai dulu~']));
        bubbles(tx, ty + 10, 3);
    }

    function passTurtle() {
        if (!running || !turtle || turtleActive()) {
            return;
        }
        var ltr = Math.random() < 0.5;
        turtle.style.top = rand(H * 0.25, H * 0.62).toFixed(0) + 'px';
        turtle.className = 'oc-turtle ' + (ltr ? 'go-ltr' : 'go-rtl');

        clearTimeout(turtleGreetTimer);
        turtleGreetTimer = setTimeout(function () {
            if (running && turtleActive()) {
                var tr = turtle.getBoundingClientRect();
                if (tr.left > 40 && tr.right < W - 40) {
                    nodTurtle();
                }
            }
        }, rand(7500, 12500));
    }

    function scheduleTurtle(delay) {
        clearTimeout(turtleTimer);
        turtleTimer = setTimeout(function () {
            passTurtle();
            scheduleTurtle(rand(28000, 42000));
        }, delay);
    }


    /* ---------- interaksi klik ---------- */

    function boop(f) {

        f.el.classList.add('boop');

        setTimeout(function () {
            f.el.classList.remove('boop');
        }, 750);

        f.vx += rand(-140, 140);
        f.vy += rand(-60, 60);

        if (f.type === 'puffer') {
            f.puffUntil = performance.now() + 1500;
        }

        hearts(f.x + f.w / 2, f.y + f.h / 2, 3);
    }

    function blinkJelly(j) {
        j.el.classList.add('blink');
        setTimeout(function () {
            j.el.classList.remove('blink');
        }, 800);
        j.vy = -32;
        bubbles(j.x + j.w / 2, j.y + j.h / 3, 5);
        hearts(j.x + j.w / 2, j.y + 10, 2);
    }

    function scatterMinnows(clickX, clickY) {
        var now = performance.now();
        var hit = false;

        minnows.forEach(function (m) {
            var d = Math.hypot((m.x + 12) - clickX, (m.y + 6) - clickY);
            if (d < 130) {
                hit = true;
            }
        });

        if (hit) {
            minnows.forEach(function (m) {
                var angle = Math.atan2((m.y + 6) - clickY, (m.x + 12) - clickX) + rand(-0.5, 0.5);
                var spd = rand(180, 300);
                m.vx = Math.cos(angle) * spd;
                m.vy = Math.sin(angle) * spd;
                m.scatterUntil = now + 2200;
            });
            bubbles(clickX, clickY, 6);
        }

        return hit;
    }

    function pokeCoral(c) {

        var r = c.getBoundingClientRect();

        c.classList.add('poke');

        setTimeout(function () {
            c.classList.remove('poke');
        }, 850);

        bubbles(r.left + r.width / 2, r.top + r.height * 0.25, 6);
    }

    document.addEventListener('click', function (e) {

        if (!html.classList.contains('ocean-on') || !fg) {
            return;
        }

        if (e.target.closest && e.target.closest('a, button, input, select, textarea, label, option, table, .sidebar')) {
            return;
        }

        var x = e.clientX;
        var y = e.clientY;
        var i;

        /* 1. Klik pada Paus */
        if (whaleActive()) {

            var wr = whale.getBoundingClientRect();

            if (x > wr.left && x < wr.right && y > wr.top && y < wr.bottom) {
                hopWhale(x, y);
                return;
            }
        }

        /* 2. Klik pada Penyu */
        if (turtleActive()) {
            var tr = turtle.getBoundingClientRect();
            if (x > tr.left && x < tr.right && y > tr.top && y < tr.bottom) {
                nodTurtle(x, y);
                return;
            }
        }

        /* 3. Klik pada Ubur-ubur (berkedip & menyala) */
        for (i = jellies.length - 1; i >= 0; i--) {
            var j = jellies[i];
            if (x > j.x && x < j.x + j.w && y > j.y && y < j.y + j.h) {
                blinkJelly(j);
                return;
            }
        }

        /* 4. Klik pada Kawanan Ikan Kecil (bubar) */
        if (scatterMinnows(x, y)) {
            return;
        }

        /* 5. Klik pada Ikan Utama */
        for (i = fishes.length - 1; i >= 0; i--) {

            var f = fishes[i];

            if (x > f.x && x < f.x + f.w && y > f.y && y < f.y + f.h) {
                boop(f);
                return;
            }
        }

        /* 6. Klik pada Gurita */
        if (octoEl) {
            var or = octoEl.getBoundingClientRect();
            if (x > or.left && x < or.right && y > or.top && y < or.bottom) {
                octoEl.classList.add('shy');
                say(or.left + or.width / 2, or.top, pick(['Malu~ 🐙', 'Jangan lihat~', 'Hehe...']));
                bubbles(or.left + or.width / 2, or.top + 20, 5);
                return;
            }
        }

        /* 7. Klik pada Kuda Laut & Terumbu Karang */
        var corals = document.querySelectorAll('.oc-coral, .oc-seahorse');

        for (i = 0; i < corals.length; i++) {

            var cr = corals[i].getBoundingClientRect();

            if (cr.width > 0 && x > cr.left && x < cr.right && y > cr.top && y < cr.bottom) {
                pokeCoral(corals[i]);
                return;
            }
        }

        /* 8. Jatuhkan makanan ikan di air */
        if (!reduceMotion) {
            dropFood(x, y);
        }
    });

    document.addEventListener('mousemove', function (e) {
        mouse.x = e.clientX;
        mouse.y = e.clientY;
        mouse.t = performance.now();
    });

    window.addEventListener('resize', function () {
        W = window.innerWidth;
        H = window.innerHeight;
    });


    /* ---------- hidup / mati ---------- */

    function start(justActivated) {

        if (running) {
            return;
        }

        W = window.innerWidth;
        H = window.innerHeight;

        if (fishes.length === 0) {
            buildFish();
        }

        if (reduceMotion) {
            return;
        }

        running = true;
        last = performance.now();
        raf = requestAnimationFrame(loop);

        scheduleWhale(justActivated ? 2500 : 6000);
        scheduleWhaleSong();
        scheduleTurtle(justActivated ? 8000 : 12000);
    }

    function stop() {

        running = false;

        cancelAnimationFrame(raf);
        clearTimeout(whaleTimer);
        clearInterval(whaleSongTimer);
        clearTimeout(turtleTimer);
        clearTimeout(turtleGreetTimer);

        whale.className = 'oc-whale';
        if (turtle) {
            turtle.className = 'oc-turtle';
        }

        while (foods.length) {
            removeFood(foods[0]);
        }
    }

    function updateButton(on) {

        btn.setAttribute('aria-pressed', on ? 'true' : 'false');
        label.textContent = on ? 'Matikan Tema Laut' : 'Aktifkan Tema Laut';
    }

    function setOcean(on) {

        html.classList.toggle('ocean-on', on);

        try {
            localStorage.setItem(STORAGE_KEY, on ? 'on' : 'off');
        } catch (e) {}

        updateButton(on);

        if (on) {
            start(true);
        } else {
            stop();
        }
    }

    function init() {

        fg     = document.getElementById('oceanFg');
        whale  = document.getElementById('ocWhale');
        turtle = document.getElementById('ocTurtle');
        crabEl = document.getElementById('ocCrab');
        octoEl = document.getElementById('ocOctopus');
        btn    = document.getElementById('oceanToggle');

        if (!fg || !whale) {
            return;
        }

        whale.addEventListener('animationend', function (e) {
            if (e.target === whale) {
                whale.className = 'oc-whale';
            }
        });

        if (turtle) {
            turtle.addEventListener('animationend', function (e) {
                if (e.target === turtle) {
                    turtle.className = 'oc-turtle';
                }
            });
        }

        if (btn) {

            label = btn.querySelector('.ocean-btn-label');

            btn.addEventListener('click', function () {
                setOcean(!html.classList.contains('ocean-on'));
            });

            updateButton(html.classList.contains('ocean-on'));
        }

        if (html.classList.contains('ocean-on')) {
            start(false);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
</script>