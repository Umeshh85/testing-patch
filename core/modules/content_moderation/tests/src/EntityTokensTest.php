<?php

namespace Drupal\Tests\content_moderation\Kernel;

use Drupal\Component\Utility\Html;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\entity_test_revlog\Entity\EntityTestWithRevisionLog;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use Drupal\Tests\node\Traits\NodeCreationTrait;
use Drupal\Tests\system\Kernel\Token\TokenReplaceKernelTest;

/**
 * Tests entity token replacement for content moderation.
 *
 * @group content_moderation
 */
class EntityTokensTest extends TokenReplaceKernelTest {

  use ContentTypeCreationTrait;
  use NodeCreationTrait;

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'content_moderation',
    'entity_test_revlog',
    'filter',
    'node',
    'workflows',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->installConfig(['filter', 'node']);
    $node_type = $this->createContentType(['type' => 'article']);
    node_add_body_field($node_type);

    $this->installEntitySchema('entity_test_revlog');
  }

  /**
   * Tests revision token replacement.
   */
  public function testNodeTokens() {
    // Node tokens.
    $author = $this->createUser();
    $node = $this->createNode([
      'type' => 'article',
      'uid' => $author->id(),
      'revision_log' => $this->randomString(),
      'revision_created' => '123456789',
    ]);

    $tests = [];
    $tests['[node:revision-created]'] = \Drupal::service('date.formatter')->format($node->getRevisionCreationTime(), 'medium');
    $tests['[node:revision-created:long]'] = \Drupal::service('date.formatter')->format($node->getRevisionCreationTime(), 'long');
    $tests['[node:revision-log]'] = Html::escape($node->getRevisionLogMessage());
    $tests['[node:revision-user]'] = $author->getDisplayName();
    $tests['[node:revision-user:uid]'] = $author->id();

    $base_bubbleable_metadata = BubbleableMetadata::createFromObject($node);
    $metadata_tests = [];
    $bubbleable_metadata = clone $base_bubbleable_metadata;
    $metadata_tests['[node:revision-created]'] = $bubbleable_metadata->addCacheTags(['rendered']);
    $metadata_tests['[node:revision-created:long]'] = $bubbleable_metadata;
    $metadata_tests['[node:revision-log]'] = $base_bubbleable_metadata;
    $bubbleable_metadata = clone $base_bubbleable_metadata;
    $metadata_tests['[node:revision-user]'] = $bubbleable_metadata->addCacheTags(['user:' . $author->id()]);
    $metadata_tests['[node:revision-user:uid]'] = $bubbleable_metadata;

    foreach ($tests as $input => $expected) {
      $bubbleable_metadata = new BubbleableMetadata();
      $output = $this->tokenService->replace($input, ['node' => $node], [], $bubbleable_metadata);
      $this->assertEquals($expected, $output, 'Token replacement did not match for ' . $input);
      $this->assertEquals($metadata_tests[$input], $bubbleable_metadata, 'Bubbleable metadata did not match for ' . $input);
    }

    // Change revision author.
    $revision_author = $this->createUser();
    $node->setRevisionUser($revision_author);
    $tests = [];
    $tests['[node:revision-user]'] = $revision_author->getDisplayName();
    $tests['[node:revision-user:uid]'] = $revision_author->id();

    $base_bubbleable_metadata = BubbleableMetadata::createFromObject($node);
    $metadata_tests = [];
    // @todo add created tokens.
    $bubbleable_metadata = clone $base_bubbleable_metadata;
    $metadata_tests['[node:revision-user]'] = $bubbleable_metadata->addCacheTags(['user:' . $revision_author->id()]);
    $metadata_tests['[node:revision-user:uid]'] = $bubbleable_metadata;

    foreach ($tests as $input => $expected) {
      $bubbleable_metadata = new BubbleableMetadata();
      $output = $this->tokenService->replace($input, ['node' => $node], [], $bubbleable_metadata);
      $this->assertEquals($expected, $output, 'Token replacement did not match for ' . $input);
      $this->assertEquals($metadata_tests[$input], $bubbleable_metadata, 'Bubbleable metadata did not match for ' . $input);
    }
  }

  /**
   * Tests non-node entity revision token replacement.
   */
  public function testEntityTestTokens() {
    $revision_user = $this->createUser();
    $entity_test = EntityTestWithRevisionLog::create([
      'type' => 'test',
    ]);
    $entity_test->setRevisionCreationTime('123456789');
    $entity_test->setRevisionLogMessage($this->randomString());
    $entity_test->setRevisionUser($revision_user);
    $entity_test->save();

    $tests = [];
    $tests['[entity_test_revlog:revision-created]'] = \Drupal::service('date.formatter')->format($entity_test->getRevisionCreationTime(), 'medium');
    $tests['[entity_test_revlog:revision-created:long]'] = \Drupal::service('date.formatter')->format($entity_test->getRevisionCreationTime(), 'long');
    $tests['[entity_test_revlog:revision-log]'] = Html::escape($entity_test->getRevisionLogMessage());
    $tests['[entity_test_revlog:revision-user]'] = $revision_user->getDisplayName();
    $tests['[entity_test_revlog:revision-user:uid]'] = $revision_user->id();

    $base_bubbleable_metadata = BubbleableMetadata::createFromObject($entity_test);
    $metadata_tests = [];
    $bubbleable_metadata = clone $base_bubbleable_metadata;
    $metadata_tests['[entity_test_revlog:revision-created]'] = $bubbleable_metadata->addCacheTags(['rendered']);
    $metadata_tests['[entity_test_revlog:revision-created:long]'] = $bubbleable_metadata;
    $metadata_tests['[entity_test_revlog:revision-log]'] = $base_bubbleable_metadata;
    $bubbleable_metadata = clone $base_bubbleable_metadata;
    $metadata_tests['[entity_test_revlog:revision-user]'] = $bubbleable_metadata->addCacheTags(['user:' . $revision_user->id()]);
    $metadata_tests['[entity_test_revlog:revision-user:uid]'] = $bubbleable_metadata;

    foreach ($tests as $input => $expected) {
      $bubbleable_metadata = new BubbleableMetadata();
      $output = $this->tokenService->replace($input, ['entity_test_revlog' => $entity_test], [], $bubbleable_metadata);
      $this->assertEquals($expected, $output, 'Token replacement failed for ' . $input);
      $this->assertEquals($metadata_tests[$input], $bubbleable_metadata, 'Bubbleable metadata did not match for ' . $input);
    }
  }

}
