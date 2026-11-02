<?php
/*
|--------------------------------------------------------------------------
| TEMA TURBIN ANGIN  (pemandangan sawah + kuda yang bisa diklik)
|--------------------------------------------------------------------------
| Simpan sebagai:  includes/windmill-scene.php
|
| Cara pakai di maintenance/history/index.php, letakkan tepat setelah
| <main class="main"> (atau setelah judul halaman):
|
|     <?php include $base . 'includes/windmill-scene.php'; ?>
|
| Fitur:
|   - Turbin angin berputar, awan bergerak, burung terbang, sawah bergelombang
|   - Kuda bisa diklik: melompat kecil, menyapa, lalu mengucapkan 5 kata semangat
|   - Tombol sakelar "Tema Turbin Angin" untuk menyalakan / mematikan tema
|     (pilihan tersimpan di browser)
|   - Otomatis ikut Mode Malam (langit malam, bulan, bintang)
|   - Tidak butuh gambar / file lain, semuanya ada di file ini
*/

/* nama depan user untuk sapaan kuda */
$wm_nama = trim((string) ($_SESSION['nama'] ?? ''));
$wm_nama = ($wm_nama !== '') ? explode(' ', $wm_nama)[0] : 'Sahabat';

/* ---------- turbin angin ---------- */
if (!function_exists('wm_turbine')) {
    function wm_turbine($x, $y, $s, $dur)
    {
        $blades = '';
        foreach ([0, 120, 240] as $a) {
            $blades .= <<<BLADE
<path transform="rotate($a 0 -119)" d="M-2.2 -119 C-2 -138 -1.4 -160 0.3 -182 C1.7 -160 2.9 -138 3.1 -119 Z" style="fill:var(--wm-sail);stroke:var(--wm-wall-shade)" stroke-width=".5"/>

BLADE;
        }

        return <<<SVG
<g transform="translate($x $y) scale($s)">
  <ellipse cx="0" cy="2" rx="16" ry="3.5" fill="#000" opacity=".18"/>
  <path d="M-4.6 0 L-2.6 -116 L2.6 -116 L4.6 0 Z" style="fill:var(--wm-sail)"/>
  <path d="M0 0 L4.6 0 L2.6 -116 L0 -116 Z" style="fill:var(--wm-wall-shade)" opacity=".55"/>
  <path d="M-2.8 -100 L2.8 -100 L2.72 -106 L-2.72 -106 Z" style="fill:var(--wm-roof)"/>
  <rect x="-7" y="-4" width="14" height="4" rx="1.5" style="fill:var(--wm-stone)"/>
  <rect x="-3.5" y="-123.5" width="17" height="9" rx="3.5" style="fill:var(--wm-sail);stroke:var(--wm-wall-shade)" stroke-width=".5"/>
  <g>
    <animateTransform attributeName="transform" type="rotate" from="0 0 -119" to="360 0 -119" dur="{$dur}s" repeatCount="indefinite"/>
    $blades
  </g>
  <circle cx="0" cy="-119" r="4.6" style="fill:var(--wm-sail);stroke:var(--wm-wall-shade)" stroke-width=".6"/>
  <circle cx="-1.2" cy="-120" r="1.4" fill="#fff" opacity=".8"/>
</g>
SVG;
    }
}

/* ---------- pohon kelapa ---------- */
if (!function_exists('wm_palm')) {
    function wm_palm($x, $y, $s, $dur)
    {
        return <<<SVG
<g transform="translate($x $y) scale($s)">
  <g>
    <animateTransform attributeName="transform" type="rotate" values="0 0 0;1.6 0 0;0 0 0;-1.6 0 0;0 0 0" dur="{$dur}s" repeatCount="indefinite"/>
    <path d="M0 0 C4 -28 2 -54 9 -82" fill="none" stroke-width="6" stroke-linecap="round" style="stroke:var(--wm-trunk)"/>
    <g fill="none" stroke-width="4.5" stroke-linecap="round" style="stroke:var(--wm-leaf)">
      <path d="M9 -82 C-10 -94 -28 -88 -40 -72"/>
      <path d="M9 -82 C-4 -102 -22 -106 -38 -100"/>
      <path d="M9 -82 C14 -104 30 -110 46 -102"/>
      <path d="M9 -82 C28 -94 44 -88 52 -72"/>
      <path d="M9 -82 C2 -98 14 -114 24 -116"/>
    </g>
    <circle cx="6" cy="-78" r="3.2" fill="#6b4423"/>
    <circle cx="12" cy="-76" r="3.2" fill="#6b4423"/>
  </g>
</g>
SVG;
    }
}
?>

<!-- =========================================================
     TEMA TURBIN ANGIN
