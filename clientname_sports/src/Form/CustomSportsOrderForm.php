<?php

namespace Drupal\CLIENTNAME_sports\Form;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Custom Sports draggable ordering (sorting) form.
 */
class CustomSportsOrderForm extends FormBase {

  /**
   * Drupal\Core\Entity\EntityTypeManagerInterface definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * Is this the table for Featured nodes?
   *
   * @var bool
   */
  protected $featured;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('database')
    );
  }

  /**
   * Construct a form.
   *
   * @param \Drupal\Core\Entity\EntityTypeManager $entity_type_manager
   *   Entity Type Manager.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   */
  public function __construct(EntityTypeManager $entity_type_manager, Connection $database) {
    $this->database = $database;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'CLIENTNAME_custom_sports_order_form';
  }

  /**
   * Builds the ordering form.
   *
   * @param array $form
   *   Render array representing form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Current form state.
   * @param string|null $type
   *   The parameter from the path URL.
   *
   * @return array
   *   The render array defining the elements of the form.
   */
  public function buildForm(array $form, FormStateInterface $form_state, $type = NULL) {
    $sports = [];
    $this->featured = $type == 'featured' ? TRUE : FALSE;

    $form['table-row'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('Sport Name'),
        $this->t('Status'),
        $this->t('Weight'),
      ],
      '#empty' => $this->t('Sorry, There are no items!'),
      '#tabledrag' => [
        [
          'action' => 'order',
          'relationship' => 'sibling',
          'group' => 'table-sort-weight',
        ],
      ],
    ];

    try {
      $query = $this->entityTypeManager
        ->getStorage('node')
        ->getQuery();
      $query->condition('type', 'sport', '=');
      if ($this->featured) {
        $query->condition('field_featured', TRUE, '=');
        $query->sort('field_featured_weight', 'ASC');
      }
      else {
        $query->sort('field_weight', 'ASC');
      }
      $nids = $query->execute();
      $node_storage = $this->entityTypeManager
        ->getStorage('node');
      $sports = $node_storage->loadMultiple($nids);
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException $e) {
    }

    $weight = 0;
    foreach ($sports as $row) {
      $form['table-row'][$row->id()]['#attributes']['class'][] = 'draggable';
      $form['table-row'][$row->id()]['#weight'] = $row->weight;
      $form['table-row'][$row->id()]['name'] = [
        '#markup' => $row->getTitle(),
      ];
      $form['table-row'][$row->id()]['status'] = [
        '#markup' => $row->status->value ? 'published' : 'unpublished',
      ];
      $form['table-row'][$row->id()]['weight'] = [
        '#type' => 'weight',
        '#title' => $this->t('Weight for @title', ['@title' => $row->getTitle()]),
        '#title_display' => 'invisible',
        '#default_value' => $weight,
        '#attributes' => ['class' => ['table-sort-weight']],
      ];
      $weight++;
    }

    $form['featured'] = [
      '#type' => 'hidden',
      '#value' => $this->featured,
    ];
    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save All Changes'),
    ];
    $form['actions']['submit']['#submit'][] = '::submitForm';

    return $form;
  }

  /**
   * Form submission handler for the simple form.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $submission = $form_state->getValue('table-row');
    $field_name = $form_state->getValue('featured') ? 'field_featured_weight' : 'field_weight';
    foreach ($submission as $nid => $value) {
      $node = $this->entityTypeManager->getStorage('node')->load($nid);
      $node->set($field_name, $value['weight']);
      $node->save();
    }
  }

}
