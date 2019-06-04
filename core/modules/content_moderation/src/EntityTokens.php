<?php

namespace Drupal\content_moderation;

use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Datetime\Entity\DateFormat;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\RevisionLogInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Utility\Token;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class for handling entity tokens.
 *
 * @internal
 */
class EntityTokens implements ContainerInjectionInterface {

  use StringTranslationTrait;

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * The Entity Type Manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The token service.
   *
   * @var \Drupal\Core\Utility\Token
   */
  protected $token;

  /**
   * Constructs a new entity token object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\Core\Utility\Token $token
   *   The token service.
   */
  public function __construct(DateFormatterInterface $date_formatter, EntityTypeManagerInterface $entity_type_manager, Token $token) {
    $this->dateFormatter = $date_formatter;
    $this->entityTypeManager = $entity_type_manager;
    $this->token = $token;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('date.formatter'),
      $container->get('entity_type.manager'),
      $container->get('token')
    );
  }

  /**
   * Generates entity token info.
   *
   * @see \content_moderation_token_info()
   */
  public function generateEntityTokenInfo() {
    $tokens = [];
    foreach ($this->entityTypeManager->getDefinitions() as $entity_type) {
      if ($entity_type->entityClassImplements(RevisionLogInterface::class)) {
        $tokens[$entity_type->id()]['revision-created'] = [
          'name' => $this->t('Revision created time'),
          'description' => $this->t('The time the latest revision was created.'),
          'type' => 'date',
        ];
        $tokens[$entity_type->id()]['revision-log'] = [
          'name' => $this->t('Revision log'),
          'description' => $this->t('Log message for the most recent changes.'),
        ];
        $tokens[$entity_type->id()]['revision-user'] = [
          'name' => $this->t('Revision user'),
          'description' => $this->t('The revision author.'),
          'type' => 'user',
        ];
      }
    }

    return [
      'types' => [],
      'tokens' => $tokens,
    ];
  }

  /**
   * Generate tokens for the given types.
   *
   * @param $type
   *   The machine-readable name of the type (group) of token being replaced, such
   *   as 'node', 'user', or another type defined by a hook_token_info()
   *   implementation.
   * @param $tokens
   *   An array of tokens to be replaced. The keys are the machine-readable token
   *   names, and the values are the raw [type:token] strings that appeared in the
   *   original text.
   * @param array $data
   *   An associative array of data objects to be used when generating replacement
   *   values, as supplied in the $data parameter to
   *   \Drupal\Core\Utility\Token::replace().
   * @param array $options
   *   An associative array of options for token replacement; see
   *   \Drupal\Core\Utility\Token::replace() for possible values.
   * @param \Drupal\Core\Render\BubbleableMetadata $bubbleable_metadata
   *   Bubbleable metadata.
   *
   * @return array
   *   An associative array of replacement values, keyed by the raw [type:token]
   *   strings from the original text. The returned values must be either plain
   *   text strings, or an object implementing MarkupInterface if they are
   *   HTML-formatted.
   *
   * @see content_moderation_tokens()
   */
  public function getTokens($type, $tokens, array $data, array $options, BubbleableMetadata $bubbleable_metadata) {
    $replacements = [];
    if (isset($data[$type]) && $data[$type] instanceof RevisionLogInterface) {
      /** @var \Drupal\Core\Entity\RevisionLogInterface $entity */
      $entity = $data[$type];
      foreach ($tokens as $name => $original) {
        switch ($name) {
          case 'revision-created':
            $date_format = DateFormat::load('medium');
            $bubbleable_metadata->addCacheableDependency($date_format);
            $replacements[$original] = $this->dateFormatter->format($entity->getRevisionCreationTime(), 'medium');
            break;

          case 'revision-log':
            $replacements[$original] = $entity->getRevisionLogMessage();
            break;

          case 'revision-user':
            // Chained tokens below.
            $author = $entity->getRevisionUser();
            $replacements[$original] = $author->getDisplayName();
            $bubbleable_metadata->addCacheableDependency($author);
            break;
        }
      }

      // Chained tokens.
      if ($author_tokens = $this->token->findWithPrefix($tokens, 'revision-user')) {
        $replacements += $this->token->generate('user', $author_tokens, ['user' => $entity->getRevisionUser()], $options, $bubbleable_metadata);
      }
      if ($created_tokens = $this->token->findWithPrefix($tokens, 'revision-created')) {
        $replacements += $this->token->generate('date', $created_tokens, ['date' => $entity->getRevisionCreationTime()], $options, $bubbleable_metadata);
      }
    }

    return $replacements;
  }

}