========================================================== -->
<section class="wm-wrap" id="wmWrap" data-on="1">

    <div class="wm-bar">
        <button type="button" class="wm-switch" id="wmSwitch" role="switch" aria-checked="true">
            <span class="wm-track"><span class="wm-knob"></span></span>
            <span>Tema Turbin Angin</span>
            <span class="wm-state" id="wmState">Nyala</span>
        </button>
    </div>

    <div class="wm-scene-wrap">
        <div class="wm-clip">
            <div class="wm-scene" id="wmScene">

                <svg id="wmSvg" viewBox="0 0 1200 320" preserveAspectRatio="xMidYMax slice"
                     xmlns="http://www.w3.org/2000/svg" role="img"
                     aria-label="Pemandangan sawah dengan turbin angin dan seekor kuda">

                    <defs>
                        <linearGradient id="wmSky" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0" style="stop-color:var(--wm-sky-top)"/>
                            <stop offset="1" style="stop-color:var(--wm-sky-bot)"/>
                        </linearGradient>
                        <radialGradient id="wmGlow">
                            <stop offset="0" stop-color="#fff6c0" stop-opacity=".95"/>
                            <stop offset="1" stop-color="#fff6c0" stop-opacity="0"/>
                        </radialGradient>
                        <radialGradient id="wmMoonGlow">
                            <stop offset="0" stop-color="#dfe8ff" stop-opacity=".55"/>
                            <stop offset="1" stop-color="#dfe8ff" stop-opacity="0"/>
                        </radialGradient>
                        <linearGradient id="wmWind" x1="0" y1="0" x2="1" y2="0">
                            <stop offset="0" stop-color="#fff" stop-opacity="0"/>
                            <stop offset=".5" stop-color="#fff" stop-opacity=".22"/>
                            <stop offset="1" stop-color="#fff" stop-opacity="0"/>
                        </linearGradient>
                        <pattern id="wmRice" width="16" height="12" patternUnits="userSpaceOnUse">
                            <path d="M3 12 L3 5 M8 12 L8 2 M13 12 L13 6" fill="none" stroke-width="1.4"
                                  stroke-linecap="round" style="stroke:var(--wm-rice);opacity:.45"/>
                        </pattern>
                    </defs>

                    <!-- LANGIT -->
                    <rect width="1200" height="320" fill="url(#wmSky)"/>

                    <!-- bintang (malam) -->
                    <g class="wm-stars" fill="#fff">
                        <circle cx="90" cy="60" r="1.4"/><circle cx="190" cy="110" r="1.1"/>
                        <circle cx="270" cy="50" r="1.6"/><circle cx="410" cy="90" r="1.2"/>
                        <circle cx="520" cy="40" r="1.4"/><circle cx="640" cy="105" r="1"/>
                        <circle cx="720" cy="55" r="1.5"/><circle cx="800" cy="115" r="1.1"/>
                        <circle cx="1010" cy="50" r="1.4"/><circle cx="1090" cy="100" r="1.2"/>
                        <circle cx="1160" cy="60" r="1.6"/><circle cx="350" cy="30" r="1"/>
                    </g>

                    <!-- matahari -->
                    <g class="wm-sun">
                        <circle cx="880" cy="80" r="70" fill="url(#wmGlow)"/>
                        <circle cx="880" cy="80" r="27" fill="#ffd95a"/>
                    </g>

                    <!-- bulan -->
                    <g class="wm-moon">
                        <circle cx="880" cy="80" r="64" fill="url(#wmMoonGlow)"/>
                        <circle cx="880" cy="80" r="23" fill="#f4f1de"/>
                        <circle cx="872" cy="73" r="4" fill="#dcd8c0"/>
                        <circle cx="889" cy="87" r="3" fill="#dcd8c0"/>
                        <circle cx="886" cy="70" r="2" fill="#dcd8c0"/>
                    </g>

                    <!-- awan -->
                    <g style="fill:var(--wm-cloud);opacity:var(--wm-cloud-op)">
                        <g>
                            <animateTransform attributeName="transform" type="translate" from="-260 0" to="1460 0" dur="95s" begin="-30s" repeatCount="indefinite"/>
                            <g transform="translate(0 66)">
                                <ellipse cx="0" cy="0" rx="48" ry="16"/><ellipse cx="-24" cy="-10" rx="26" ry="16"/><ellipse cx="18" cy="-14" rx="30" ry="19"/>
                            </g>
                        </g>
                        <g>
                            <animateTransform attributeName="transform" type="translate" from="-260 0" to="1460 0" dur="130s" begin="-90s" repeatCount="indefinite"/>
                            <g transform="translate(0 112) scale(.75)">
                                <ellipse cx="0" cy="0" rx="48" ry="16"/><ellipse cx="-24" cy="-10" rx="26" ry="16"/><ellipse cx="18" cy="-14" rx="30" ry="19"/>
                            </g>
                        </g>
                        <g>
                            <animateTransform attributeName="transform" type="translate" from="-260 0" to="1460 0" dur="160s" begin="-40s" repeatCount="indefinite"/>
                            <g transform="translate(0 42) scale(1.15)">
                                <ellipse cx="0" cy="0" rx="48" ry="16"/><ellipse cx="-24" cy="-10" rx="26" ry="16"/><ellipse cx="18" cy="-14" rx="30" ry="19"/>
                            </g>
                        </g>
                    </g>

                    <!-- burung -->
                    <g fill="none" stroke-width="1.8" stroke-linecap="round" style="stroke:var(--wm-bird)">
                        <g>
                            <animateTransform attributeName="transform" type="translate" from="-60 78" to="1260 44" dur="42s" begin="-8s" repeatCount="indefinite"/>
                            <path d="M0 0 q5 -6 10 0 q5 -6 10 0"/>
                            <path d="M22 9 q4 -5 8 0 q4 -5 8 0"/>
                        </g>
                        <g>
                            <animateTransform attributeName="transform" type="translate" from="-80 130" to="1280 96" dur="58s" begin="-35s" repeatCount="indefinite"/>
                            <path d="M0 0 q4 -5 8 0 q4 -5 8 0"/>
                        </g>
                    </g>

                    <!-- gunung -->
                    <path d="M0 205 L90 148 L170 196 L260 126 L360 202 L450 156 L540 208 L640 136 L740 204 L830 160 L930 208 L1030 146 L1120 198 L1200 164 L1200 320 L0 320 Z" style="fill:var(--wm-mtn-far)"/>
                    <path d="M0 226 L120 178 L230 218 L350 170 L470 224 L590 184 L700 226 L820 176 L940 224 L1060 182 L1200 222 L1200 320 L0 320 Z" style="fill:var(--wm-mtn-near)"/>

                    <!-- bukit jauh + turbin kecil -->
                    <path d="M0 222 C150 194 300 200 450 210 C600 220 750 190 900 200 C1050 210 1130 200 1200 194 L1200 320 L0 320 Z" style="fill:var(--wm-hill1)"/>
                    <?= wm_turbine(150, 206, 0.52, 15); ?>
                    <?= wm_turbine(262, 203, 0.42, 19); ?>
                    <?= wm_turbine(1010, 208, 0.55, 16); ?>
                    <?= wm_turbine(1105, 203, 0.42, 21); ?>

                    <!-- bukit turbin utama -->
                    <path d="M0 246 C130 218 250 212 380 222 C470 229 520 248 640 256 C760 262 900 244 1200 250 L1200 320 L0 320 Z" style="fill:var(--wm-hill2)"/>
                    <?= wm_turbine(560, 248, 0.72, 13); ?>
                    <?= wm_turbine(380, 226, 1, 11); ?>

                    <!-- SAWAH -->
                    <path d="M0 250 C200 240 420 258 640 248 C860 238 1040 256 1200 244 L1200 320 L0 320 Z" style="fill:var(--wm-fa)"/>
                    <path d="M0 250 C200 240 420 258 640 248 C860 238 1040 256 1200 244 L1200 320 L0 320 Z" fill="url(#wmRice)"/>
                    <path d="M0 250 C200 240 420 258 640 248 C860 238 1040 256 1200 244" fill="none" stroke-width="2" style="stroke:var(--wm-bund)"/>

                    <path d="M0 268 C220 260 440 278 660 268 C880 258 1040 276 1200 264 L1200 320 L0 320 Z" style="fill:var(--wm-fb)"/>
                    <path d="M0 268 C220 260 440 278 660 268 C880 258 1040 276 1200 264 L1200 320 L0 320 Z" fill="url(#wmRice)"/>
                    <path d="M0 268 C220 260 440 278 660 268 C880 258 1040 276 1200 264" fill="none" stroke-width="2" style="stroke:var(--wm-bund)"/>

                    <path d="M0 290 C240 282 460 300 700 290 C900 282 1060 298 1200 288 L1200 320 L0 320 Z" style="fill:var(--wm-fc)"/>
                    <path d="M0 290 C240 282 460 300 700 290 C900 282 1060 298 1200 288 L1200 320 L0 320 Z" fill="url(#wmRice)"/>
                    <path d="M0 290 C240 282 460 300 700 290 C900 282 1060 298 1200 288" fill="none" stroke-width="2" style="stroke:var(--wm-bund)"/>

                    <!-- kilau angin yang menyapu sawah -->
                    <rect x="-400" y="244" width="400" height="76" fill="url(#wmWind)">
                        <animateTransform attributeName="transform" type="translate" from="0 0" to="1700 0" dur="10s" repeatCount="indefinite"/>
                    </rect>

                    <!-- pohon kelapa -->
                    <?= wm_palm(40, 272, 0.9, 7); ?>
                    <?= wm_palm(1070, 268, 0.95, 6); ?>
                    <?= wm_palm(1130, 278, 1.15, 8); ?>

                    <!-- KUDA (klik aku!) -->
                    <g id="wmHorse" class="wm-horse" transform="translate(640 296) scale(.62)"
                       role="button" tabindex="0" aria-label="Klik kuda untuk disapa">
                        <ellipse cx="96" cy="1" rx="74" ry="7" fill="#000" opacity=".22"/>

                        <g class="wm-hop-g" id="wmHop">
                            <g transform="translate(0 -132)">

                                <!-- kaki sisi jauh -->
                                <g style="fill:#6b4220">
                                    <path d="M56 82 L68 82 L67 112 L69 132 L58 132 L59 112 Z"/>
                                    <path d="M88 82 L98 82 L97 112 L99 132 L89 132 L90 112 Z"/>
                                </g>
                                <g fill="#241810">
                                    <rect x="58" y="127" width="11" height="5" rx="1.5"/>
                                    <rect x="89" y="127" width="10" height="5" rx="1.5"/>
                                </g>

                                <!-- ekor -->
                                <g>
                                    <animateTransform attributeName="transform" type="rotate"
                                        values="0 36 58;-7 36 58;0 36 58;6 36 58;0 36 58" dur="2.8s" repeatCount="indefinite"/>
                                    <path d="M36 56 C16 58 4 82 9 112 C16 96 20 84 38 76 Z" fill="#2a1a10"/>
                                    <path d="M30 70 C20 78 16 92 15 104" fill="none" stroke="#4a3220" stroke-width="2" stroke-linecap="round"/>
                                </g>

                                <!-- badan -->
                                <path d="M32 64 C32 50 54 45 80 47 C106 45 128 50 130 66 C132 82 118 94 100 94 L52 94 C40 94 32 80 32 64 Z" fill="#8b5a2b"/>
                                <path d="M46 54 C62 48 100 48 118 55" fill="none" stroke="#b07a40" stroke-width="4" stroke-linecap="round" opacity=".55"/>

                                <!-- selimut pelana -->
                                <path d="M66 49 L96 49 L94 78 C86 82 76 82 68 78 Z" fill="#c2410c"/>
                                <path d="M70 56 H92 M70 63 H92 M71 70 H91" stroke="#ffd37a" stroke-width="1.6" fill="none" opacity=".9"/>

                                <!-- kaki sisi dekat -->
                                <path d="M38 80 L54 80 L52 112 L55 132 L43 132 L45 112 Z" fill="#8b5a2b"/>
                                <path d="M102 82 L117 82 L115 110 L116 132 L105 132 L105 110 Z" fill="#8b5a2b"/>
                                <g fill="#241810">
                                    <rect x="43" y="127" width="12" height="5" rx="1.5"/>
                                    <rect x="105" y="127" width="11" height="5" rx="1.5"/>
                                </g>

                                <!-- leher -->
                                <path d="M108 56 C116 38 128 24 142 16 L164 30 C154 42 146 58 140 80 L112 88 Z" fill="#8b5a2b"/>

                                <!-- surai -->
                                <path d="M142 14 C128 20 116 34 108 54 C114 52 118 50 122 46 C120 52 116 58 112 64 C120 62 126 56 130 50 C130 56 126 64 124 70 C132 64 136 54 138 46 L146 30 Z" fill="#2a1a10"/>

                                <!-- kepala -->
                                <g>
                                    <animateTransform attributeName="transform" type="rotate"
                                        values="0 146 38;3 146 38;0 146 38;-2 146 38;0 146 38" dur="3.4s" repeatCount="indefinite"/>
                                    <path d="M146 12 L148 0 L157 10 Z" fill="#6b4220"/>
                                    <path d="M140 14 C148 6 162 8 168 18 L186 42 C189 49 183 54 176 52 C165 50 154 42 147 33 C142 26 138 19 140 14 Z" fill="#8b5a2b"/>
                                    <ellipse cx="181" cy="47" rx="6.5" ry="5" fill="#5e3a1c" transform="rotate(30 181 47)"/>
                                    <circle cx="183" cy="46" r="1.2" fill="#241810"/>
                                    <circle cx="166" cy="35" r="4.5" fill="#e58a7b" opacity=".35"/>
                                    <ellipse cx="158" cy="22" rx="2.7" ry="3.2" fill="#1a1008"/>
                                    <circle cx="158.8" cy="20.8" r=".9" fill="#fff"/>
                                    <path d="M146 12 C152 12 156 16 156 23 C152 21 148 18 146 12 Z" fill="#2a1a10"/>
                                </g>

                            </g>
                        </g>

                        <rect class="wm-hit" x="-8" y="-152" width="208" height="160" fill="transparent" pointer-events="all"/>
                    </g>

                </svg>

                <div class="wm-hint" id="wmHint">Klik aku! 🐴</div>

                <div class="wm-bubble" id="wmBubble" role="status" aria-live="polite">
                    <button type="button" class="wm-close" id="wmClose" aria-label="Tutup">&times;</button>
                    <div class="wm-b-title" id="wmTitle"></div>
                    <div class="wm-b-text" id="wmText"></div>
                    <div class="wm-b-dots" id="wmDots"></div>
                </div>

            </div>
        </div>
    </div>

