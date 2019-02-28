<?php

namespace Drupal\custom_facets\Plugin\facets\widget;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\facets\FacetInterface;
use Drupal\facets\Result\ResultInterface;
use Drupal\facets_range_widget\Plugin\facets\widget\RangeSliderWidget;

/**
 * Custom range widget.
 *
 * @FacetsWidget(
 *   id = "custom_range",
 *   label = @Translation("Custom Range"),
 *   description = @Translation("A widget that provides range widget for facet."),
 * )
 */
class CustomRangeWidget extends RangeSliderWidget {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'custom_range' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function build(FacetInterface $facet) {
    $this->facet = $facet;
    $widget = $facet->getWidget();

    // Get custom range from the configuration.
    $options = $this->getConfiguration();
    $range = $options['custom_range'];
    $new_ranges = preg_split('/\n|\r\n?/', $range);

    $new_items_counts = [];

    // Loop through the results and group the results between the range.
    $results = $facet->getResults();
    foreach ($new_ranges as $range) {
      $new_items_counts[$range]['count'] = 0;
      list($min, $max) = explode('|', $range);

      // Set min as zero if null.
      if ($min == NULL) {
        $min = 0;
      }

      foreach ($results as $result) {
        if ($result->getRawValue() > $min && $result->getRawValue() < $max) {
          $new_items_counts[$range]['count'] += $result->getCount();
        }
        elseif ($result->getRawValue() > $min && $max == NULL) {
          $new_items_counts[$range]['count'] += $result->getCount();
        }
      }
    }

    // Build the array of items from the new range.
    foreach ($new_ranges as $key => $range) {
      if (isset($results[$key])) {
        $modified_items[] = $this->facetsBuildListItems($facet, $results[$key], $range, $new_items_counts);
      }
    }

    // Build the list items from the new range.
    $modified_items = [
      '#theme' => $this->getFacetItemListThemeHook($facet),
      '#facet' => $facet,
      '#items' => $modified_items,
      '#attributes' => [
        'data-drupal-facet-id' => $facet->id(),
        'data-drupal-facet-alias' => $facet->getUrlAlias(),
      ],
      '#context' => ['list_style' => $widget['type']],
      '#cache' => [
        'contexts' => [
          'url.path',
          'url.query_args',
        ],
      ],
    ];

    // Add the library to convert list item to checkbox.
    $modified_items['#attributes']['class'][] = 'js-facets-checkbox-links';
    $modified_items['#attached']['library'][] = 'facets/drupal.facets.checkbox-widget';

    return $modified_items;
  }

  /**
   * Builds a renderable array of result items.
   *
   * @param \Drupal\facets\FacetInterface $facet
   *   The facet we need to build.
   * @param \Drupal\facets\Result\ResultInterface $result
   *   A result item.
   * @param string $range
   *   Filter range string like 100|300.
   * @param array $new_items_counts
   *   Array of all the range strings with result count for each range string.
   *
   * @return array
   *   A renderable array of the result.
   */
  protected function facetsBuildListItems(FacetInterface $facet, ResultInterface $result, $range, array $new_items_counts) {
    $classes = ['facet-item'];

    // Prepare the link for the facet from the list of items.
    $items = $this->customFacetsPrepareLink($result, $range, $new_items_counts);

    $children = $result->getChildren();

    // Expand the result if has active result.
    if ($children && ($this->facet->getExpandHierarchy() || $result->isActive() || $result->hasActiveChildren())) {
      // Add class accordingly.
      $child_items = [];
      $classes[] = 'facet-item--expanded';
      foreach ($children as $child) {
        $child_items[] = $this->customFacetsBuildListItems($facet, $child);
      }

      // Theme the child hook elements.
      $items['children'] = [
        '#theme' => $this->getFacetItemListThemeHook($facet),
        '#items' => $child_items,
      ];

      // Add the class for active trail.
      if ($result->hasActiveChildren()) {
        $classes[] = 'facet-item--active-trail';
      }

    }
    else {
      // Add the class for collapsed item.
      if ($children) {
        $classes[] = 'facet-item--collapsed';
      }
    }

    // Get min value and max value from the range.
    list($min_val, $max_val) = $this->customFacetsGetRangeValue($result, $range);

    // Get active items.
    $active_items = $facet->getActiveItems();

    // Add the class if element is active.
    if (!empty($active_items)) {
      foreach ($active_items as $active_range) {
        if (isset($active_range[0]) && (isset($active_range[1])) && ($active_range[0] == $min_val) && ($active_range[1] == $max_val)) {
          $items['#attributes'] = ['class' => ['is-active']];
        }
      }
    }

    // Add facet data id attr and add classes.
    $items['#wrapper_attributes'] = ['class' => $classes];
    $items['#attributes']['data-drupal-facet-item-id'] = $this->facet->getUrlAlias() . '-' . str_replace(' ', '-', $result->getRawValue());
    $items['#attributes']['data-drupal-facet-item-value'] = $result->getRawValue();

    // Return element if its count is not empty.
    if ((!empty($items['#title'])) && (!empty($items['#title']['#count']))) {
      return $items;
    }
  }

