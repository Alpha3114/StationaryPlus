<?php
// ============================================================
//  pricing.php — Shared price/discount calculation
//  Usage: require_once 'pricing.php';
// ============================================================

/**
 * Applies a product's discount_percent to its base price. Used everywhere
 * a product's charged price is calculated (customer catalog, POS) so the
 * price shown to a customer always matches the price actually charged.
 */
function discounted_price(float $price, float $discountPercent): float {
    return round($price * (1 - $discountPercent / 100), 2);
}
