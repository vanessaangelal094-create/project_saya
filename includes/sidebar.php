<?php
/*
|--------------------------------------------------------------------------
| SIDEBAR BERSAMA
|--------------------------------------------------------------------------
| Cara pakai di tiap halaman, SEBELUM include ini:
|
|   $base   = '';        // index.php (root)
|   $base   = '../';      // equipment/, fuel/, lubricant/, maintenance/, dst
|   $base   = '../../';   // maintenance/history/
|
|   $active = 'dashboard'; // lihat daftar key di bawah
|
|   include $base . 'includes/sidebar.php';
*/

$base   = $base   ?? '';
$active = $active ?? '';

$icons = [
    'dashboard'   => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>',
    'equipment'   => '<path d="M3 17h13M5 17V9l4-3h5l3 4v7"/><circle cx="7" cy="19" r="2"/><circle cx="14" cy="19" r="2"/><path d="M17 10h4l-1 7"/>',
    'status'      => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
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
];

/* key => [label, link, ikon] */
$menu_groups = [
    'Menu Utama' => [
        'dashboard' => ['Dashboard',            'index.php',                       'dashboard'],
        'equipment' => ['Equipment Master',     'equipment/index.php',             'equipment'],
        'status'    => ['Status Unit',          'equipment/index.php',             'status'],
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

function sidebar_icon($icons, $key)
{
    return '<svg viewBox="0 0 24 24" aria-hidden="true">' . ($icons[$key] ?? '') . '</svg>';
}
?>
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