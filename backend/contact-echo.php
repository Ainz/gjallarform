<?php
header('Content-Type: application/json; charset=utf-8');
echo json_encode([
  'origin' => $_SERVER['HTTP_ORIGIN'] ?? '(none)',
  'content_type' => $_SERVER['CONTENT_TYPE'] ?? '(none)',
  'post' => $_POST,
], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);


