<?php

namespace Drupal\drush_display_fields\Commands;

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\Core\Entity\EntityTypeBundleInfo;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drush\Commands\DrushCommands;
use mysql_xdevapi\Exception;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A Command for easily modifying field and display config.
 */
class DrushDisplayFieldsCommands extends DrushCommands {

  /**
   * Bundle Info Class.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfo
   */
  protected $bundleInfo;

  /**
   * CnbcFieldUtilitiesCommands constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeBundleInfo $entity_type_bundle_info
   *   Bundle Info Manager.
   */
  public function __construct(EntityTypeBundleInfo $entity_type_bundle_info) {
    $this->bundleInfo = $entity_type_bundle_info;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.bundle.info')
    );
  }

  /**
   * Delete a field from config.
   *
   * @param string $field_name
   *   The name of the field being deleted (without cnbc_ prefix).
   * @param string $bundle
   *   The bundle that contains the field.
   * @param string $options
   *   Choose to erase all instances of the field.
   *
   * @command drush_display_fields:deleteField
   * @aliases delfield
   *
   * @throws \Exception
   */
  public function deleteFields($field_name, $bundle = NULL, $options = ['all' => FALSE]) {

    if ($options['all']) {
      $bundles_info = $this->bundleInfo->getBundleInfo('node');
      $bundles = array_filter(array_keys($bundles_info));
    }
    else {
      $bundles = [$bundle];
    }

    foreach ($bundles as $bundle) {
      $this->deleteField($bundle, $field_name);
    }
  }

  /**
   * Set fields in the displays to be visible.
   *
   * @param string $bundles
   *   The bundle being edited, or a comma-separated list of bundles. Use 'all' for all bundles.
   * @param mixed $fields
   *   The field being displayed, or a comma-separated list of fields. Use 'all' for all fields.
   * @param array $options
   *   'display' => Which display mode you wish to alter. Defaults to 'default'.
   *   'label' => Which label setting to apply.
   *   'weight' => Sets field weight. Only useful if a single field is being changed.
   *
   * @command drush_display_fields:updateDisplayFields
   * @aliases dispfields
   *
   * @throws \Exception
   *   If field or bundle is not valid.
   */
  public function updateDisplayFields($bundles, $fields, $options = [
    'display' => 'default',
    'label' => NULL,
    'widget' => NULL,
  ]) {

    if (mb_strtolower($bundles) === 'all') {
      $bundles_info = $this->bundleInfo->getBundleInfo('node');
      $bundles = array_filter(array_keys($bundles_info));
    }
    else {
      $bundles = explode(',', $bundles);
    }

    foreach ($bundles as $bundle) {
      $view_display = EntityViewDisplay::load('node.' . $bundle . '.' . $options['display']);
      if (!empty($view_display)) {
        $this->updateViewFields($view_display, $bundle, $fields, $options);
      }
      else {
        $this->logger->notice("The display " . $options['display'] . " is not a valid machine name or does not exist on the $bundle bundle");
      }
    }
  }

  /**
   * Set fields in the form displays to be visible.
   *
   * @param string $bundles
   *   The bundle being edited, or a comma-separated list of bundles. Use 'all' for all bundles.
   * @param mixed $fields
   *   The field being displayed, or a comma-separated list of fields. Use 'all' for all fields.
   * @param array $options
   *   'weight' => Sets field weight. Only useful if a single field is being changed.
   *   'widget' => Sets field widget. Only useful if a single field is being changed.
   *
   * @command drush_display_fields:updateFormFields
   * @aliases formfields
   *
   * @throws \Exception
   *   If field or bundle is not valid.
   */
  public function updateFormFields($bundles, $fields, $options = [
    'weight' => NULL,
    'widget' => NULL,
  ]) {

    if (mb_strtolower($bundles) === 'all') {
      $bundles_info = $this->bundleInfo->getBundleInfo('node');
      $bundles = array_filter(array_keys($bundles_info));
    }
    else {
      $bundles = explode(',', $bundles);
    }

    foreach ($bundles as $bundle) {
      $form_display = EntityFormDisplay::load('node.' . $bundle . '.default');
      if (!empty($form_display)) {
        $this->updateViewFields($form_display, $bundle, $fields, $options);
      }
      else {
        throw new \Exception("The $bundle bundle does not allow editing of its form, or its default display is corrupt.");
      }
    }
  }

  /**
   * Utility function that can process form or normal entity displays.
   *
   * @param EntityFormDisplay|EntityViewDisplay $entity_display
   * @param string $bundle
   * @param mixed $fields
   * @param array $options
   * @throws \Exception
   */
  protected function updateViewFields($entity_display, $bundle, $fields, $options = [
    'display' => 'default',
    'label' => NULL,
    'weight' => NULL,
    'widget' => NULL,
  ]) {

    if (empty($bundle)) {
      return;
    }

    $fields = explode(',', $fields);

    if (!$options['display']) {
      $options['display'] = 'default';
    }

    // If the user said to affect all fields (or foolishly included a field named
    // 'all' in their list of fields), display them all!
    if (in_array('all', $fields)) {
      if (!empty($entity_display)) {
        $hidden_fields = $entity_display->get('hidden');
        foreach (array_keys($hidden_fields) as $field_name) {
          if (substr($field_name, 0, 6) === 'field_') {
            $this->displayField($entity_display, $field_name, $options);
          }
        }
        $entity_display->save();
      }
    }
    else {
      // If not, only a subset are desired to be visible, so first set all non-default fields
      // to be hidden.
      $shown_fields = $entity_display->get('content');
      foreach (array_keys($shown_fields) as $field_name) {
        if (substr($field_name, 0, 6) === 'field_' && !in_array($field_name, $fields)) {
          $this->hideField($entity_display, $field_name);
        }
      }

      // Then set the fields selected to visible.
      foreach ($fields as $field_name) {
        $this->displayField($entity_display, $field_name, $options);
      }
      $entity_display->save();
    }
  }

  /**
   * A helper function for deleting an individual field.
   *
   * @param string bundle
   * @param string field_name
   *
   * @throws \Exception
   *   If field is not valid.
   */
  protected function deleteField($bundle, $field_name) {
    $field_instance = $this->checkField($bundle, $field_name);
    if ($field_instance) {
      $field_instance->delete();
      $this->logger->notice("The field $field_name was deleted from $bundle");
      // Field storage is automatically deleted by the API if no more instances
      // of the field exist.
      if (!FieldStorageConfig::loadByName('node', $field_name)) {
        $this->logger->notice("The field storage for $field_name was deleted");
      }
    }

    $view_display = EntityViewDisplay::load('node.' . $bundle . '.default');
    if ($view_display) {
      $view_display->save();
      $this->logger->notice("$field_name has been removed from the view display for $bundle");
    }

    $form_display = EntityFormDisplay::load('node.' . $bundle . '.default');
    if ($form_display) {
      $form_display->save();
      $this->logger->notice("$field_name has been removed from the form display for $bundle");
    }
  }

  /**
   * A helper function for checking field config.
   *
   * @param string bundle
   * @param string field_name
   *
   * @return mixed
   *   The field config entity.
   *
   * @throws \Exception
   *   If field is not valid.
   */
  protected function checkField($bundle, $field_name) {
    $field = FieldConfig::loadByName('node', $bundle, $field_name);
    if (!$field) {
      throw new \Exception("The field $field_name is not a valid machine name or does not exist on the $bundle bundle");
    }
    return $field;
  }

  /**
   * A helper function moving a field to the content region of a display.
   *
   * @param EntityFormDisplay $display
   * @param string $field_name
   * @param array $options
   * @return mixed
   *   The altered display object.
   */
  protected function displayField($display, $field_name, $options = []) {
    if (empty($display)) {
      return;
    }

    $bundle = $display->getTargetBundle();
    if (array_key_exists($field_name, $display->get('hidden')) || !empty($options)) {
      $display->setComponent($field_name, ['region' => 'content']);
      if (!empty($options)) {
        $display->setComponent($field_name, $options);
      }
      $this->logger->notice("$field_name has been added to " . $display->id() . " for $bundle");
      return $display;
    }
    $this->logger->notice("$field_name WAS NOT added to " . $display->id() . " for $bundle");
    return $display;
  }

  /**
   * A helper function removing a field from the content region of a display.
   *
   * @param EntityFormDisplay $display
   * @param string $field_name
   * @return mixed
   *   The altered display object.
   */
  protected function hideField($display, $field_name) {
    if (empty($display)) {
      return;
    }

    $bundle = $display->getTargetBundle();
    if (array_key_exists($field_name, $display->get('content'))) {
      $display->removeComponent($field_name);

      $this->logger->notice("$field_name has been removed from the " . $display->getEntityTypeId() . " for $bundle");
      return $display;
    }
    $this->logger->notice("$field_name WAS NOT removed from the " . $display->getEntityTypeId() . " for $bundle");
    return $display;
  }

}
