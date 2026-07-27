<?php
// Test token hashing
$raw_token = "24ca92001bb13e93b511ece2e2418894cc148925d3126701137496cfb3b292aa";
$hashed = hash('sha256', $raw_token);

echo json_encode([
    'raw_token' => $raw_token,
    'sha256_hash' => $hashed,
    'db_hash' => '87bce7917040b87ff6a6101676d8f8c545de04df262ebf3089d04e1aa45a2be3',
    'match' => ($hashed === '87bce7917040b87ff6a6101676d8f8c545de04df262ebf3089d04e1aa45a2be3') ? 'YES' : 'NO'
], JSON_PRETTY_PRINT);
?>
