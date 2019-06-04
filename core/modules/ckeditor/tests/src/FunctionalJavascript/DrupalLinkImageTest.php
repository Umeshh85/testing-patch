<?php

namespace Drupal\Tests\ckeditor\FunctionalJavascript;

use Drupal\editor\Entity\Editor;
use Drupal\filter\Entity\FilterFormat;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\TestFileCreationTrait;

/**
 * Tests the Integration of the DrupalImage and DrupalLink plugins in CKEditor.
 *
 * @group ckeditor
 */
class DrupalLinkImageTest extends WebDriverTestBase {

  use TestFileCreationTrait;

  /**
   * The account.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $account;

  /**
   * {@inheritdoc}
   */
  public static $modules = ['node', 'editor', 'ckeditor', 'filter'];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    // Create a text format and associate CKEditor.
    $basic_html_format = FilterFormat::create([
      'format' => 'basic_html',
      'name' => 'Basic HTML',
      'weight' => 0,
    ]);
    $basic_html_format->save();

    // Create the editor
    $editor = Editor::create([
      'format' => 'basic_html',
      'editor' => 'ckeditor',
      'image_upload' => [
        'status' => TRUE,
        'scheme' => 'public',
        'directory' => 'inline-images',
        'max_size' => '',
        'max_dimensions' => [
          'width' => NULL,
          'height' => NULL,
        ],
      ],
    ]);
    $editor->save();

    // Create a node type for testing.
    $type = NodeType::create(['type' => 'page', 'name' => 'page']);
    $type->save();
    node_add_body_field($type);

    $this->account = $this->drupalCreateUser([
      'administer nodes',
      'create page content',
      'use text format basic_html',
    ]);
    $this->drupalLogin($this->account);
  }

  /**
   * Tests that a link can be applied to an inline uploaded image.
   */
  public function testCreateLinkOnImage() {
    $session = $this->getSession();
    $page = $session->getPage();
    $web_assert = $this->assertSession();

    // Find a test image to upload.
    $valid_images = [];
    foreach ($this->getTestFiles('image') as $image) {
      $regex = '/\.(' . preg_replace('/ +/', '|', 'png') . ')$/i';
      if (preg_match($regex, $image->filename)) {
        $valid_images[] = $image;
      }
    }

    // Ensure we have at least one valid image.
    $this->assertGreaterThanOrEqual(1, count($valid_images));

    // Go to a node creation page.
    $this->drupalGet('node/add/page');
    // Add the Title
    $page->fillField('edit-title-0-value', 'Sample Title');

    // Make sure any ckeditor ajax has finished.
    $web_assert->assertWaitOnAjaxRequest();

    // Press the DrupalImage button.
    $this->click('.cke_button__drupalimage');
    $page->waitFor(5, function () use ($page) {
      return $page->find('css', '.editor-image-dialog');
    });

    // Attach the file and alt tag.
    $file_system = $this->container->get('file_system');
    $image_path = $file_system->realpath($valid_images[0]->uri);
    $page->attachFileToField('files[fid]', $image_path);
    $web_assert->assertWaitOnAjaxRequest();
    $page->fillField('attributes[alt]', 'Alt text');
    $this->click('.ui-dialog-buttonset .form-submit');

    // Make sure any ajax has finished saving the file.
    $web_assert->assertWaitOnAjaxRequest();

    // Press the Link button to wrap the image in a link
    $this->click('.cke_button__drupallink');
    $page->waitFor(5, function () use ($page) {
      return $page->find('css', '.editor-link-dialog');
    });
    $link = 'https://drupal.org';
    $page->fillField('attributes[href]', $link);
    $this->click('.ui-dialog-buttonset .form-submit');

    // Make sure any ajax is finished
    $web_assert->assertWaitOnAjaxRequest();

    $page->pressButton('Save');

    $web_assert->pageTextContains('Sample Title');
    $web_assert->elementContains('css', 'a[href="' . $link . '"]', $valid_images[0]->filename);
  }

}
