<?php

/**
 * Stripe module routes (api/v1).
 * Loaded only when MODULE_STRIPE=true (disabled by default).
 * Depends on: payments module.
 *
 * This is separate from the payments module because Stripe requires
 * API keys and is not needed for all deployments.
 */

// Stripe-specific routes are handled by the payments module.
// This file exists as a flag — when MODULE_STRIPE is enabled,
// the PaymentController uses real Stripe SDK calls instead of stubs.
