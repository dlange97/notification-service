<?php

declare(strict_types=1);

$coverageFile = $argv[1] ?? __DIR__ . '/../test-output/coverage.xml';
$thresholdRaw = getenv('MIN_COVERAGE');
$threshold = is_numeric($thresholdRaw) ? (float) $thresholdRaw : 70.0;

if (!is_file($coverageFile)) {
    fwrite(STDERR, "Coverage file not found: {$coverageFile}\n");
    exit(1);
}

$xml = @simplexml_load_file($coverageFile);
if ($xml === false || !isset($xml->project->metrics)) {
    fwrite(STDERR, "Invalid Clover coverage file: {$coverageFile}\n");
    exit(1);
}

$metrics = $xml->project->metrics;
$statements = (int) ($metrics['statements'] ?? 0);
$coveredStatements = (int) ($metrics['coveredstatements'] ?? 0);

if ($statements <= 0) {
    fwrite(STDERR, "Coverage check failed: no statements found in report.\n");
    exit(1);
}

$coverage = ($coveredStatements / $statements) * 100;
printf("Line coverage: %.2f%% (threshold: %.2f%%)\n", $coverage, $threshold);

if ($coverage + 0.0001 < $threshold) {
    fwrite(STDERR, "Coverage threshold not met.\n");
    exit(1);
}
