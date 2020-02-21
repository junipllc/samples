<?php

namespace Drupal\CLIENTNAME_articles\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\CurrentRouteMatch;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\node\NodeInterface;
use Drupal\Core\Cache\Cache;

/**
 * Provides a 'More CLIENTNAME [sport]' block.
 *
 * @Block(
 *   id = "CLIENTNAME_more_CLIENTNAME_sport_block",
 *   admin_label = @Translation("More CLIENTNAME [sport] block"),
 * )
 */
class MoreCLIENTNAMESportBlock extends BlockBase implements ContainerFactoryPluginInterface {
  /**
   * Entity Type Manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * Route match.
   *
   * @var \Drupal\Core\Routing\CurrentRouteMatch
   */
  protected $routeMatch;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('current_route_match')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManager $entity_type_manager, CurrentRouteMatch $route_match) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
    $this->routeMatch = $route_match;
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $sport_reference = [];
    $title = '';
    $current_node = $this->getNode();
    if ($current_node instanceof NodeInterface) {
      // Try to get from the node.
      if ($current_node->getType() == 'sport') {
        $sport = $current_node;
        $title = $this->t("More Links");
      }
      elseif ($current_node->getType() == 'article') {
        $sport_reference = $current_node->get('field_sports')->getValue();
        if (empty($sport_reference[0]['target_id'])) {
          return [];
        }
      }
    }
    else {
      // Try to get from query param.
      $sport_param = CLIENTNAME_components_get_sport_from_filter();
      if (empty($sport_param) || empty($sport_param['nid'])) {
        return [];
      }
      else {
        $sport_reference[0]['target_id'] = $sport_param['nid'];
      }
    }

    // If we don't have the Sport by now, load it from the reference.
    if (empty($sport)) {
      $sport = $this->entityTypeManager->getStorage('node')->load($sport_reference[0]['target_id']);
    }

    if (!$sport instanceof NodeInterface) {
      return [];
    }

    $sport_name = $sport->getTitle();
    if ($current_node instanceof NodeInterface && $current_node->getType() == 'article') {
      $title = $this->t('More CLIENTNAME @sport', [
        '@sport' => $sport_name,
      ]);
    }

    $links = $sport->get('field_links')->getValue();
    if (count($links) && !empty($sport_name)) {
      $list = [];
      foreach ($links as $link) {
        if (!empty($link['title'] && !empty($link['uri']))) {
          $list[] = [
            'text' => $link['title'],
            'url' => $link['uri'],
          ];
        }
      }
      if (count($list)) {
        $build = [
          'CLIENTNAME_more_CLIENTNAME_sport_block' => [
            '#theme' => 'CLIENTNAME_more_CLIENTNAME_sport_block',
            '#link_list' => $list,
            '#title' => $title,
          ],
        ];
        return $build;
      }
    }

    return [];
  }

  /**
   * Get the current node object.
   *
   * @return mixed|null
   *   The current node.
   */
  public function getNode() {
    return $this->routeMatch->getParameter('node');
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    $sport_filter = CLIENTNAME_components_get_sport_from_filter();
    $school_filter = CLIENTNAME_components_get_school_from_filter();

    $tags = [];
    if (!empty($sport_filter['nid'])) {
      $tags[] = 'node:' . $sport_filter['nid'];
    }
    if (!empty($school_filter['nid'])) {
      $tags[] = 'node:' . $school_filter['nid'];
    }

    if (!empty($tags)) {
      return Cache::mergeTags(parent::getCacheTags(), $tags);
    }
    else {
      return parent::getCacheTags();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    return Cache::mergeContexts(parent::getCacheContexts(), ['url']);
  }

}
