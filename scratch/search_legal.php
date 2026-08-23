<?php
$files = glob(__DIR__ . '/../*.{php,js,html,json}', GLOB_BRACE);
$files = array_merge($files, glob(__DIR__ . '/../admin-side/*.{php,js,html,json}', GLOB_BRACE));
$files = array_merge($files, glob(__DIR__ . '/../admin-side/ASSETS/js/*.{php,js,html,json}', GLOB_BRACE));
$files = array_merge($files, glob(__DIR__ . '/../admin/*.{php,js,html,json}', GLOB_BRACE));
$files = array_merge($files, glob(__DIR__ . '/../admin/ASSETS/js/*.{php,js,html,json}', GLOB_BRACE));
$files = array_merge($files, glob(__DIR__ . '/../assets/js/*.{php,js,html,json}', GLOB_BRACE));

foreach ($files as $f) {
    if (!is_file($f)) continue;
    $c = file_get_contents($f);
    if (stripos($c, 'Legal Assistance') !== false || stripos($c, 'Community Legal') !== false) {
        echo "Found in $f\n";
    }
}
