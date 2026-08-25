<?php
require __DIR__ . '/includes/bootstrap.php';
$dirty = "Unit 1 \x80\xFF Programming";
$clean = CoursePlanTools::sanitizeExtractedText($dirty);
$j = json_encode(['ok' => true, 'text' => $clean], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
echo ($j !== false ? 'OK json' : 'FAIL') . ' len=' . strlen($clean) . PHP_EOL;
echo substr($j, 0, 120) . PHP_EOL;
