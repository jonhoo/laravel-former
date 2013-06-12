<?php

/**
 * Former\Form is a Laravel helper class for configuring, dispaying and
 * validating forms
 * @author Jon Gjengset <jon@tsp.io>
 * @package Former
 */

namespace Former;

use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\MessageBag;

class Form {

  /**
   * Fields that should be printed with type='text'
   */
  private static $textAlias = array('wide', 'short', 'address');

  protected $_fields = null;
  protected $_messages = null;
  protected $_source = null;
  protected $_input = null;
  protected $_errors = null;

  /**
   * Create a new form based on definitions from the given file
   *
   * @param string $file The file to read definitions form (will be required())
   * @param array [$messages] Localized messages for Validator::make
   * @return Form A new form with the specs read from $file
   */
  public static function fromFile($file, $messages = null) {
    return new Form(require($file), $messages);
  }

  /**
   * Extract the field $f from the element $x, regardless of whether
   * it's an object or an array. Otherwise, return $otherwise
   *
   * @param object|array $x Element to get a field from
   * @param string $f Field to extract value for
   * @param mixed [$otherwise] Fallback value if field not set
   */
  protected static function get($x, $f, $otherwise = null) {
    if (is_null($x)) {
      return $otherwise;
    }

    if (is_array($x) && array_key_exists($f, $x)) {
      return $x[$f];
    }

    if (is_object($x) && isset($x->$f)) {
      return $x->$f;
    }

    return $otherwise;
  }

  /**
   * Create a new form bsaed on the given fields
   *
   * The field specifications given in $fields will be modified based on the
   * given type's field. Number fields will for example automatically get the
   * numeric filter, dates the date filter, etc.
   *
   * @param array $fields array indexed by field name where each field is an
   *   array with any of the following indices:
   *   - type: text|wide|long|number|date|email|phone|bool
   *   - validate: <array of Validator rules>
   *   - padto: <left-pad with zeros to given length>
   *   - option: <for enum: array of value => title>
   *   - default: <for enum: default value>
   * @param array [$messages] messages passed to Validator::make
   */
  public function __construct($fields, $messages = null) {
    foreach ($fields as $f => $spec) {
      if (strpos($f, '_confirmation') !== false) {
        continue;
      }

      if (!array_key_exists('type', $spec)) {
        $fields[$f]['type'] = 'text';
      }

      if (!array_key_exists('validate', $spec)) {
        $fields[$f]['validate'] = array();
      }

      if (in_array('confirmed', $fields[$f]['validate'])) {
        $fields[$f . '_confirmation'] = array_merge($fields[$f], array(
          'field' => $f . '_confirmation',
          'autoadded' => true,
        ));
      }

      $fields[$f]['field'] = $f;
    }

    $this->_fields = $fields;
    $this->_messages = $messages;
  }

  /**
   * Set the data source to use for this form.
   *
   * The data source will be used to deterine the value of form elements if no
   * input is given
   *
   * @param array|object $source Source data
   */
  public function setSource($source) {
    $this->_source = $source;
  }

  /**
   * Set the input data to use for this form.
   *
   * The input data will be used to deterine the value of form elements, as well
   * as being the target of validation for the accept() method
   *
   * @param array|object $input Input data (usually Input::all())
   */
  public function setInput($input) {
    $this->_input = $input;
  }

  /**
   * Validate incoming data from this form based on the data given to setInput
   *
   * @return Validator A Validator for all the fields defined for this form
   */
  public function accept() {
    $data = array();
    $validate = array();

    foreach ($this->_fields as $field => $spec) {
      $trimmed = trim(self::get($this->_input, $field, ""));

      switch ($spec['type']) {
        case 'number':
          $spec['validate'][] = 'numeric';
          break;
        case 'text':
        case 'wide':
        case 'long':
          break;
        case 'date':
          $spec['validate'][] = 'date_format:Y-m-d';
          break;
        case 'email':
          $spec['validate'][] = 'email';
          break;
        case 'phone':
          $spec['validate'][] = 'regex:/^\+?[\d ]+$/';
          break;
        case 'bool':
          $trimmed = !empty($trimmed) ? "yes" : "";
          break;
        case 'enum':
          $spec['validate'][] = 'in:' . implode(',', array_keys($spec['options']));
          break;
      }

      if (!array_key_exists('autoadded', $spec)) {
        $validate[$field] = $spec['validate'];
      }
      $data[$field] = $trimmed;
    }

    if (!is_null($this->_messages)) {
      $validator = Validator::make($data, $validate, $this->_messages);
    } else {
      $validator = Validator::make($data, $validate);
    }

    if ($validator->fails()) {
      $this->_errors = $validator->messages();
    }
    return $validator;
  }

