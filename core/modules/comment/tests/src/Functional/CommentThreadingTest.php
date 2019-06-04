<?php

namespace Drupal\Tests\comment\Functional;

use Drupal\comment\CommentManagerInterface;

/**
 * Tests to make sure the comment number increments properly.
 *
 * @group comment
 */
class CommentThreadingTest extends CommentTestBase {

  /**
   * Tests the comment threading.
   */
  public function testCommentThreading() {
    // Set comments to have a subject with preview disabled.
    $this->drupalLogin($this->adminUser);
    $this->setCommentPreview(DRUPAL_DISABLED);
    $this->setCommentForm(TRUE);
    $this->setCommentSubject(TRUE);
    $this->setCommentSettings('default_mode', CommentManagerInterface::COMMENT_MODE_THREADED, 'Comment paging changed.');
    $this->drupalLogout();

    // Create a node.
    $this->drupalLogin($this->webUser);
    $this->node = $this->drupalCreateNode(['type' => 'article', 'promote' => 1, 'uid' => $this->webUser->id()]);

    // Post comment #1.
    $this->drupalLogin($this->webUser);
    $subject_text = $this->randomMachineName();
    $comment_text = $this->randomMachineName();

    $comment1 = $this->postComment($this->node, $comment_text, $subject_text, TRUE);
    // Confirm that the comment was created and has the correct threading.
    $this->assertTrue($this->commentExists($comment1), 'Comment #1. Comment found.');
    $this->assertEqual($comment1->getThread(), '01/');
    // Confirm that there is no reference to a parent comment.
    $this->assertNoParentLink($comment1->id());

    // Post comment #2 following the comment #1 to test if it correctly jumps
    // out the indentation in case there is a thread above.
    $subject_text = $this->randomMachineName();
    $comment_text = $this->randomMachineName();
    $this->postComment($this->node, $comment_text, $subject_text, TRUE);

    // Reply to comment #1 creating comment #1_3.
    $this->drupalGet('comment/reply/node/' . $this->node->id() . '/comment/' . $comment1->id());
    $comment1_3 = $this->postComment(NULL, $this->randomMachineName(), '', TRUE);

    // Confirm that the comment was created and has the correct threading.
    $this->assertTrue($this->commentExists($comment1_3, TRUE), 'Comment #1_3. Reply found.');
    $this->assertEqual($comment1_3->getThread(), '01.00/');
    // Confirm that there is a link to the parent comment.
    $this->assertParentLink($comment1_3->id(), $comment1->id());

    // Reply to comment #1_3 creating comment #1_3_4.
    $this->drupalGet('comment/reply/node/' . $this->node->id() . '/comment/' . $comment1_3->id());
    $comment1_3_4 = $this->postComment(NULL, $this->randomMachineName(), $this->randomMachineName(), TRUE);

    // Confirm that the comment was created and has the correct threading.
    $this->assertTrue($this->commentExists($comment1_3_4, TRUE), 'Comment #1_3_4. Second reply found.');
    $this->assertEqual($comment1_3_4->getThread(), '01.00.00/');
    // Confirm that there is a link to the parent comment.
    $this->assertParentLink($comment1_3_4->id(), $comment1_3->id());

    // Reply to comment #1 creating comment #1_5.
    $this->drupalGet('comment/reply/node/' . $this->node->id() . '/comment/' . $comment1->id());

    $comment1_5 = $this->postComment(NULL, $this->randomMachineName(), '', TRUE);

    // Confirm that the comment was created and has the correct threading.
    $this->assertTrue($this->commentExists($comment1_5), 'Comment #1_5. Third reply found.');
    $this->assertEqual($comment1_5->getThread(), '01.01/');
    // Confirm that there is a link to the parent comment.
    $this->assertParentLink($comment1_5->id(), $comment1->id());

    // Post comment #3 overall comment #5.
    $this->drupalLogin($this->webUser);
    $subject_text = $this->randomMachineName();
    $comment_text = $this->randomMachineName();

    $comment5 = $this->postComment($this->node, $comment_text, $subject_text, TRUE);
    // Confirm that the comment was created and has the correct threading.
    $this->assertTrue($this->commentExists($comment5), 'Comment #5. Second comment found.');
    $this->assertEqual($comment5->getThread(), '03/');
    // Confirm that there is no link to a parent comment.
    $this->assertNoParentLink($comment5->id());

    // Reply to comment #5 creating comment #5_6.
    $this->drupalGet('comment/reply/node/' . $this->node->id() . '/comment/' . $comment5->id());
    $comment5_6 = $this->postComment(NULL, $this->randomMachineName(), '', TRUE);

    // Confirm that the comment was created and has the correct threading.
    $this->assertTrue($this->commentExists($comment5_6, TRUE), 'Comment #6. Reply found.');
    $this->assertEqual($comment5_6->getThread(), '03.00/');
    // Confirm that there is a link to the parent comment.
    $this->assertParentLink($comment5_6->id(), $comment5->id());

    // Reply to comment #5_6 creating comment #5_6_7.
    $this->drupalGet('comment/reply/node/' . $this->node->id() . '/comment/' . $comment5_6->id());
    $comment5_6_7 = $this->postComment(NULL, $this->randomMachineName(), $this->randomMachineName(), TRUE);

    // Confirm that the comment was created and has the correct threading.
    $this->assertTrue($this->commentExists($comment5_6_7, TRUE), 'Comment #5_6_7. Second reply found.');
    $this->assertEqual($comment5_6_7->getThread(), '03.00.00/');
    // Confirm that there is a link to the parent comment.
    $this->assertParentLink($comment5_6_7->id(), $comment5_6->id());

    // Reply to comment #5 creating comment #5_8.
    $this->drupalGet('comment/reply/node/' . $this->node->id() . '/comment/' . $comment5->id());
    $comment5_8 = $this->postComment(NULL, $this->randomMachineName(), '', TRUE);

    // Confirm that the comment was created and has the correct threading.
    $this->assertTrue($this->commentExists($comment5_8), 'Comment #5_8. Third reply found.');
    $this->assertEqual($comment5_8->getThread(), '03.01/');
    // Confirm that there is a link to the parent comment.
    $this->assertParentLink($comment5_8->id(), $comment5->id());
  }

