<?php
/*
|--------------------------------------------------------------------------
| SIDEBAR BERSAMA
|--------------------------------------------------------------------------
| Cara pakai di tiap halaman, SEBELUM include ini:
|
|   $base   = '';        // index.php (root)
|   $base   = '../';     // equipment/, fuel/, lubricant/, maintenance/, dst
|   $base   = '../../';  // maintenance/history/
|
|   $active = 'dashboard'; // lihat daftar key di bawah
|
|   include $base . 'includes/sidebar.php';
|
| Gaya sidebar NAVY ada di blok <style> paling bawah file ini,
| sehingga selalu menang atas CSS lain yang dimuat di <head>.
*/

$base   = $base   ?? '';
$active = $active ?? '';

$icons = [
    'dashboard'   => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>',
    'equipment'   => '<path d="M3 17h13M5 17V9l4-3h5l3 4v7"/><circle cx="7" cy="19" r="2"/><circle cx="14" cy="19" r="2"/><path d="M17 10h4l-1 7"/>',
    'schedule'    => '<rect x="3" y="4" width="18" height="17" rx="2"/><path d="M3 9h18M8 2v4M16 2v4"/>',
    'history'     => '<path d="M3 12a9 9 0 1 0 3-6.7L3 8"/><path d="M3 3v5h5M12 8v4l3 2"/>',
    'breakdown'   => '<path d="M12 3 2 20h20L12 3Z"/><path d="M12 10v4M12 17.5v.01"/>',
    'sparepart'   => '<circle cx="12" cy="12" r="3"/><path d="M19 13a7 7 0 0 0 0-2l2-1.5-2-3.5-2.3 1a7 7 0 0 0-2-1.2L14.3 3H9.7l-.4 2.8a7 7 0 0 0-2 1.2l-2.3-1-2 3.5L5 11a7 7 0 0 0 0 2l-2 1.5 2 3.5 2.3-1a7 7 0 0 0 2 1.2l.4 2.8h4.6l.4-2.8a7 7 0 0 0 2-1.2l2.3 1 2-3.5-2-1.5Z"/>',
    'lubricant'   => '<path d="M12 3s6 6.5 6 11a6 6 0 0 1-12 0c0-4.5 6-11 6-11Z"/>',
    'fuel'        => '<path d="M4 21V5a2 2 0 0 1 2-2h6a2 2 0 0 1 2 2v16M3 21h12"/><path d="M14 9h2a2 2 0 0 1 2 2v5a1.5 1.5 0 0 0 3 0V8l-3-3"/><path d="M7 8h4"/>',
    'cost'        => '<circle cx="12" cy="12" r="9"/><path d="M14.5 9.5c-.5-1-1.5-1.5-2.5-1.5-1.5 0-2.5.8-2.5 2s1 1.7 2.5 2 2.5.8 2.5 2-1 2-2.5 2c-1.1 0-2.1-.5-2.5-1.5M12 6.5V8M12 16v1.5"/>',
    'performance' => '<path d="M3 20h18"/><rect x="5" y="11" width="3" height="7"/><rect x="11" y="7" width="3" height="11"/><rect x="17" y="3" width="3" height="15"/>',
    'report'      => '<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8l-5-5Z"/><path d="M14 3v5h5M9 13h6M9 17h6"/>',
    'logout'      => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/>',
    'moon'        => '<path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8Z"/>',
    'sun'         => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>',
];

/* key => [label, link, ikon] */
$menu_groups = [
    'Menu Utama' => [
        'dashboard' => ['Dashboard',            'index.php',                       'dashboard'],
        'equipment' => ['Equipment Master',     'equipment/index.php',             'equipment'],
        'schedule'  => ['Jadwal Pemeliharaan',  'maintenance/index.php',           'schedule'],
        'history'   => ['Histori Pemeliharaan', 'maintenance/history/index.php',   'history'],
        'breakdown' => ['Breakdown & Failure',  'breakdown/index.php',             'breakdown'],
    ],
    'Suku Cadang & BBM' => [
        'sparepart' => ['Spare Part',           'sparepart/index.php',             'sparepart'],
        'lubricant' => ['Lubricant',            'lubricant/index.php',             'lubricant'],
        'fuel'      => ['Fuel Performance',     'fuel/index.php',                  'fuel'],
    ],
    'Lainnya' => [
        'cost'        => ['Biaya Pemeliharaan', '#',                               'cost'],
        'performance' => ['Kinerja & Evaluasi', '#',                               'performance'],
        'report'      => ['Laporan',            '#',                               'report'],
    ],
];

$nama_user = $_SESSION['nama'] ?? 'User';
$inisial   = strtoupper(substr($nama_user, 0, 1));

function sidebar_icon($icons, $key, $class = '')
{
    $cls = $class !== '' ? ' class="' . $class . '"' : '';
    return '<svg' . $cls . ' viewBox="0 0 24 24" aria-hidden="true">' . ($icons[$key] ?? '') . '</svg>';
}
?>
<script>
    /* Terapkan tema tersimpan secepat mungkin, lalu sediakan fungsi ganti tema */
    (function () {
        try {
            var t = localStorage.getItem('theme');
            document.documentElement.setAttribute('data-theme', t === 'dark' ? 'dark' : 'light');
        } catch (e) {}
    })();

    function toggleTheme() {
        var isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        var next = isDark ? 'light' : 'dark';

        document.documentElement.setAttribute('data-theme', next);

        try {
            localStorage.setItem('theme', next);
        } catch (e) {}

        window.dispatchEvent(new Event('themechange'));
    }
