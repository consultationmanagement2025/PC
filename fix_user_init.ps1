# Fix user initialization in system-template-full.php

$filePath = 'c:\xampp\htdocs\CAP101\PC\system-template-full.php'
$content = Get-Content $filePath -Raw

$oldCode = 'window.__SESSION_LOGGED_IN__ = true;'
$newCode = @'
window.__SESSION_LOGGED_IN__ = true;
        
        // Initialize current user data for JavaScript role checks
        window.__CURRENT_USER__ = {
            id: <?php echo json_encode($_SESSION['user_id'] ?? null); ?>,
            name: <?php echo json_encode($_SESSION['fullname'] ?? 'User'); ?>,
            email: <?php echo json_encode($_SESSION['email'] ?? ''); ?>,
            role: <?php echo json_encode($_SESSION['role'] ?? 'citizen'); ?>
        };
        
        // Initialize role flags for JavaScript access control
        window.__IS_ADMIN__ = <?php echo json_encode($is_admin); ?>;
        window.__IS_SUPER_ADMIN__ = <?php echo json_encode($is_super_admin); ?>;
        window.__IS_BARANGAY_STAFF__ = <?php echo json_encode($is_barangay_staff); ?>;
'@

$newContent = $content -replace [regex]::Escape($oldCode), $newCode
$newContent | Set-Content $filePath -Encoding UTF8
Write-Output 'Replaced successfully'
