<?php
// api/ai_help.php
// Proxy seguro hacia OpenAI Responses API (no expongas la API key al front)
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['error' => 'Método no permitido']);
  exit;
}

$apiKey = getenv('OPENAI_API_KEY');
if (!$apiKey) {
  http_response_code(500);
  echo json_encode(['error' => 'Falta OPENAI_API_KEY en el servidor']);
  exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$userMsg   = trim($input['message'] ?? '');
$formState = $input['form'] ?? []; // estado del formulario para dar ayuda contextual

if ($userMsg === '') {
  http_response_code(400);
  echo json_encode(['error' => 'Mensaje vacío']);
  exit;
}

// Contexto de negocio (ajústalo a tus reglas reales)
$business_rules = <<<RULES
Eres el asistente de ayuda de CESISS para captura de equipos (CCTV y Alarma).
Objetivo: ayudar al usuario a completar el registro correctamente, con pasos claros y breves.
- Si el usuario no ubica el tipo de dispositivo, sugiere 2–3 opciones probables y di por qué.
- Valida y ejemplifica formatos: IP (v4), MAC (hex XX:XX:XX:XX:XX:XX), zona de alarma, etc.
- Respeta reglas de visibilidad: 
  * Switch y Alarma: no requieren IP/MAC (si el flujo así lo define).
  * Monitor: ocultar Switch, IP, MAC, No. Puerto, IDE, IDE Password.
- Si el equipo = cámara, recuerda relacionar con NVR/DVR cuando aplique.
- Da respuestas cortas, con bullets si conviene, y un “Siguiente paso” concreto.
- Si faltan datos, pregunta 1 sola cosa a la vez.
RULES;

$condensedForm = json_encode([
  'equipo'        => $formState['equipo'] ?? null,
  'marca'         => $formState['marca'] ?? null,
  'modelo'        => $formState['modelo'] ?? null,
  'ip'            => $formState['ip'] ?? null,
  'mac'           => $formState['mac'] ?? null,
  'zona_alarma'   => $formState['zona_alarma'] ?? null,
  'tipo_switch'   => $formState['tipo_switch'] ?? null,
  'tipo_cctv'     => $formState['tipo_cctv'] ?? null,
  'tipo_alarma'   => $formState['tipo_alarma'] ?? null,
], JSON_UNESCAPED_UNICODE);

// Construimos el prompt con instrucciones + estado del formulario
$system = $business_rules . "\n\nEstado actual del formulario (JSON):\n" . $condensedForm;

$payload = [
  // Model sugerido rápido y costo-eficiente para ayuda interactiva
  "model" => "gpt-4o-mini", 
  "input" => [
    ["role" => "system", "content" => $system],
    ["role" => "user",   "content" => $userMsg],
  ],
  // Puedes activar streaming en el futuro; por simplicidad respondemos normal
];

$ch = curl_init("https://api.openai.com/v1/responses");
curl_setopt_array($ch, [
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_POST           => true,
  CURLOPT_HTTPHEADER     => [
    "Authorization: Bearer {$apiKey}",
    "Content-Type: application/json",
  ],
  CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
  CURLOPT_TIMEOUT        => 25,
]);

$res = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err = curl_error($ch);
curl_close($ch);

if ($err) {
  http_response_code(500);
  echo json_encode(['error' => 'cURL error', 'detail' => $err]);
  exit;
}

if ($httpCode < 200 || $httpCode >= 300) {
  http_response_code($httpCode);
  echo $res ?: json_encode(['error' => 'Error OpenAI', 'status' => $httpCode]);
  exit;
}

// La Responses API devuelve un objeto; buscamos el texto final
$data = json_decode($res, true);
$text = $data['output'][0]['content'][0]['text'] ?? ($data['content'][0]['text'] ?? null);

// Estructuramos salida
echo json_encode([
  'reply' => $text ?: '[Sin texto de respuesta]',
]);
