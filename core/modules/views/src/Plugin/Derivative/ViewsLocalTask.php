<?php

namespace Drupal\views\Plugin\Derivative;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\Core\Routing\RouteProviderInterface;
use Drupal\views\ViewExecutable;
use Drupal\views\Views;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides local task definitions for all views configured as local tasks.
 */
class ViewsLocalTask extends DeriverBase implements ContainerDeriverInterface {

  /**
   * The route provider.
   *
   * @var \Drupal\Core\Routing\RouteProviderInterface
   */
  protected $routeProvider;

  /**
   * The state key value store.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * The view storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $viewStorage;

  /**
   * Constructs a \Drupal\views\Plugin\Derivative\ViewsLocalTask instance.
   *
   * @param \Drupal\Core\Routing\RouteProviderInterface $route_provider
   *   The route provider.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state key value store.
   * @param \Drupal\Core\Entity\EntityStorageInterface $view_storage
   *   The view storage.
   */
  public function __construct(RouteProviderInterface $route_provider, StateInterface $state, EntityStorageInterface $view_storage) {
    $this->routeProvider = $route_provider;
    $this->state = $state;
    $this->viewStorage = $view_storage;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    return new static(
      $container->get('router.route_provider'),
      $container->get('state'),
      $container->get('entity.manager')->getStorage('view')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    $this->derivatives = [];

    $view_route_names = $this->state->get('views.view_route_names');
    foreach ($this->getApplicableMenuViews() as $pair) {
      /** @var $executable \Drupal\views\ViewExecutable */
      list($view_id, $display_id) = $pair;
      $executable = $this->viewStorage->load($view_id)->getExecutable();

      $executable->setDisplay($display_id);
      $menu = $executable->display_handler->getOption('menu');
      if (in_array($menu['type'], ['tab', 'default tab'])) {
        $plugin_id = 'view.' . $executable->storage->id() . '.' . $display_id;
        $route_name = $view_route_names[$executable->storage->id() . '.' . $display_id];

        // Don't add a local task for views which override existing routes.
        // @todo Alternative it could just change the existing entry.
        if ($route_name != $plugin_id) {
          continue;
        }

        $this->derivatives[$plugin_id] = [
          'route_name' => $route_name,
          'weight' => $menu['weight'],
          'title' => $menu['title'],
        ] + $base_plugin_definition;

        if ($menu['type'] == 'default tab') {
          $tab_options = $executable->display_handler->getOption('tab_options');

          // If the user has chosen a "Menu tab" as the parent for the default
          // tab, then it must also be created and the parent relationship must
          // be established.
          if (!empty($tab_options['type']) && $tab_options['type'] == 'tab') {
            $parent_id = $plugin_id . '.parent';
            $this->derivatives[$parent_id] = [
              'route_name' => $route_name,
              'weight' => $tab_options['weight'],
              'title' => $tab_options['title'],
            ] + $base_plugin_definition;
            // A parent ID is the ID of a local task, which will be slightly
            // different from the plugin ID after the derivatives are added.
            $this->derivatives[$plugin_id]['parent_id'] = 'views_view:' . $parent_id;
          }
          // Default local tasks have themselves as root tab.
          else {
            $this->derivatives[$plugin_id]['base_route'] = $route_name;
          }
        }
      }
    }
    return $this->derivatives;
  }

  /**
   * Alters base_route and parent_id into the views local tasks.
   */
  public function alterLocalTasks(&$local_tasks) {
    $view_route_names = $this->state->get('views.view_route_names');

    foreach ($this->getApplicableMenuViews() as $pair) {
      list($view_id, $display_id) = $pair;
      /** @var $executable \Drupal\views\ViewExecutable */
      $executable = $this->viewStorage->load($view_id)->getExecutable();

      $executable->setDisplay($display_id);
      $menu = $executable->display_handler->getOption('menu');

      // We already have set the base_route for default tabs.
      $plugin_id = 'view.' . $executable->storage->id() . '.' . $display_id;
      if ($menu['type'] == 'tab') {
        $view_route_name = $view_route_names[$executable->storage->id() . '.' . $display_id];

        // Don't add a local task for views which override existing routes.
        if ($view_route_name != $plugin_id) {
          unset($local_tasks[$plugin_id]);
          continue;
        }

        $this->applyBaseRoute($executable, $plugin_id, $local_tasks);
      }
      // Although the base route for the default tab has been taken care of, we
      // would still have to adjust the parent tab, if it's being created.
      elseif ($menu['type'] == 'default tab') {
        $tab_options = $executable->display_handler->getOption('tab_options');
        if ($tab_options['type'] == 'tab') {
          $this->applyBaseRoute($executable, $plugin_id, $local_tasks, TRUE);
        }
      }
    }
  }

  /**
   * Apply the proper base route to a local task.
   *
   * @param \Drupal\views\ViewExecutable $view
   *   The View executable providing the local task.
   * @param string $plugin_id
   *   The identifier for the display plugin.
   * @param array $local_tasks
   *   The set of all local tasks, which will be adjusted.
   * @param bool $is_parent
   *   Notifying that we want a parent local task.
   */
  protected function applyBaseRoute(ViewExecutable $view, $plugin_id, &$local_tasks, $is_parent = FALSE) {
    // Find out the parent route.
    // @todo Find out how to find both the root and parent tab.
    $path = $view->display_handler->getPath();
    $split = explode('/', $path);
    array_pop($split);
    $path = implode('/', $split);

    $pattern = '/' . str_replace('%', '{}', $path);
    if ($routes = $this->routeProvider->getRoutesByPattern($pattern)) {
      foreach ($routes->all() as $name => $route) {
        $local_task_key = 'views_view:' . $plugin_id . ($is_parent ? '.parent' : '');
        $local_tasks[$local_task_key]['base_route'] = $name;
        // Skip after the first found route.
        break;
      }
    }
  }

  /**
   * Return a list of all views and display IDs that have a menu entry.
   *
   * @return array
   *   A list of arrays containing the $view and $display_id.
   * @code
   * array(
   *   array($view, $display_id),
   *   array($view, $display_id),
   * );
   * @endcode
   */
  protected function getApplicableMenuViews() {
    return Views::getApplicableViews('uses_menu_links');
  }

}