  /**
   * Returns the text or link for an item.
   *
   * @param \Drupal\facets\Result\ResultInterface $result
   *   A result item.
   * @param string $range
   *   Filter range string like 100|300.
   * @param array $new_items_counts
   *   Array of all the range strings with result count for each range string.
   *
   * @return array
   *   The item as a render array.
   */
  public function customFacetsPrepareLink(ResultInterface $result, $range, array $new_items_counts) {
    $item = $this->customFacetsBuildResultItem($result, $range, $new_items_counts);

    // Prepare the url for the range according to the selected options.
    if (!is_null($result->getUrl())) {
      list($min, $max) = $this->customFacetsGetRangeValue($result, $range);

      // Get the active options.
      $active_options = $result->getUrl()->getOptions();

      if ((!empty($active_options)) && (!empty($active_options['query']['f']))) {
        $updated_query = FALSE;

        // Loop through all the queries.
        foreach ($active_options['query']['f'] as $key => $val) {
          // Check if string contains range slider variable.
          if ((strpos($val, '__range_slider_min__') !== FALSE) && (strpos($val, '__range_slider_max__') !== FALSE)) {
            $updated_query = TRUE;

            // Replace range slider variable with min and max of custom range.
            $default_option = $val;
            $default_option = str_replace('__range_slider_min__', $min, $default_option);
            $active_options['query']['f'][$key] = str_replace('__range_slider_max__', $max, $default_option);
          }
        }

        // Add query if the query is not updated.
        if (!$updated_query) {
          $active_options['query']['f'][] = 'price:' . '(min:' . $min . ',max:' . $max . ')';
        }

        // Get all the active selections of the facet.
        $active_selections = $result->getFacet()->getActiveItems();

        // Check if all the active selections are present in the query.
        if ((!empty($active_options['query']['f'])) && (!empty($active_selections))) {
          // Loop through all the query options and the active selections.
          foreach ($active_selections as $active_range) {
            $selection_exist = FALSE;

            // Get min and max for each active selection.
            $min_active = isset($active_range[0]) ? $active_range[0] : 0;
            $max_active = isset($active_range[1]) ? $active_range[1] : '';
            $query = 'price:' . '(min:' . $min_active . ',max:' . $max_active . ')';

            foreach ($active_options['query']['f'] as $key => $val) {
              // Update selection exist if query exist.
              if (strpos($val, $query) !== FALSE) {
                $selection_exist = TRUE;
              }

              // Unset query if active selection matches current range.
              $orig_query = 'price:' . '(min:' . $min . ',max:' . $max . ')';
              if ((strpos($val, $orig_query) !== FALSE) && ($min == $min_active) && ($max == $max_active)) {
                unset($active_options['query']['f'][$key]);
              }
            }

            // Add the active element to the query if does not exist.
            if (!$selection_exist) {
              $active_options['query']['f'][] = $query;
            }
          }
        }

        // Update the url with the active option.
        $new_url = $result->getUrl()->setOptions($active_options);
        $result->setUrl($new_url);
        $item = (new Link($item, $result->getUrl()))->toRenderable();
      }
    }

    return $item;
  }

  /**
   * Get max value from the result.
   *
   * @param \Drupal\facets\Result\ResultInterface $result
   *   The result item.
   *
   * @return string
   *   Max value from the result.
   */
  protected function customFacetsGetMaxFromResult(ResultInterface $result) {
    $max = NULL;

    // Get all the original results.
    $results = $result->getFacet()->getResults();

    if (!empty($results)) {
      $result_val = [];

      // Loop through all the results and get array of price.
      foreach ($results as $val) {
        $result_val[$val->getRawValue()] = $val->getRawValue();
      }

      // Get max value of price.
      $max = max($result_val);
    }

    return $max;
  }

