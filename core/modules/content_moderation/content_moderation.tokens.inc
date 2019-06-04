<?php

/**
 * @file
 * Tokens for the content moderation module.
 */

use Drupal\content_moderation\EntityTokens;
use Drupal\Core\Render\BubbleableMetadata;

/**
 * Implements hook_token_info().
 */
function content_moderation_token_info() {
  return \Drupal::service('class_resolver')
    ->getInstanceFromDefinition(EntityTokens::class)
    ->generateEntityTokenInfo();
}

/**
 * Implements hook_tokens().
 */
function content_moderation_tokens($type, $tokens, array $data, array $options, BubbleableMetadata $bubbleable_metadata) {
  return \Drupal::service('class_resolver')
    ->getInstanceFromDefinition(EntityTokens::class)
    ->getTokens($type, $tokens, $data, $options, $bubbleable_metadata);
}
