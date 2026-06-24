<?php
// ============================================================
//  ai_helper.php — Shared AI utilities (Google Gemini API)
//  Include this on any page that needs AI features.
//  Requires config.php to be included first.
// ============================================================
 
// ── Pricing config (RM per page — adjust to your shop rates) ──
const PRICE_PER_PAGE = [
    'color' => ['A0' => 4.00, 'A1' => 2.50, 'A2' => 1.50, 'A3' => 1.00, 'A4' => 0.50, 'A5' => 0.35],
    'bw'    => ['A0' => 1.00, 'A1' => 0.60, 'A2' => 0.40, 'A3' => 0.20, 'A4' => 0.10, 'A5' => 0.07],
];
 
const BINDING_PRICE = [
    'NONE'   => 0.00,
    'STAPLE' => 0.50,
    'SPIRAL' => 3.00,
];
 
// Minutes to print per page (used for duration estimate)
const DURATION_PER_PAGE = [
    'color' => 2.0,
    'bw'    => 0.5,
];
 
 
// ── Text-only AI call ─────────────────────────────────────────
// Use this for report insights, recommendations, restock suggestions
function callAI(string $prompt, int $maxTokens = 500): string {
    return callGemini([
        'contents' => [[
            'parts' => [['text' => $prompt]]
        ]],
        'generationConfig' => ['maxOutputTokens' => $maxTokens],
    ]);
}
 
// Keep old name working so other files don't break
function callClaude(string $prompt, int $maxTokens = 500): string {
    return callAI($prompt, $maxTokens);
}
 
 
// ── Vision call — analyses a file (PDF or image) ──────────────
// Accepts the Anthropic-style messages array used in upload_print.php
// and converts it to Gemini format internally.
function callClaudeWithMessages(array $messages, int $maxTokens = 1000): string {
    // Convert Anthropic message format → Gemini parts format
    $parts = [];
 
    foreach ($messages as $msg) {
        $content = $msg['content'] ?? [];
 
        // content can be a plain string (text-only) or an array of blocks
        if (is_string($content)) {
            $parts[] = ['text' => $content];
            continue;
        }
 
        foreach ($content as $block) {
            switch ($block['type'] ?? '') {
                case 'text':
                    $parts[] = ['text' => $block['text']];
                    break;
 
                case 'document':   // PDF
                case 'image':      // JPG / PNG
                    $src = $block['source'] ?? [];
                    if (($src['type'] ?? '') === 'base64') {
                        $parts[] = [
                            'inline_data' => [
                                'mime_type' => $src['media_type'],
                                'data'      => $src['data'],
                            ],
                        ];
                    }
                    break;
            }
        }
    }
 
    return callGemini([
        'contents' => [['parts' => $parts]],
        'generationConfig' => ['maxOutputTokens' => $maxTokens],
    ]);
}
 
 
// ── Core Gemini API caller ────────────────────────────────────
function callGemini(array $payload): string {
    if (!defined('GEMINI_API_KEY') || GEMINI_API_KEY === '') {
        error_log('GEMINI_API_KEY is not configured. Edit config.php.');
        return json_encode(['error' => 'API key not configured — edit config.php']);
    }
 
    $model    = defined('GEMINI_MODEL') ? GEMINI_MODEL : 'gemini-1.5-flash';
    $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=" . GEMINI_API_KEY;
 
    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT        => 90,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
 
    $raw   = curl_exec($ch);
    $errno = curl_errno($ch);
    $error = curl_error($ch);
    curl_close($ch);
 
    if ($errno) {
        error_log("Gemini cURL error $errno: $error");
        return json_encode(['error' => "Network error: $error"]);
    }
 
    $data = json_decode($raw, true);
 
    // Surface API errors (bad key, quota, blocked content, etc.)
    if (isset($data['error'])) {
        $msg = $data['error']['message'] ?? 'Gemini API error';
        error_log("Gemini API error: $msg");
        return json_encode(['error' => $msg]);
    }
 
    // Extract the text response
    return $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
}
 
 
// ── Price helpers ─────────────────────────────────────────────
 
function getPriceBreakdown(
    int    $colorPages,
    int    $bwPages,
    string $size,
    string $binding,
    int    $copies
): array {
    $size = array_key_exists($size, PRICE_PER_PAGE['color']) ? $size : 'A4';
 
    $colorRate   = PRICE_PER_PAGE['color'][$size];
    $bwRate      = PRICE_PER_PAGE['bw'][$size];
    $bindingCost = BINDING_PRICE[$binding] ?? 0.00;
 
    $colorCost = round($colorPages * $colorRate * $copies, 2);
    $bwCost    = round($bwPages    * $bwRate    * $copies, 2);
    $total     = round($colorCost + $bwCost + $bindingCost, 2);
 
    return [
        'color_pages'  => $colorPages,
        'bw_pages'     => $bwPages,
        'copies'       => $copies,
        'paper_size'   => $size,
        'binding'      => $binding,
        'color_rate'   => $colorRate,
        'bw_rate'      => $bwRate,
        'color_cost'   => $colorCost,
        'bw_cost'      => $bwCost,
        'binding_cost' => $bindingCost,
        'total'        => $total,
    ];
}
 
 
function estimateDuration(int $colorPages, int $bwPages, int $copies): float {
    $mins = ($colorPages * DURATION_PER_PAGE['color'])
          + ($bwPages    * DURATION_PER_PAGE['bw']);
    return round($mins * $copies, 1);
}
 