  /**
   * Builds a facet result item.
   *
   * @param \Drupal\facets\Result\ResultInterface $result
   *   The result item.
   * @param string $range
   *   Filter range string like 100|300.
   * @param array $new_items_counts
   *   Array of all the range strings with result count for each range string.
   *
   * @return array
   *   The facet result item as a render array.
   */
  protected function customFacetsBuildResultItem(ResultInterface $result, $range, array $new_items_counts) {
    // Build facet result item according to configuration.
    $count = $new_items_counts[$range]['count'];
    list($min, $max) = explode("|", $range);
    $configuration = $this->getConfiguration();
    $prefix = isset($configuration['prefix']) ? $configuration['prefix'] : '';

    // Set min to zero if empty.
    $min = empty($min) ? 0 : $min;

    // Generate title according to presence of max value.
    if (!empty($max)) {
      $title = $prefix . $min . ' - ' . $prefix . $max;
    }
    else {
      $title = $prefix . $min . '+';
    }

    // Return themed result item.
    return [
      '#theme' => 'facets_result_item',
      '#is_active' => $result->isActive(),
      '#value' => $title,
      '#show_count' => $this->getConfiguration()['show_numbers'] && ($count !== NULL),
      '#count' => $count,
      '#facet' => $result->getFacet(),
      '#raw_value' => $range,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state, FacetInterface $facet) {
    $config = $this->getConfiguration();
    $form = parent::buildConfigurationForm($form, $form_state, $facet);

    // Create custom range field in the config form.
    $form['custom_range'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Custom Range'),
      '#size' => 5,
      '#default_value' => $config['custom_range'],
      '#description' => $this->t('A list of ranges in min|max format, i.e minimum value and maximum value separated by a pipe. Enter one range per line, in the format <em>min_value|max_value</em>. The <em>min_value</em> is the mimumum value that will be used to generate the query, in format <em>from-to</em>, and the <em>max_value</em> is the maximum value for that range. If <em>min_value</em> value is not provided, then 0 will be used instead. Example: <em>100|200</em>. Pipe separator | is necessary even in every range even if min value or the max value is supposed to be empty.'),
      '#element_validate' => [
        [$this, 'customFacetsValidateRange'],
      ],
    ];

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
  public function customFacetsValidateRange($element, FormStateInterface $form_state) {
    $value = $form_state->getValue($element['#parents']);

    // Check if the custom range is present.
    if (empty($value)) {
      $form_state->setError($element, $this->t('Custom range is empty, please enter the desired range.'));
    }
    else {
      // Separate custom range config value from new line.
      $custom_range = explode(PHP_EOL, $value);

      // Check if custom range is present after separating by new line.
      if (empty($custom_range)) {
        $form_state->setError($element, $this->t('Custom range is empty, please enter the desired range.'));
      }
      else {
        // Loop through each custom range.
        foreach ($custom_range as $range) {
          // Validate range contains pipe separator.
          if (!preg_match("/\|/", $range)) {
            $form_state->setError($element, $this->t('Custom range does not contain pipe separator in the range. Please make sure pipe "|" is present between the min value and max value of each range even if either of the value is empty.'));
          }

          // Get the min and max values from the custom range.
          $range_val = explode('|', $range);
          $min_val = isset($range_val[0]) ? (int) $range_val[0] : '';
          $max_val = isset($range_val[1]) ? (int) $range_val[1] : '';

          // Validate range to be integer.
          if ((!empty($range_val[0])) && (!preg_match('/^\d+$/', trim($range_val[0])))) {
            $form_state->setError($element, $this->t('Minimum value: @min_val is not an integer, please correct the value to be an integer.', ['@min_val' => $range_val[0]]));
          }
          elseif ((!empty($range_val[1])) && (!preg_match('/^\d+$/', trim($range_val[1])))) {
            $form_state->setError($element, $this->t('Maximum value: @max_val is not an integer, please correct the value to be an integer.', ['@max_val' => $range_val[1]]));
          }

          // Validate range for condition.
          if ((!empty($min_val)) && (!empty($max_val))) {
            if ($min_val > $max_val) {
              $form_state->setError($element, $this->t('Minimum value cannot be greater than maximum value. @min_val is greater than @max_val in custom range.', ['@min_val' => $min_val, '@max_val' => $max_val]));
            }
            elseif ($min_val == $max_val) {
              $form_state->setError($element, $this->t('Minimum value cannot be equal to maximum value. @min_val is same for both minimum value and maximum value in custom range.', ['@min_val' => $min_val]));
            }
          }
          elseif ((empty($min_val)) && (empty($max_val)) && ($min_val !== 0)) {
            $form_state->setError($element, $this->t('Custom range contains empty option, please remove the empty option from the list.'));
          }
        }
      }
    }
  }

  /**
   * Returns the min and max value separated by pipe from the range.
   *
   * @param \Drupal\facets\Result\ResultInterface $result
   *   A result item.
   * @param string $range
   *   Filter range string like 100|300.
   *
   * @return array
   *   Array containing min and max values.
   */
  protected function customFacetsGetRangeValue(ResultInterface $result, $range) {
    // Get min and max from the range.
    list($min, $max) = explode('|', $range);

    // Set min as zero if null.
    if ($min == NULL) {
      $min = 0;
    }

    // Get max from original result if null.
    if ($max == NULL) {
      $max = $this->customFacetsGetMaxFromResult($result);
    }

    return [
      $min,
      $max,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function isPropertyRequired($name, $type) {
    if ($name === 'range_slider' && $type === 'processors') {
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
