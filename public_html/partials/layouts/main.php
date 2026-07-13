<?php
/**
 * Main layout: header + content + footer.
 * Expects $content (rendered page body) to be defined.
 */
$pageTitle = $pageTitle ?? 'Matrimony';
require __DIR__ . '/../header.php';
echo $content;
require __DIR__ . '/../footer.php';