  /**
   * Test comment indenting.
   */
  public function testCommentMaxThreadDepth() {
    // Set comments to have a subject with preview disabled.
    $thread_depth = 2;
    $this->setCommentPreview(DRUPAL_DISABLED);
    $this->setCommentForm(TRUE);
    $this->setCommentSubject(TRUE);
    $this->setCommentSettings('default_mode', CommentManagerInterface::COMMENT_MODE_THREADED, 'Comment paging changed.');
    $this->setCommentSettings('thread_depth', $thread_depth, 'Thread depth changed.');

    // Create a node.
    $this->drupalLogin($this->webUser);
    $this->node = $this->drupalCreateNode(['type' => 'article', 'promote' => 1, 'uid' => $this->webUser->id()]);

    // Create first comment.
    $comments = [];
    $comment_expected_indents = [];
    $subject_text = $this->randomMachineName();
    $comment_text = $this->randomMachineName();
    $comments[] = $this->postComment($this->node, $comment_text, $subject_text, TRUE);
    $comment_expected_indents[] = 0;

    // Create enough comments to be one greater than the max thread depth. Each new
    // comment should be a reply to the previous created comment.
    for ($i = 0; $i < $thread_depth + 1; $i++) {
      $this->drupalGet('comment/reply/node/' . $this->node->id() . '/comment/' . $comments[$i]->id());
      $comments[] = $this->postComment(NULL, $this->randomMachineName(), '', TRUE);

      // Each comment should be indented one over from its parent until the
      // comment depth exceeds the user defined thread_depth. From that point
      // on, each new comment that is a reply to a comment that is at
      // thread_depth will not be indented over from its parent.
      if ($i >= $thread_depth) {
        $comment_expected_indents[] = 0;
      }
      else {
        $comment_expected_indents[] = 1;
      }
    }

    /** @var \Drupal\comment\CommentViewBuilder $view_builder */
    $view_builder = $this->container->get('entity.manager')->getViewBuilder('comment');

    // Build render array for all created comments and replies.
    $render_array = $view_builder->viewMultiple($comments, 'default');
    $updated_render_array = $view_builder->buildMultiple($render_array);

    // Confirm comment indent is adjusted when replies go past thread_depth.
    for ($i = 0; $i < count($comments); $i++) {
      if ($i === ($thread_depth + 1)) {
        $error_message = 'Comment indent should have been adjusted since thread depth exceeds thread_depth value';
      }
      else {
        $error_message = 'Comment indent does not match expected indent level';
      }
      $this->assertEquals($comment_expected_indents[$i], $updated_render_array[$i]['#comment_indent'], $error_message);
    }
  }

  /**
   * Asserts that the link to the specified parent comment is present.
   *
   * @param int $cid
   *   The comment ID to check.
   * @param int $pid
   *   The expected parent comment ID.
   */
  protected function assertParentLink($cid, $pid) {
    // This pattern matches a markup structure like:
    // <a id="comment-2"></a>
    // <article>
    //   <p class="parent">
    //     <a href="...comment-1"></a>
    //   </p>
    //  </article>
    $pattern = "//a[@id='comment-$cid']/following-sibling::article//p[contains(@class, 'parent')]//a[contains(@href, 'comment-$pid')]";

    $this->assertFieldByXpath($pattern, NULL, format_string(
      'Comment %cid has a link to parent %pid.',
      [
        '%cid' => $cid,
        '%pid' => $pid,
      ]
    ));
  }

  /**
   * Asserts that the specified comment does not have a link to a parent.
   *
   * @param int $cid
   *   The comment ID to check.
   */
  protected function assertNoParentLink($cid) {
    // This pattern matches a markup structure like:
    // <a id="comment-2"></a>
    // <article>
    //   <p class="parent"></p>
    //  </article>

    $pattern = "//a[@id='comment-$cid']/following-sibling::article//p[contains(@class, 'parent')]";
    $this->assertNoFieldByXpath($pattern, NULL, format_string(
      'Comment %cid does not have a link to a parent.',
      [
        '%cid' => $cid,
      ]
    ));
  }

}