  /**
   * Returns a form field for $spec, showing the indicated value
   *
   * @param array $spec Specification for the field to display
   * @param string $bestValue The value to display for this form element
   * @return string HTML for the form element
   */
  protected static function _field($spec, $bestValue) {
    $ni = 'name="' . $spec['field'] . '" id="' . $spec['field'] . '"';
    $e = htmlspecialchars($bestValue);
    $r = in_array('required', $spec['validate']) ? 'required' : '';
    $pl = empty($spec['placeholder']) ? '' : 'placeholder="' . htmlspecialchars($spec['placeholder']) . '"';

    switch ($spec['type']) {
      case 'long':
        return "<textarea class=\"input-long\" $ni $pl rows=\"10\" cols=\"40\" $r>$e</textarea>";
      case 'bool':
        if ($bestValue) {
          return "<input type=\"checkbox\" $ni value=\"yes\" checked $r />";
        }

        return "<input type=\"checkbox\" $ni value=\"yes\" $r />";
      case 'enum':
        $s = "<select $ni $r>";

        foreach ($spec['options'] as $value => $title) {
          $s .= '<option value="' . htmlspecialchars($value) . '"';
          if ($value == $bestValue) {
            $s .= ' selected="selected"';
          }
          $s .= '>' . htmlspecialchars($title) . '</option>';
        }

        $s .= '</select>';
        return $s;
      case 'date':
        $after = $before = "";
        foreach ($spec['validate'] as $s) {
          if (strpos($s, 'after:') === 0) {
            $after = 'data-after="' . date('Y-m-d', strtotime(substr($s, strlen('after:')))) . '"';
          }
          if (strpos($s, 'before:') === 0) {
            $before = 'data-before="' . date('Y-m-d', strtotime(substr($s, strlen('before:')))) . '"';
          }
        }

        return "<input type=\"date\" $ni value=\"$e\" placeholder=\"YYYY-MM-DD\" $r />";
      default:
        $type = $spec['type'];

        if (in_array($type, self::$textAlias)) {
          $type = 'text';
        }

        if ($type == 'number' && !empty($spec['padto'])) {
          $type = 'text';
        }

        if (!empty($spec['padto']) && !empty($bestValue)) {
          $bestValue = str_pad($bestValue, $spec['padto'], '0', STR_PAD_LEFT);
        }

        if ($type == 'phone') {
          $type = 'tel';
        }

        return "<input type=\"$type\" $ni $pl value=\"$e\" $r />";
    }
  }

  /**
   * Returns the best value for the given field
   *
   * The decision is based on the current user input and the data source.
   * User input is preferred over source data.
   * Will return "" for password fields
   *
   * @param string $field Field to get best value for
   * @return string The best value to display for $field
   */
  public function bestValue($field) {
    if (!array_key_exists($field, $this->_fields)) {
      return null;
    }

    $spec = $this->_fields[$field];
    $s = self::get($this->_source, $spec['field']);
    $i = self::get($this->_input, $spec['field']);
    $bestValue = is_null($i) ? $s : $i;

    switch ($spec['type']) {
      case 'bool':
        if (!is_null($this->_input) && is_null($i)) {
          return false;
        }
        break;

      case 'enum':
        if (empty($bestValue)) {
          if (array_key_exists('default', $spec)) {
            return $spec['default'];
          }
          return null;
        }
        break;
      case 'password':
        return "";
    }

    return $bestValue;
  }

  /**
   * Returns a full form element (with label and containers) for $field
   *
   * Data should have been set with setSource and setInput before calling this
   * function.
   *
   * @param string $field The field to get the form element HTML for
   * @return string HTML for the full form field
   */
  public function fieldset($field) {

    if (!array_key_exists($field, $this->_fields)) {
      return "";
    }

    $spec = $this->_fields[$field];
    $failed = !is_null($this->_errors) && $this->_errors->has($spec['field']);
    $bestValue = $this->bestValue($field);

    $o = "";
    $o .= '<div class="control-group input-' . $spec['type'] . ($failed ? ' error' : '') . '">';
      $o .= '<label class="control-label" for="' . $spec['field']. '">' . $this->fieldName($field) . '</label>';
      $o .= '<div class="controls">';
        $o .= self::_field($spec, $bestValue);
        if ($failed) {
          $o .= '<span class="help-inline">' . htmlspecialchars($this->_errors->first($spec['field'])) . '</span>';
        }
      $o .= '</div>';
    $o .= '</div>';

    return $o;
  }

