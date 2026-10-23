<?php
echo '<h3>Pemeriksaan Ekstensi PHP</h3>';

echo 'ZIP: ' .
    (extension_loaded('zip') ? 'AKTIF' : 'TIDAK AKTIF');

echo '<br>DOM: ' .
    (class_exists('DOMDocument') ? 'AKTIF' : 'TIDAK AKTIF');

echo '<br>XML: ' .
    (extension_loaded('xml') ? 'AKTIF' : 'TIDAK AKTIF');
?>