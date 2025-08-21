<?php
// /api/validate/index.php (on FRONTEND app)
header('Content-Type: application/json; charset=utf-8');

// CORS (same-origin calls usually don’t need this, but it doesn't hurt)
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin) header('Access-Control-Allow-Origin: ' . $origin);
header('Vary: Origin');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  header('Access-Control-Allow-Methods: POST, OPTIONS');
  header('Access-Control-Allow-Headers: Content-Type, Accept-Language, X-Edge-Ts, X-Edge-Sig');
  http_response_code(204); exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405); echo '{"valid":false,"message":"Method not allowed"}'; exit;
}

$workerUrl = getenv('WORKER_URL');        // e.g. https://<your-worker>.<sub>.workers.dev
$shared    = getenv('EDGE_SHARED_SECRET'); // optional HMAC hardening
if (!$workerUrl) {
  http_response_code(500); echo '{"valid":false,"message":"Proxy not configured"}'; exit;
}

$raw = file_get_contents('php://input');

// --- Optional: sign a short-lived header so only your proxy can call the Worker
$ts  = (string) time();
$sig = '';
if ($shared) {
  $sig = rtrim(strtr(base64_encode(hash_hmac('sha256', $ts, $shared, true)), '+/', '-_'), '=');
}

$ch = curl_init($workerUrl);
curl_setopt_array($ch, [
  CURLOPT_POST           => true,
  CURLOPT_HTTPHEADER     => array_filter([
    'Content-Type: application/json',
    'Accept-Language: ' . ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? 'en'),
    'User-Agent: ' . ($_SERVER['HTTP_USER_AGENT'] ?? 'railway-proxy'),
    'X-Proxy-From: railway',
    $shared ? "X-Edge-Ts: $ts" : null,
    $shared ? "X-Edge-Sig: $sig" : null,
  ]),
  CURLOPT_POSTFIELDS     => $raw,
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_HEADER         => false,
  CURLOPT_TIMEOUT        => 12,
]);
$resp = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE) ?: 502;
curl_close($ch);

http_response_code($code);
echo $resp ?: '{"valid":false,"message":"Upstream unavailable"}';
