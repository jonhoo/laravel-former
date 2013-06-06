<?php

namespace Former;

class Form {

  /**
   * Create a new form based on definitions from the given file
   * $messages may be an array of localized messages for Validate
   */
  public static function fromFile($file, $messages = null) {
    return new Form(require($file), $messages);
  }

  /**
   * Extract the field $f from the element $x, regardless of whether
   * it's an object or an array. Otherwise, return $otherwise
   */
  protected static function get($x, $f, $otherwise = null) {
    if (is_null($x)) {
      return $otherwise;
    }

    if (is_array($x) && array_key_exists($f, $x)) {
      return $x->$f;
    }

    if (is_object($x) && property_exists($x, $f)) {
      return $x->$f;
    }

    return $otherwise;
  }

  protected $_fields = null;
  protected $_messages = null;
  protected $_source = null;
  protected $_input = null;
  protected $_errors = null;

  private static $textAlias = array('wide', 'short', 'address');

  /**
   * Create a new form bsaed on the given fields
   *
   * $fields should be indexed by field name, and each field should be an array
   *   with any of the following indices:
   *   - type: text|wide|long|number|date|email|phone|bool
   *   - name: <human readable field name>
   *   - validate: <array of Validate rules>
   *   - padto: <left-pad with zeros to given length>
   *   - option: <for enum: array of value => title>
   *   - default: <for enum: default value>
   * $messages can be passed to internationalize the messages given by Validate
   */
  public function __construct($fields, $messages = null) {
    foreach ($fields as $f => $spec) {
      if (!array_key_exists('type', $spec)) {
        $fields[$f]['type'] = 'text';
      }

      if (!array_key_exists('validate', $spec)) {
        $fields[$f]['validate'] = array();
      }

      $fields[$f]['field'] = $f;
    }

    $this->_fields = $fields;
    $this->_messages = $messages;
  }

  public function setSource($source) {
    $this->_source = $source;
  }

  public function setInput($input) {
    $this->_input = $input;
  }

  public function setErrors(MessageBag $errors) {
    $this->_errors = $errors;
  }

  /**
   * Validate incoming data from this form based on the data given to setInput
   * Returns a Validate object
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
          $spec['validate'][] = 'regex:^\+?[\d ]+$';
          break;
        case 'bool':
          $trimmed = !empty($trimmed) ? "true" : "";
          break;
      }

      $validate[$field] = $spec['validate'];
      $data[$field] = $trimmed;
    }

    if (!is_null($this->_messages)) {
      return Validate::make($data, $validate, $this->_messages);
    }

    return Validate::make($data, $validate);
  }

  /**
   * Returns a form field for the given spec, showing the indicated value
   */
  protected static function _field($spec, $bestValue) {
    $ni = 'name="' . $spec['field'] . '" id="' . $spec['field'] . '"';
    $e = htmlspecialchars($bestValue);
    $r = in_array('required', $spec['validate']) ? 'required' : '';

    switch ($spec['type']) {
      case 'long':
        return "<textarea class=\"input-long\" $ni rows=\"10\" cols=\"40\" $r>$e</textarea>";
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

        return "<input type=\"$type\" $ni value=\"$e\" $r />";
    }
  }

  /**
   * Returns a full form element (with label and containers) for the given field
   * Data should have been set with setSource and setInput before calling this function.
   */
  protected function fieldset($field) {

    if (!array_key_exists($field, $this->_fields)) {
      return "";
    }

    $spec = $this->_fields[$field];
    $s = self::get($this->_source, $spec['field']);
    $i = self::get($this->_input, $spec['field']);
    $bestValue = is_null($i) ? $s : $i;

    $failed = !is_null($this->_errors) && $this->_errors->has($spec['field']);

    switch ($spec['type']) {
      case 'bool':
        if (!is_null($this->_input) && is_null($i)) {
          $bestValue = false;
        }
        break;
      case 'enum':
        if (empty($bestValue)) {
          $bestValue = $spec['default'];
        }
        break;
    }

    $o = "";
    $o .= '<div class="control-group input-' . $spec['type'] . ($failed ? ' error' : '') . '">';
      $o .= '<label class="control-label" for="' . $spec['field']. '">' . $spec['name'] . '</label>';
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
   * Return the input element for the given field
   */
  public function field($field) {
    if (!array_key_exists($field, $this->_fields)) {
      return "";
    }

    return self::_field($this->_fields[$field]);
  }

  public function fields() {
    $o = '';
    foreach (array_keys($this->_fields) as $f) {
      $o .= $this->fieldset($f);
    }
    return $o;
  }
}
