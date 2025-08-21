<?php
// /api/validate/index.php (FRONTEND app on Railway)
header('Content-Type: application/json; charset=utf-8');

// CORS (safe even for same-origin)
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin) header('Access-Control-Allow-Origin: ' . $origin);
header('Vary: Origin');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
  header('Access-Control-Allow-Methods: POST, OPTIONS');
  header('Access-Control-Allow-Headers: Content-Type, Accept-Language, X-Edge-Ts, X-Edge-Sig');
  http_response_code(204); exit;
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  http_response_code(405); echo '{"valid":false,"message":"Method not allowed"}'; exit;
}

$workerUrl = getenv('WORKER_URL');          // e.g. https://<your-worker>.workers.dev
$shared    = getenv('EDGE_SHARED_SECRET');  // same as Worker secret
if (!$workerUrl) {
  http_response_code(500); echo '{"valid":false,"message":"Proxy not configured"}'; exit;
}

$raw = file_get_contents('php://input');
if ($raw === false) { $raw = ''; }

// Short-lived signature (no UX impact)
$ts  = (string) time();
$sig = '';
if ($shared) {
  $sig = rtrim(strtr(base64_encode(hash_hmac('sha256', $ts, $shared, true)), '+/', '-_'), '=');
}

// Forward client hints (optional)
$acceptLang = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? 'en';
$userAgent  = $_SERVER['HTTP_USER_AGENT'] ?? 'railway-proxy';
$clientIp   = $_SERVER['REMOTE_ADDR'] ?? '';

// Build headers
$headers = array_filter([
  'Content-Type: application/json',
  'Accept-Language: ' . $acceptLang,
  'User-Agent: ' . $userAgent,
  'X-Proxy-From: railway',
  $clientIp ? 'X-Forwarded-For: ' . $clientIp : null,
  $shared   ? "X-Edge-Ts: $ts"              : null,
  $shared   ? "X-Edge-Sig: $sig"            : null,
]);

$ch = curl_init($workerUrl);
curl_setopt_array($ch, [
  CURLOPT_POST            => true,
  CURLOPT_HTTPHEADER      => $headers,
  CURLOPT_POSTFIELDS      => $raw,
  CURLOPT_RETURNTRANSFER  => true,
  CURLOPT_HEADER          => false,
  CURLOPT_CONNECTTIMEOUT  => 5,
  CURLOPT_TIMEOUT         => 12,
  CURLOPT_FOLLOWLOCATION  => false,
  // CURLOPT_SSL_VERIFYPEER => true, // default true; ensure CA bundle exists
]);

$resp = curl_exec($ch);
$errno = curl_errno($ch);
$error = curl_error($ch);
$code  = curl_getinfo($ch, CURLINFO_HTTP_CODE) ?: 502;
curl_close($ch);

// Relay Worker response (or a useful error JSON)
if ($errno !== 0 || $resp === false) {
  http_response_code(502);
  echo json_encode([
    'valid'   => false,
    'message' => 'Upstream unavailable',
    'detail'  => $error ?: 'cURL error '.$errno
  ]);
  exit;
}

http_response_code($code);
echo $resp;
