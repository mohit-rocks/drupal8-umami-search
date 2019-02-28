<?php

namespace Drupal\custom_facets\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\CurrentRouteMatch;
use Drupal\node\NodeInterface;
use Drupal\facets\FacetInterface;
use Drupal\Component\Utility\Html;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Implements a PriceRange form.
 */
class PriceRange extends FormBase {

  /**
   * The route match service.
   *
   * @var \\Drupal\Core\Routing\CurrentRouteMatch
   */
  protected $routeMatch;

  /**
   * Constructs a \Drupal\system\ConfigFormBase object.
   *
   * @param \Drupal\Core\Routing\CurrentRouteMatch $route_match
   *   The route match service.
   */
  public function __construct(CurrentRouteMatch $route_match) {
    $this->routeMatch = $route_match;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('current_route_match')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, FacetInterface $facet = NULL) {

    // Initialize the required variables.
    $prefix = '';
    $min_val = '';
    $max_val = '';

    // Check if facet exist.
    if (!empty($facet)) {
      // Get custom price range widget configuration.
      $facet_config = $facet->getWidgetInstance()->getConfiguration();

      // Get the prefix from facet config.
      if (!empty($facet_config['prefix'])) {
        $prefix = $facet_config['prefix'];
      }

      // Get active items from the facet.
      $active_items = $facet->getActiveItems();

      // Get min and max from active item if present.
      if ((!empty($active_items)) && (!empty($active_items[0]))) {
        $min_val = $active_items[0][0];
        $max_val = $active_items[0][1];
      }
    }

    // Min price field.
    $form['min_price'] = [
      '#type' => 'textfield',
      '#title' => $this->t('min'),
      '#title_display' => 'invisible',
      '#attributes' => [
        'placeholder' => $this->t('min'),
      ],
      '#size' => 6,
      '#default_value' => $min_val,
      '#field_prefix' => $prefix,
      '#field_suffix' => $this->t('to'),
      '#maxlength' => 6,
    ];

    // Max price field.
    $form['max_price'] = [
      '#type' => 'textfield',
      '#title' => $this->t('max'),
      '#title_display' => 'invisible',
      '#attributes' => [
        'placeholder' => $this->t('max'),
      ],
      '#size' => 6,
      '#default_value' => $max_val,
      '#field_prefix' => $prefix,
      '#maxlength' => 6,
    ];

    // Group submit handlers in an actions element with a key of "actions" so
    // that it gets styled correctly, and so that other modules may add actions
    // to the form. This is not required, but is convention.
    $form['actions'] = [
      '#type' => 'actions',
    ];

    // Add a submit button that handles the submission of the form.
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Go'),
    ];

    // Pass the facet variable in the form for accessing it in submit handler.
    $form['facet'] = [
      '#type' => 'value',
      '#value' => $facet,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'custom_facets_price_range';
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // Get min and max values.
    $min_price = trim($form_state->getValue('min_price'));
    $max_price = trim($form_state->getValue('max_price'));

    // Validate min and max values as per requirement.
    if ((empty($min_price)) && ('0' != $min_price)) {
      // Set error if min value is empty except zero.
      $form_state->setErrorByName('min_price', $this->t('Please enter a valid number range.'));
    }
    elseif (empty($max_price)) {
      // Set error if max value is empty.
      $form_state->setErrorByName('max_price', $this->t('Please enter a valid number range.'));
    }
    elseif ((!is_numeric($min_price)) || (floor($min_price) != $min_price) || ($min_price < 0) || ($min_price > 999999)) {
      // Set error if min value is not a whole number.
      $form_state->setErrorByName('min_price', $this->t('Please enter whole numbers only.'));
    }
    elseif ((!is_numeric($max_price)) || (floor($max_price) != $max_price) || ($max_price < 0) || ($max_price > 999999)) {
      // Set error if max value is not a whole number.
      $form_state->setErrorByName('max_price', $this->t('Please enter whole numbers only.'));
    }
    elseif ($min_price >= $max_price) {
      // Set error if min value is greater than max value.
      $form_state->setErrorByName('min_price', $this->t('Please enter a valid number range.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Get facet result.
    $facet = $form_state->getValue('facet');
    $results = !empty($facet) ? $facet->getResults() : [];
    $result = !empty($results) ? current($results) : [];

    $route = $this->routeMatch->getRouteName();

    if (!empty($result)) {
      // Get the active options.
      $active_options = $result->getUrl()->getOptions();

      // Get price elements from the form.
      $min_value = trim($form_state->getValue('min_price'));
      $max_value = trim($form_state->getValue('max_price'));
      $min = Html::escape($min_value);
      $max = Html::escape($max_value);

      // CHeck if the active options exist.
      if ((!empty($active_options)) && (!empty($active_options['query']['f']))) {
        // Initialize the variable for verifying if new query updated or not.
        $updated_query = FALSE;

        // Generate the query for the price range selection.
        $facet_id = $facet->id();
        $new_query = "{$facet_id}:(min:{$min},max:{$max})";

        // Loop through all the active options.
        foreach ($active_options['query']['f'] as $key => $val) {
          // Check if string contains range slider and price_range variable.
          if ((strpos($val, '__range_slider_min__') !== FALSE) && (strpos($val, '__range_slider_max__') !== FALSE) && (strpos($val, $facet_id) !== FALSE)) {
            $updated_query = TRUE;

            // Replace range slider variable with min and max of price range.
            $default_option = $val;
            $default_option = str_replace('__range_slider_min__', $min, $default_option);
            $active_options['query']['f'][$key] = str_replace('__range_slider_max__', $max, $default_option);
          }
          elseif (strpos($val, $new_query) !== FALSE) {
            // Update var if the query already exist for range.
            $updated_query = TRUE;
          }
          elseif (strpos($val, $facet_id) !== FALSE) {
            $updated_query = TRUE;

            // Replace min and max variable in the price range query.
            $active_options['query']['f'][$key] = $new_query;
          }
        }

        // Add query if the query is still not updated.
        if (!$updated_query) {
          $active_options['query']['f'][] = $new_query;
        }
      }

      // Set the from redirect to the same page with required query.
      $form_state->setRedirect($route, [], $active_options);
    }
  }

}