</script>

<aside class="sidebar">

    <div class="logo">
        <img src="<?= $base; ?>assets/logo.png" alt="Logo PT Biringkassi Raya">
        <div class="logo-text">PT BIRINGKASSI RAYA</div>
    </div>

    <?php foreach ($menu_groups as $judul => $items): ?>

        <div class="menu-title"><?= htmlspecialchars($judul); ?></div>

        <ul class="menu">

            <?php foreach ($items as $key => $item): ?>

                <?php
                $href = ($item[1] === '#') ? '#' : $base . $item[1];
                ?>

                <li>
                    <a
                        href="<?= $href; ?>"
                        class="<?= ($active === $key) ? 'active' : ''; ?>"
                    >
                        <?= sidebar_icon($icons, $item[2]); ?>
                        <?= htmlspecialchars($item[0]); ?>
                    </a>
                </li>

            <?php endforeach; ?>

        </ul>

    <?php endforeach; ?>

    <!-- TOMBOL MODE MALAM -->
    <button type="button" class="theme-toggle" onclick="toggleTheme()">
        <?= sidebar_icon($icons, 'moon', 'icon-moon'); ?>
        <?= sidebar_icon($icons, 'sun', 'icon-sun'); ?>
        <span class="label-dark">Mode Malam</span>
        <span class="label-light">Mode Terang</span>
    </button>

    <div class="sidebar-footer">

        <div class="sidebar-avatar"><?= htmlspecialchars($inisial); ?></div>

        <div class="sidebar-user">
            <?= htmlspecialchars($nama_user); ?>
            <small>Login aktif</small>
        </div>

        <a
            href="<?= $base; ?>auth/logout.php"
            class="sidebar-logout"
            title="Logout"
        >
            <?= sidebar_icon($icons, 'logout'); ?>
        </a>

    </div>

</aside>

<!-- =========================================================
     GAYA SIDEBAR NAVY
     Diletakkan setelah </aside> supaya dibaca terakhir dan
     menimpa gaya lama dari app.css / tema lain.
     Ubah warna cukup lewat variabel --sb-* di bawah.
========================================================== -->
<style>
    aside.sidebar {
        --sb-top: #0b1f3a;
        --sb-bottom: #12305a;
        --sb-text: #c5d0e2;
        --sb-muted: #7e92b3;
        --sb-line: rgba(255, 255, 255, 0.08);
        --sb-line-strong: rgba(255, 255, 255, 0.16);
        --sb-hover: rgba(255, 255, 255, 0.07);
        --sb-active: rgba(255, 255, 255, 0.12);
        --sb-accent: #d4af63;

        background: linear-gradient(180deg, var(--sb-top) 0%, var(--sb-bottom) 100%);
        border-right: 1px solid rgba(255, 255, 255, 0.06);
        color: #ffffff;
        scrollbar-width: thin;
        scrollbar-color: rgba(255, 255, 255, 0.18) transparent;
    }

    /* Mode Malam: navy lebih gelap agar tetap beda dari latar halaman */
    [data-theme="dark"] aside.sidebar {
        --sb-top: #071427;
        --sb-bottom: #0b1d38;
        --sb-line: rgba(255, 255, 255, 0.07);
        --sb-line-strong: rgba(255, 255, 255, 0.14);
    }

    aside.sidebar::-webkit-scrollbar { width: 6px; }
    aside.sidebar::-webkit-scrollbar-thumb {
        background: rgba(255, 255, 255, 0.18);
        border-radius: 6px;
    }

    /* logo & judul grup */
    aside.sidebar .logo-text  { color: #ffffff; }
    aside.sidebar .menu-title { color: var(--sb-muted); }

    /* menu */
    aside.sidebar .menu a { color: var(--sb-text); }

    aside.sidebar .menu a svg { opacity: 0.8; }

    aside.sidebar .menu a:hover {
        background: var(--sb-hover);
        color: #ffffff;
    }

    aside.sidebar .menu a.active {
        background: var(--sb-active);
        color: #ffffff;
        font-weight: 600;
        box-shadow: inset 3px 0 0 var(--sb-accent);
    }

    aside.sidebar .menu a.active svg {
        stroke: var(--sb-accent);
        opacity: 1;
    }

    /* tombol mode malam */
    aside.sidebar .theme-toggle {
        border-color: var(--sb-line-strong);
        color: var(--sb-text);
    }

    aside.sidebar .theme-toggle:hover {
        background: var(--sb-hover);
        color: #ffffff;
    }

    /* footer: avatar, nama, logout */
    aside.sidebar .sidebar-footer { border-top-color: var(--sb-line); }

    aside.sidebar .sidebar-avatar {
        background: var(--sb-accent);
        color: #0b1f3a;
        font-weight: 700;
    }

    aside.sidebar .sidebar-user       { color: #ffffff; }
    aside.sidebar .sidebar-user small { color: var(--sb-muted); }

    aside.sidebar .sidebar-logout { color: var(--sb-muted); }

    aside.sidebar .sidebar-logout:hover {
        background: rgba(255, 90, 90, 0.16);
        color: #ff8a8a;
    }
</style>