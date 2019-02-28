<?php

namespace Drupal\custom_facets\Plugin\facets\widget;

use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\facets\FacetInterface;
use Drupal\facets_range_widget\Plugin\facets\widget\RangeSliderWidget;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Custom range widget.
 *
 * @FacetsWidget(
 *   id = "custom_range_text_widget",
 *   label = @Translation("Custom Range Text Fields"),
 *   description = @Translation("A widget that provides custom range textfields for facet."),
 * )
 */
class CustomRangeTextWidget extends RangeSliderWidget implements ContainerFactoryPluginInterface {

  /**
   * The form builder.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * Constructs the Custom Price Range Widget object.
   *
   * @param array $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Form\FormBuilderInterface $form_builder
   *   The Form Builder.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, FormBuilderInterface $form_builder) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->formBuilder = $form_builder;
  }

  /**
   * Creates an instance of the plugin.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The container to pull out services used in the plugin.
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   *
   * @return static
   *   Returns an instance of this plugin.
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('form_builder')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'custom_price_range' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function build(FacetInterface $facet) {
    $form = $this->formBuilder->getForm('Drupal\custom_facets\Form\PriceRange', $facet);
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state, FacetInterface $facet) {
    $form = parent::buildConfigurationForm($form, $form_state, $facet);

    // Hide show amount of result if exists in the config form.
    if (!empty($form['show_numbers'])) {
      unset($form['show_numbers']);
    }

    // Hide suffix if exists in the config form.
    if (!empty($form['suffix'])) {
      unset($form['suffix']);
    }

    // Hide min type if exists in the config form.
    if (!empty($form['min_type'])) {
      unset($form['min_type']);
    }

    // Hide min value if exists in the config form.
    if (!empty($form['min_value'])) {
      unset($form['min_value']);
    }

    // Hide max type if exists in the config form.
    if (!empty($form['max_type'])) {
      unset($form['max_type']);
    }

    // Hide max value if exists in the config form.
    if (!empty($form['max_value'])) {
      unset($form['max_value']);
    }

    // Hide step field if exists in the config form.
    if (!empty($form['step'])) {
      unset($form['step']);
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function isPropertyRequired($name, $type) {
    if ($name === 'range_slider' && $type === 'processors') {
      return TRUE;
    }
    elseif ($name === 'custom_range' && $type === 'processors') {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getQueryType() {
    return 'range';
  }

}