</section>


<style>
    /* ---------- warna tema (terang) ---------- */
    .wm-wrap {
        --wm-sky-top: #6ec3f5;
        --wm-sky-bot: #fff0c9;
        --wm-cloud: #ffffff;
        --wm-cloud-op: .92;
        --wm-mtn-far: #b4cbe6;
        --wm-mtn-near: #92b4d6;
        --wm-hill1: #9bd884;
        --wm-hill2: #72c25f;
        --wm-fa: #c4df62;
        --wm-fb: #86c64c;
        --wm-fc: #549f3b;
        --wm-rice: #2f7d2b;
        --wm-bund: #e3f0b0;
        --wm-wall: #f5ebd3;
        --wm-wall-shade: #dccdaa;
        --wm-stone: #c8b88f;
        --wm-roof: #b9503e;
        --wm-sail: #fbf6e8;
        --wm-wood: #6b4423;
        --wm-window: #86b2dc;
        --wm-bird: #3a4a63;
        --wm-leaf: #2f8a3a;
        --wm-trunk: #7a5230;
        --wm-sun-op: 1;
        --wm-moon-op: 0;
        --wm-star-op: 0;
        --wm-bubble-bg: #ffffff;
        --wm-bubble-ink: #2d2d44;
        --wm-bubble-accent: #c2410c;

        margin: 0 0 16px;
    }

    /* ---------- warna tema (Mode Malam) ---------- */
    [data-theme="dark"] .wm-wrap {
        --wm-sky-top: #0a1430;
        --wm-sky-bot: #34457a;
        --wm-cloud: #8b9ac8;
        --wm-cloud-op: .35;
        --wm-mtn-far: #1f2d5a;
        --wm-mtn-near: #18254b;
        --wm-hill1: #1f4f3b;
        --wm-hill2: #19412f;
        --wm-fa: #2f6b3b;
        --wm-fb: #245830;
        --wm-fc: #19441f;
        --wm-rice: #7fd186;
        --wm-bund: #4a8654;
        --wm-wall: #d9ccab;
        --wm-wall-shade: #b7a982;
        --wm-stone: #9d9071;
        --wm-roof: #8f3b31;
        --wm-sail: #ebe3cf;
        --wm-wood: #4d3219;
        --wm-window: #ffd37a;
        --wm-bird: #aab6d6;
        --wm-leaf: #1f6b2d;
        --wm-trunk: #5a3d22;
        --wm-sun-op: 0;
        --wm-moon-op: 1;
        --wm-star-op: .9;
        --wm-bubble-bg: #202748;
        --wm-bubble-ink: #eef0ff;
        --wm-bubble-accent: #ffd37a;
    }

    [data-theme="dark"] .wm-horse { filter: brightness(.82); }

    .wm-sun   { opacity: var(--wm-sun-op);  transition: opacity .5s; }
    .wm-moon  { opacity: var(--wm-moon-op); transition: opacity .5s; }
    .wm-stars { opacity: var(--wm-star-op); transition: opacity .5s; }

    /* ---------- bar sakelar ---------- */
    .wm-bar {
        display: flex;
        justify-content: flex-end;
        margin-bottom: 10px;
    }

    .wm-switch {
        display: inline-flex;
        align-items: center;
        gap: 10px;
        height: 38px;
        padding: 0 14px 0 10px;
        border: 1px solid var(--line-strong, #dedeea);
        border-radius: 999px;
        background: var(--surface, #ffffff);
        color: var(--ink-2, #4a4a63);
        font-family: inherit;
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        transition: background .15s, border-color .15s;
    }

    .wm-switch:hover { border-color: var(--primary, #7c5cfc); }

    .wm-track {
        position: relative;
        width: 34px;
        height: 20px;
        border-radius: 999px;
        background: var(--line-strong, #cfcfe0);
        transition: background .2s;
        flex-shrink: 0;
    }

    .wm-knob {
        position: absolute;
        top: 2px;
        left: 2px;
        width: 16px;
        height: 16px;
        border-radius: 50%;
        background: #ffffff;
        box-shadow: 0 1px 3px rgba(0, 0, 0, .3);
        transition: transform .2s;
    }

    .wm-switch[aria-checked="true"] .wm-track { background: #3fae5a; }
    .wm-switch[aria-checked="true"] .wm-knob  { transform: translateX(14px); }

    .wm-state {
        font-size: 12px;
        font-weight: 600;
        color: var(--muted, #8a8aa0);
        min-width: 38px;
    }

    /* ---------- kotak pemandangan (bisa dilipat) ---------- */
    .wm-scene-wrap {
        display: grid;
        grid-template-rows: 1fr;
        opacity: 1;
    }

    .wm-ready .wm-scene-wrap {
        transition: grid-template-rows .45s ease, opacity .35s ease, visibility 0s;
    }

    .wm-wrap[data-on="0"] .wm-scene-wrap {
        grid-template-rows: 0fr;
        opacity: 0;
        visibility: hidden;
    }

    .wm-ready .wm-wrap[data-on="0"] .wm-scene-wrap,
    .wm-wrap.wm-ready[data-on="0"] .wm-scene-wrap {
        transition: grid-template-rows .45s ease, opacity .35s ease, visibility 0s .45s;
    }

    .wm-clip {
        overflow: hidden;
        min-height: 0;
    }

    .wm-scene {
        position: relative;
        height: clamp(280px, 25vw, 360px);
        border-radius: var(--radius, 12px);
        border: 1px solid var(--line, #ececf3);
        overflow: hidden;
    }

    .wm-scene svg {
        display: block;
        width: 100%;
        height: 100%;
    }

    /* ---------- kuda ---------- */
    .wm-horse { cursor: pointer; outline: none; }

    .wm-hop-g {
        transform-box: fill-box;
        transform-origin: 50% 100%;
        transition: filter .15s;
    }

    .wm-horse:hover .wm-hop-g { filter: brightness(1.08); }

    .wm-horse:focus-visible .wm-hit {
        stroke: rgba(255, 255, 255, .95);
        stroke-width: 2;
        stroke-dasharray: 6 4;
    }

    .wm-hop-g.wm-hop { animation: wm-hop .85s ease; }

    @keyframes wm-hop {
        0%   { transform: translateY(0) rotate(0); }
        22%  { transform: translateY(-34px) rotate(-4deg); }
        45%  { transform: translateY(0) rotate(0); }
        65%  { transform: translateY(-14px) rotate(-2deg); }
        100% { transform: translateY(0) rotate(0); }
    }

    /* ---------- petunjuk "Klik aku!" ---------- */
    .wm-hint {
        position: absolute;
        z-index: 2;
        padding: 5px 11px;
        border-radius: 999px;
        background: rgba(255, 255, 255, .94);
        color: #3b3b52;
        font-size: 12px;
        font-weight: 600;
        white-space: nowrap;
        box-shadow: 0 4px 14px rgba(0, 0, 0, .2);
        pointer-events: none;
        opacity: 0;
        transform: translate(-50%, calc(-100% - 6px));
        transition: opacity .25s;
    }

    .wm-hint.show {
        opacity: 1;
        animation: wm-bob 1.6s ease-in-out infinite;
    }

    @keyframes wm-bob {
        0%, 100% { transform: translate(-50%, calc(-100% - 6px)); }
        50%      { transform: translate(-50%, calc(-100% - 13px)); }
    }

    /* ---------- gelembung ucapan ---------- */
    .wm-bubble {
        position: absolute;
        z-index: 3;
        width: min(310px, calc(100% - 20px));
        padding: 14px 16px 12px;
        border-radius: 16px;
        background: var(--wm-bubble-bg);
        color: var(--wm-bubble-ink);
        font-size: 13.5px;
        line-height: 1.55;
        box-shadow: 0 12px 32px rgba(0, 0, 0, .28);
        opacity: 0;
        pointer-events: none;
        transform: translate(-50%, calc(-100% - 14px)) scale(.9);
        transform-origin: 50% 100%;
        transition: opacity .2s, transform .2s;
    }

    .wm-bubble.show {
        opacity: 1;
        pointer-events: auto;
        transform: translate(-50%, calc(-100% - 14px)) scale(1);
    }

    .wm-bubble::after {
        content: "";
        position: absolute;
        left: var(--tail, 50%);
        bottom: -7px;
        width: 14px;
        height: 14px;
        background: inherit;
        border-radius: 0 0 4px 0;
        transform: translateX(-50%) rotate(45deg);
    }

    .wm-b-title {
        padding-right: 18px;
        margin-bottom: 4px;
        font-size: 14px;
        font-weight: 700;
        color: var(--wm-bubble-accent);
    }

    .wm-b-dots {
        display: flex;
        gap: 5px;
        margin-top: 9px;
    }

    .wm-b-dots i {
        width: 6px;
        height: 6px;
        border-radius: 50%;
        background: currentColor;
        opacity: .2;
        transition: opacity .2s, transform .2s;
    }

    .wm-b-dots i.on {
        opacity: 1;
        transform: scale(1.25);
        background: var(--wm-bubble-accent);
    }

    .wm-close {
        position: absolute;
        top: 6px;
        right: 8px;
        width: 24px;
        height: 24px;
        border: none;
        border-radius: 50%;
        background: transparent;
        color: inherit;
        font-size: 18px;
        line-height: 1;
        cursor: pointer;
        opacity: .55;
    }

    .wm-close:hover { opacity: 1; }

    /* ---------- hati melayang ---------- */
    .wm-heart {
        position: absolute;
        z-index: 4;
        font-size: 18px;
        pointer-events: none;
        opacity: 0;
        animation: wm-float 1.4s ease-out forwards;
    }

    @keyframes wm-float {
        0%   { opacity: 0; transform: translateY(0) scale(.6); }
        15%  { opacity: 1; }
        100% { opacity: 0; transform: translateY(-72px) scale(1.1); }
    }

    @media (prefers-reduced-motion: reduce) {
        .wm-hint.show,
        .wm-hop-g.wm-hop { animation: none; }
    }
</style>


<script>
(function () {
    'use strict';

    var wrap = document.getElementById('wmWrap');
    if (!wrap) return;

    var sw      = document.getElementById('wmSwitch');
    var stateEl = document.getElementById('wmState');
    var scene   = document.getElementById('wmScene');
    var svg     = document.getElementById('wmSvg');
    var horse   = document.getElementById('wmHorse');
    var hop     = document.getElementById('wmHop');
    var bubble  = document.getElementById('wmBubble');
    var titleEl = document.getElementById('wmTitle');
    var textEl  = document.getElementById('wmText');
    var dotsEl  = document.getElementById('wmDots');
    var closeBt = document.getElementById('wmClose');
    var hint    = document.getElementById('wmHint');

    var NAME = <?= json_encode($wm_nama, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE); ?>;
    var KEY  = 'wmTheme';

    var reduced = window.matchMedia &&
                  window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    /* 5 sapaan + kata semangat dari kuda */
    var ITEMS = [
        {
            t: 'Halo, {nama}! 👋 Aku si Kuda Sawah.',
            m: 'Senang bertemu denganmu. Terima kasih sudah merawat mesin-mesin itu dengan sepenuh hati. Kerja kerasmu yang bikin semuanya terus berjalan.'
        },
        {
            t: 'Hei {nama}, sini sebentar... 🌾',
            m: 'Tidak apa-apa kalau hari ini terasa melelahkan. Padi pun butuh waktu untuk menguning. Prosesmu juga sedang tumbuh, pelan tapi pasti.'
        },
        {
            t: '{nama}, dengarkan ini ya 💛',
            m: 'Turbin itu terus berputar bukan karena anginnya selalu kencang, tapi karena ia tetap berdiri dan siap menyambut angin yang datang. Kamu juga kuat.'
        },
        {
            t: 'Ssst, {nama}... 🌅',
            m: 'Hal kecil yang kamu rawat hari ini bisa menyelamatkan banyak hal di hari esok. Pekerjaanmu penting, dan kamu lebih berarti dari yang kamu kira.'
        },
        {
            t: 'Pesan terakhir dariku, {nama} 🐴💖',
            m: 'Pelan-pelan saja, yang penting tidak berhenti. Aku bangga padamu, kamu sudah sejauh ini. Istirahatlah kalau lelah, lalu lanjutkan lagi.'
        }
    ];

    ITEMS.forEach(function () { dotsEl.appendChild(document.createElement('i')); });

    var idx = 0, typer = null, hideTimer = null, clicked = false;

    /* ---------- penempatan elemen di atas kuda ---------- */
    function place(el, withTail) {
        var s = scene.getBoundingClientRect();
        var h = horse.getBoundingClientRect();
        var hx = h.left + h.width * 0.62 - s.left;
        var half = el.offsetWidth / 2 + 10;
        var cx = Math.max(half, Math.min(s.width - half, hx));

        el.style.left = cx + 'px';
        el.style.top  = Math.max(h.top - s.top, 0) + 'px';

        if (withTail) {
            var tx = hx - (cx - el.offsetWidth / 2);
            tx = Math.max(22, Math.min(el.offsetWidth - 22, tx));
            el.style.setProperty('--tail', tx + 'px');
        }
    }

    function placeHint() {
        if (clicked || wrap.getAttribute('data-on') !== '1') return;
        place(hint, false);
        hint.classList.add('show');
    }

    function hide() {
        clearInterval(typer);
        clearTimeout(hideTimer);
        bubble.classList.remove('show');
    }

    /* ---------- hati melayang ---------- */
    function hearts() {
        if (reduced) return;
        var s = scene.getBoundingClientRect();
        var h = horse.getBoundingClientRect();

        ['💛', '🌾', '💚'].forEach(function (emoji, i) {
            var el = document.createElement('span');
            el.className = 'wm-heart';
            el.textContent = emoji;
            el.style.left = (h.left - s.left + h.width * (0.4 + 0.15 * i)) + 'px';
            el.style.top  = (h.top - s.top + 12) + 'px';
            el.style.animationDelay = (i * 0.12) + 's';
            scene.appendChild(el);
            setTimeout(function () { el.remove(); }, 1700);
        });
    }

    /* ---------- kuda diklik ---------- */
    function talk() {
        clicked = true;
        hint.classList.remove('show');

        var item = ITEMS[idx];
        var msg  = item.m;

        clearInterval(typer);
        clearTimeout(hideTimer);

        titleEl.textContent = item.t.split('{nama}').join(NAME);

        /* ukur tinggi teks penuh dulu supaya gelembung tidak "loncat" saat mengetik */
        textEl.style.minHeight = '';
        textEl.textContent = msg;
        textEl.style.minHeight = textEl.offsetHeight + 'px';
        textEl.textContent = '';

        Array.prototype.forEach.call(dotsEl.children, function (d, i) {
            d.className = (i === idx) ? 'on' : '';
        });

        place(bubble, true);
        bubble.classList.add('show');

        hop.classList.remove('wm-hop');
        void hop.getBoundingClientRect();
        hop.classList.add('wm-hop');
        hearts();

        if (reduced) {
            textEl.textContent = msg;
        } else {
            var i = 0;
            typer = setInterval(function () {
                i++;
                textEl.textContent = msg.slice(0, i);
                if (i >= msg.length) clearInterval(typer);
            }, 22);
        }

        hideTimer = setTimeout(hide, (reduced ? 0 : msg.length * 22) + 9000);
        idx = (idx + 1) % ITEMS.length;
    }

    hop.addEventListener('animationend', function () { hop.classList.remove('wm-hop'); });

    horse.addEventListener('click', talk);
    horse.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            talk();
        }
    });

    scene.addEventListener('click', function (e) {
        if (!horse.contains(e.target) && !bubble.contains(e.target)) hide();
    });

    closeBt.addEventListener('click', hide);

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') hide();
    });

    /* ---------- sakelar nyala / mati ---------- */
    function setOn(on, save) {
        wrap.setAttribute('data-on', on ? '1' : '0');
        sw.setAttribute('aria-checked', on ? 'true' : 'false');
        stateEl.textContent = on ? 'Nyala' : 'Mati';

        if (!on) {
            hide();
            hint.classList.remove('show');
            try { svg.pauseAnimations(); } catch (e) {}
        } else {
            if (!reduced) { try { svg.unpauseAnimations(); } catch (e) {} }
            setTimeout(placeHint, 500);
        }

        if (save) {
            try { localStorage.setItem(KEY, on ? 'on' : 'off'); } catch (e) {}
        }
    }

    sw.addEventListener('click', function () {
        setOn(wrap.getAttribute('data-on') !== '1', true);
    });

    var stored = null;
    try { stored = localStorage.getItem(KEY); } catch (e) {}

    setOn(stored !== 'off', false);

    if (reduced) { try { svg.pauseAnimations(); } catch (e) {} }

    /* aktifkan transisi setelah keadaan awal terpasang (tanpa kedip) */
    requestAnimationFrame(function () { wrap.classList.add('wm-ready'); });

    window.addEventListener('load', function () { setTimeout(placeHint, 300); });

    window.addEventListener('resize', function () {
        if (bubble.classList.contains('show')) place(bubble, true);
        if (hint.classList.contains('show'))   place(hint, false);
    });
})();
</script>