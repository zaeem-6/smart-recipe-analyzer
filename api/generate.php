<?php

declare(strict_types=1);

const OPENROUTER_URL = 'https://openrouter.ai/api/v1/chat/completions';

const DEFAULT_OPENROUTER_MODEL = 'deepseek/deepseek-v4-flash-0731:free';
const REQUEST_TIMEOUT_SECONDS = 45;

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Methods: POST, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

function respond(int $statusCode, array $payload): never
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

function loadEnvFile(string $path): void
{
    if (!is_readable($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }

        [$name, $value] = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        $value = trim($value, "\\\"'");

        if ($name !== '' && getenv($name) === false) {
            putenv($name . '=' . $value);
        }
    }
}

function cleanModelJson(string $content): string
{
    $content = trim($content);
    $content = preg_replace('/^```(?:json)?\\s*/i', '', $content) ?? $content;
    $content = preg_replace('/\\s*```$/', '', $content) ?? $content;
    return trim($content);
}

loadEnvFile(dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env');

$model = getenv('OPENROUTER_MODEL');
$model = $model === false || trim($model) === '' ? DEFAULT_OPENROUTER_MODEL : trim($model);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(405, ['success' => false, 'error' => 'Method not allowed.']);
}

$rawBody = file_get_contents('php://input');
$body = is_string($rawBody) ? json_decode($rawBody, true) : null;
$ingredients = is_array($body) && isset($body['ingredients']) && is_string($body['ingredients'])
    ? trim($body['ingredients'])
    : '';

if ($ingredients === '') {
    respond(400, ['success' => false, 'error' => 'Please enter at least one ingredient.']);
}

$apiKey = getenv('OPENROUTER_API_KEY');
if ($apiKey === false || trim($apiKey) === '') {
    respond(500, ['success' => false, 'error' => 'The recipe service is not configured yet.']);
}

$systemPrompt = <<<'PROMPT'
You are a practical recipe assistant. Generate 2 or 3 recipe suggestions using mainly the ingredients supplied by the user. You may assume common pantry staples, but keep the recipes realistic and clearly list all ingredients. Include cooking time, a difficulty of exactly Easy, Medium, or Hard, and basic nutrition per recipe with calories as a number and protein and carbs as strings. If the input does not contain any real, recognizable food ingredients, return valid JSON with an empty "recipes" array instead of inventing recipes.

Return ONLY raw JSON with no markdown fences or commentary, matching this exact shape:
{
  "recipes": [
    {
      "name": "string",
      "ingredients": ["string"],
      "instructions": ["string"],
      "cookingTime": "string",
      "difficulty": "Easy|Medium|Hard",
      "nutrition": { "calories": 0, "protein": "string", "carbs": "string" }
    }
  ]
}
PROMPT;

$userPrompt = "Create recipes from these ingredients: {$ingredients}";
$requestBody = json_encode([
    'model' => $model,
    'temperature' => 0.3,
    'max_tokens' => 2000,
    'messages' => [
        ['role' => 'system', 'content' => $systemPrompt],
        ['role' => 'user', 'content' => $userPrompt],
    ],
], JSON_UNESCAPED_SLASHES);

$curl = curl_init(OPENROUTER_URL);
if ($curl === false) {
    respond(502, ['success' => false, 'error' => 'The AI service is busy, please try again in a moment.']);
}

curl_setopt_array($curl, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $requestBody,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . trim($apiKey),
        'Content-Type: application/json',
    ],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_TIMEOUT => REQUEST_TIMEOUT_SECONDS,
]);

$responseBody = curl_exec($curl);
$curlError = curl_error($curl);
$statusCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
curl_close($curl);

if ($responseBody === false || $curlError !== '') {
    respond(502, ['success' => false, 'error' => 'The AI service is busy, please try again in a moment.']);
}

$openRouterResponse = json_decode($responseBody, true);
if ($statusCode < 200 || $statusCode >= 300) {
    $errorMessage = is_array($openRouterResponse) ? ($openRouterResponse['error']['message'] ?? '') : '';
    $friendlyMessage = ($statusCode === 429 || $statusCode >= 500 || $errorMessage !== '')
        ? 'The AI service is busy, please try again in a moment.'
        : 'Couldn\'t generate recipes right now, please try again.';
    respond(502, ['success' => false, 'error' => $friendlyMessage]);
}

$content = is_array($openRouterResponse)
    ? ($openRouterResponse['choices'][0]['message']['content'] ?? null)
    : null;
if (!is_string($content) || trim($content) === '') {
    respond(502, ['success' => false, 'error' => "Couldn't generate recipes right now, please try again."]);
}

$decodedContent = json_decode(cleanModelJson($content), true);
if (!is_array($decodedContent) || !isset($decodedContent['recipes']) || !is_array($decodedContent['recipes'])) {
    respond(502, ['success' => false, 'error' => "Couldn't generate recipes right now, please try again."]);
}

respond(200, ['success' => true, 'recipes' => $decodedContent['recipes']]);