  /**
   * Returns a display-only element (with label and containers) for $field
   *
   * Data should have been set with setSource and setInput before calling this
   * function.
   *
   * @param string $field The field to get the display HTML for
   * @param string [$yes] Value to use for set boolean fields
   * @param string [$no] Value to use for unset boolean fields
   * @return string HTML for the display-only element
   */
  public function displayfield($field, $yes = 'Yes', $no = 'No') {

    if (!array_key_exists($field, $this->_fields)) {
      return "";
    }

    $spec = $this->_fields[$field];
    $bestValue = $this->bestValue($field);

    switch ($spec['type']) {
      case 'enum':
        if (array_key_exists($bestValue, $spec['options'])) {
          $bestValue = $spec['options'][$bestValue];
        } else if (isset($spec['default'])) {
          $bestValue = $spec['options'][$spec['default']];
        }
        break;
      case 'bool':
        $bestValue = $bestValue ? $yes : $no;
      break;

    }

    if (!empty($spec['padto']) && !empty($bestValue)) {
      $bestValue = str_pad($bestValue, $spec['padto'], '0', STR_PAD_LEFT);
    }

    $o = "";
    $o .= '<div class="control-group input-' . $spec['type'] . '">';
      $o .= '<dt class="control-label">' . $this->fieldName($field) . '</dt>';
      $o .= '<dd class="controls">' . htmlspecialchars($bestValue) . '</dd>';
    $o .= '</div>';

    return $o;
  }

  /**
   * Return the input element for the given field
   *
   * @param string $field Name of the field to display
   * @return string HTML for the form element
   */
  public function field($field) {
    if (!array_key_exists($field, $this->_fields)) {
      return "";
    }

    $bestValue = $this->bestValue($field);
    return self::_field($this->_fields[$field], $bestValue);
  }

  /**
   * Get the translated display name for the given field
   *
   * Uses the Laravel Lang provider to translate validation.attributes.$field
   * as done by Validator
   *
   * @param string $field Field to get the name for
   * @return string Translated name for $field
   */
  public function fieldName($field) {
    return Lang::get('validation.attributes.' . $field);
  }

  /**
   * Get the current specification for this form
   *
   * @todo Should prune out things that were automatically added in __construct
   * @return array Specifications on the same form as passed to __construct
   */
  public function fieldSpecs() {
    return $this->_fields;
  }

  /**
   * Get the specification for the given field
   *
   * @todo Should prune out things that were automatically added in __construct
   * @param string $field Name of field to get spec for
   * @return array? Specification for the given field or null
   */
  public function fieldSpec($field) {
    if (!array_key_exists($field, $this->_fields)) {
      return null;
    }

    return $this->_fields[$field];
  }

  /**
   * Set the spec of the given field to $spec
   *
   * @todo Should also do the automatic adding stuff done in __construct
   * @param string $field Field to change spec for
   * @param array $spec New spec for $field
   */
  public function setFieldSpec($field, $spec) {
    $this->_fields[$field] = $spec;
  }

  /**
   * Set the given attribute of the spec for $field to $val
   *
   * @param string $field Field to change spec for
   * @param string $attr Attribute to change
   * @param mixed $val New value for [$field][$attr]
   */
  public function setFieldSpecAttr($field, $attr, $val) {
    if (!array_key_exists($field, $this->_fields)) {
      return false;
    }

    if (!array_key_exists($attr, $this->_fields[$field])) {
      $this->_fields[$field] = array($attr => $val);
      return true;
    } else if (is_array($this->_fields[$field])) {
      $this->_fields[$field][$attr] = array($attr => $val);
      return true;
    } else {
      return false;
    }
  }

  /**
   * Get an HTML representation of all the form fields in this form
   *
   * Gives the HTML for every field in the order they are in the form's spec
   * with control groups and labels
   *
   * @return string HTML for all form fields
   */
  public function fields() {
    $o = '';
    foreach (array_keys($this->fieldSpecs()) as $f) {
      $o .= $this->fieldset($f);
    }
    return $o;
  }

  /**
   * Get an display-only HTML representation of all the fields in this form
   *
   * Gives the HTML for every field in the order they are in the form's spec
   * with control groups and labels, but using dt/dd instead of label/input
   *
   * @param string [$yes] Value to use for set boolean fields
   * @param string [$no] Value to use for unset boolean fields
   * @return string HTML for all fields
   */
  public function displayfields($yes = 'Yes', $no = 'No') {
    $o = '<dl>';
    foreach (array_keys($this->fieldSpecs()) as $f) {
      $o .= $this->displayfield($f, $yes, $no);
    }
    $o .= '</dl>';
    return $o;
  }
}
