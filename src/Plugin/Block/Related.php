<?php

namespace Drupal\related\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Related Content Block
 *
 * @author Attila Németh
 * 08.04.2021
 * 
 * @Block(
 *  id = "related",
 *  admin_label = @Translation("Related Content Block"),
 *  category = @Translation("Content"),
 * )
 */
class Related extends BlockBase {
  
  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form = parent::blockForm($form, $form_state);
    $config = $this->getConfiguration();
    $form['count'] = [
      '#type' => 'number',
      '#title' => t('Count'),
      '#description' => t('Maximal amount of elements displayed'),
      '#default_value' => $config['count'],
      '#min' => 1,
      '#max' => 36,
    ];
    return $form;
  }
  
  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $this->configuration['count'] = $form_state->getValue('count');
    return parent::blockSubmit($form, $form_state);
  }
  
  /**
   * {@inheritdoc}
   */
  public function build(): array {
    if (\Drupal::routeMatch()->getRouteName() === 'entity.node.canonical') {
      $node = \Drupal::routeMatch()->getParameter('node');
      $taxonomyFields = [];
      $taxonomyTerms = [];
      $query = \Drupal::entityQuery('node');
      $query->condition('nid', $node->id(), '<>');
      $taxonomyGroup = $query->orConditionGroup();
      foreach($node->getFieldDefinitions() as $field) {
        if ($field->getType() == 'entity_reference' && $field->getSetting('target_type') == 'taxonomy_term') {
          $taxonomyFields[$field->getName()] = $field->getName();
          foreach($node->get($field->getName()) as $item) {
            $term = $item->entity;
            $taxonomyTerms[$term->id()] = $term->id();
            $taxonomyGroup->condition($field->getName() . '.target_id', $term->id());
          }
        }
      }
      $query->condition($taxonomyGroup);
      $related = $query->execute();
      $contents = \Drupal::entityTypeManager()->getStorage('node')->loadMultiple($related);
      $rankings = [];
      foreach($contents as $relatedNode) {
        $points = 0;
        foreach($taxonomyFields as $fieldName) {
          foreach($relatedNode->get($fieldName) as $item) {
            if (array_key_exists($item->entity->id(), $taxonomyTerms)) {
              $points++;
            }
          }
        }
        $rankings[$relatedNode->id()] = $points;
      }
      arsort($rankings);
      $config = $this->getConfiguration();
      $relatedIds = array_slice(array_keys($rankings), 0, $config['count']);
      $build = [];
      $relatedNodes = \Drupal::entityTypeManager()->getStorage('node')->loadMultiple($relatedIds);
      $viewBuilder = \Drupal::entityTypeManager()->getViewBuilder('node');
      foreach($relatedNodes as $node) {
        $nodeBuild = $viewBuilder->view($node, 'teaser');
        $build[] = $nodeBuild;
      }
      return $build;
    }
    else {
      return [];
    }
  }
  
}
