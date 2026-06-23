<?php
// ============================================================
//  ai_helper.php — Shared AI utilities for StationaryPlus
//  Include this file on any page that calls the Anthropic API
// ============================================================
 
// ── Pricing config (RM per page, adjust to match your shop) ──
const PRICE_PER_PAGE = [
    'color' => ['A3' => 1.00, 'A4' => 0.50, 'A5' => 0.35],
    'bw'    => ['A3' => 0.20, 'A4' => 0.10, 'A5' => 0.07],
];
 
const BINDING_PRICE = [
    'NONE'   => 0.00,
    'STAPLE' => 0.50,
    'SPIRAL' => 3.00,
];
 
// Minutes per page to print (rough estimate)
const DURATION_PER_PAGE = [
    'color' => 2.0,
    'bw'    => 0.5,
];
 
 
// ── Core Claude API caller (text prompts only) ────────────────
function callClaude(string $prompt, int $maxTokens = 500): string {
    return callClaudeWithMessages(
        [['role' => 'user', 'content' => $prompt]],
        $maxTokens
    );
}
 
 
// ── Claude API caller (full messages array — supports vision) ─
function callClaudeWithMessages(array $messages, int $maxTokens = 1000): string {
    $apiKey = getenv('ANTHROPIC_API_KEY');
 
    if (!$apiKey) {
        error_log('ANTHROPIC_API_KEY not set in environment.');
        return '';
    }
 
    $payload = json_encode([
        'model'      => 'claude-sonnet-4-6',
        'max_tokens' => $maxTokens,
        'messages'   => $messages,
    ]);
 
    $ch = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'x-api-key: '           . $apiKey,
            'anthropic-version: 2023-06-01',
        ],
        CURLOPT_TIMEOUT        => 90,   // vision calls can be slow for large PDFs
    ]);
 
    $raw   = curl_exec($ch);
    $errno = curl_errno($ch);
    curl_close($ch);
 
    if ($errno) {
        error_log("Claude cURL error $errno");
        return '';
    }
 
    $data = json_decode($raw, true);
    return $data['content'][0]['text'] ?? '';
}
 
 
// ── Price helpers ─────────────────────────────────────────────
 
/**
 * Returns full price breakdown as an array.
 */
function getPriceBreakdown(
    int    $colorPages,
    int    $bwPages,
    string $size,
    string $binding,
    int    $copies
): array {
    // Fallback to A4 if unknown size
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
 
/**
 * Returns estimated print time in minutes.
 */
function estimateDuration(int $colorPages, int $bwPages, int $copies): float {
    $mins = ($colorPages * DURATION_PER_PAGE['color'])
          + ($bwPages    * DURATION_PER_PAGE['bw']);
    return round($mins * $copies, 1);
}