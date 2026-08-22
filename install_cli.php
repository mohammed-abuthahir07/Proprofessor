<?php
// CLI installer wrapper
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = ['gemini_key' => $argv[1] ?? ''];
include __DIR__ . '/install.php';
