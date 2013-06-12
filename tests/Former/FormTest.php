<?php

use Former\Form;

$specs = array(
  'notype' => array(),
  'text' => array('type' => 'text'),
  'date' => array('type' => 'date'),
  'phone' => array('type' => 'phone'),
  'short' => array('type' => 'short'),
  'number' => array('type' => 'number'),
  'bool' => array('type' => 'bool'),
  'email' => array('type' => 'email'),
  'password' => array('type' => 'password'),
  'wide' => array('type' => 'wide'),
  'long' => array('type' => 'long'),
  'date-before-after' => array('type' => 'date', 'validate' => array('after:2000-01-01','before:2001-01-01')),
  'confirm' => array('type' => 'email', 'validate' => array('confirmed')),
  'confirm_pos' => array('validate' => array('confirmed')),
  'confirm_pos_confirmation' => array(),
  'padto' => array('type' => 'number', 'padto' => 8),
  'enum' => array(
    'type' => 'enum',
    'options' => array (
      'left' => 'Left',
      'right' => 'Right'
    ),
    'default' => 'right'
  ),
  'enum-nodefault' => array(
    'type' => 'enum',
    'options' => array (
      'left' => 'Left',
      'right' => 'Right'
    )
  ),
);

class FormTest extends \PHPUnit_Framework_TestCase {
  protected static $nocopy = array('padto');
  protected static $novalue = array('bool', 'enum', 'long', 'password');
  protected static $form;
  public static function setUpBeforeClass() {
    global $specs;
    self::$form = new Form($specs);
  }

  public function tearDown() {
    self::$form->setInput(null);
    self::$form->setSource(null);
  }

  /**
   * @covers Form::fromFile
   */
  public function testFromFile() {
    $f = Form::fromFile('tests/fixtures/form.php');
    $this->assertNotNull($f->fieldSpec('field'));
  }

  // 'notype' => array(),
  public function testNoTypeIsText() {
    $this->assertEquals('text', self::$form->fieldSpec('notype')['type']);
  }

  public function testTypesKept() {
    $ts = array();
    foreach (self::$form->fieldSpecs() as $f => $spec) {
      $t = $spec['type'];
      if (!array_key_exists($t, $ts)) {
        $ts[] = $t;
        $f = self::$form->fieldSpec($f);
        $this->assertEquals($t, $f['type']);
      }
    }
  }

  public function testInputClasses() {
    $ts = array();
    foreach (self::$form->fieldSpecs() as $f => $spec) {
      $t = $spec['type'];
      if (!array_key_exists($t, $ts)) {
        $ts[] = $t;
        $o = self::$form->fieldset($f);
        $this->assertRegExp("/class=\"[^\"]*\binput-$t\b/", $o);
      }
    }
  }

  public function testTextType($t = 'text') {
    $o = self::$form->field($t);
    $this->assertStringMatchesFormat('%Atype="text"%A', $o);
  }

  // 'wide' => array('type' => 'wide'),
  public function testWideType() {
    return $this->testTextType('wide');
  }

  // 'short' => array('type' => 'short'),
  public function testShortType() {
    return $this->testTextType('wide');
  }

  // 'date' => array('type' => 'date'),
  public function testDateType() {
    $f = self::$form->fieldSpec('date');
    $o = self::$form->field('date');
    $this->assertEquals(array('date_format:Y-m-d'), $f['validate']);
    $this->assertStringMatchesFormat('%Atype="date"%A', $o);
  }

  // 'date-before-after' => array('validate' => array('after:2000-01-01','before:2001-01-01')),
  public function testDateBounds() {
    $f = self::$form->fieldSpec('date-before-after');
    $o = self::$form->field('date-before-after');
    $this->assertEquals(array('after:2000-01-01','before:2001-01-01', 'date_format:Y-m-d'), $f['validate']);
    $this->assertStringMatchesFormat('%Adata-before="2001-01-01"%A', $o);
    $this->assertStringMatchesFormat('%Adata-after="2000-01-01"%A', $o);
  }

  // 'phone' => array('type' => 'phone'),
  public function testPhoneType() {
    $f = self::$form->fieldSpec('phone');
    $o = self::$form->field('phone');
    $this->assertEquals(array('regex:/^\+?[\d ]+$/'), $f['validate']);
    $this->assertStringMatchesFormat('%Atype="tel"%A', $o);
  }

  // 'number' => array('type' => 'number'),
  public function testNumberType() {
    $f = self::$form->fieldSpec('number');
    $o = self::$form->field('number');
    $this->assertEquals(array('numeric'), $f['validate']);
    $this->assertStringMatchesFormat('%Atype="number"%A', $o);
  }

  // 'bool' => array('type' => 'bool'),
  public function testBoolType() {
    $o = self::$form->field('bool');
    $this->assertStringMatchesFormat('%Atype="checkbox"%A', $o);
  }

  // 'email' => array('type' => 'email'),
  public function testEmailType() {
    $f = self::$form->fieldSpec('email');
    $o = self::$form->field('email');
    $this->assertEquals(array('email'), $f['validate']);
    $this->assertStringMatchesFormat('%Atype="email"%A', $o);
  }

  // 'password' => array('type' => 'password'),
  public function testPasswordType() {
    $o = self::$form->field('password');
    $this->assertStringMatchesFormat('%Atype="password"%A', $o);
  }

  public function testPasswordNotInOutput() {
    self::$form->setInput(array('password' => 'test'));
    $o = self::$form->field('password');
    $this->assertStringMatchesFormat('%Atype="password"%A', $o);
    $this->assertStringNotMatchesFormat('%Avalue="test"%A', $o, "Password field outputs data");
  }

  public function testSourceAsOutput() {
    $ts = array();
    foreach (self::$form->fieldSpecs() as $f => $spec) {
      $t = $spec['type'];
      if (!in_array($t, self::$novalue) && !array_key_exists($t, $ts)) {
        if (isset($spec['isconfirm']) && $spec['isconfirm']) { continue; }
        if (in_array($f, self::$nocopy)) { continue; }
        $ts[] = $t;
        self::$form->setSource(array($f => 'test'));
        $o = self::$form->field($f);
        $this->assertStringMatchesFormat('%Avalue="test"%A', $o, "Source data not in output for type $t");
      }
    }
  }

  public function testSourceAsOutputLong() {
    self::$form->setSource(array('long' => 'test'));
    $o = self::$form->field('long');
    $this->assertStringMatchesFormat('%A>test</textarea>%A', $o, "Source data not in output for type long");
  }

  public function testSourceAsOutputBool() {
    self::$form->setSource(array('bool' => 'yes'));
    $o = self::$form->field('bool');
    $this->assertRegExp('/<input[^>]* checked\b/', $o, "Source data not in output for type bool");
  }

  public function testSourceAsOutputEnum() {
    self::$form->setSource(array('enum' => 'left'));
    $o = self::$form->field('enum');
    $this->assertRegExp('/<option[^>]* value="left"[^>]* selected\b/', $o, "Source data not in output for type enum");
    $this->assertNotRegExp('/<option[^>]* value="right"[^>]* selected\b/', $o, "Non-source data in output for type enum");
  }

  public function testInputAsOutput() {
    $ts = array();
    foreach (self::$form->fieldSpecs() as $f => $spec) {
      $t = $spec['type'];
      if (!in_array($t, self::$novalue) && !array_key_exists($t, $ts)) {
        if (isset($spec['isconfirm']) && $spec['isconfirm']) { continue; }
        if (in_array($f, self::$nocopy)) { continue; }
        $ts[] = $t;
        self::$form->setInput(array($f => 'test'));
        $o = self::$form->field($f);
        $this->assertStringMatchesFormat('%Avalue="test"%A', $o, "Input not in output for type $t");
      }
    }
  }

  public function testInputAsOutputLong() {
    self::$form->setInput(array('long' => 'test'));
    $o = self::$form->field('long');
    $this->assertStringMatchesFormat('%A>test</textarea>%A', $o, "Input data not in output for type long");
  }

  public function testInputAsOutputBool() {
    self::$form->setInput(array('bool' => 'yes'));
    $o = self::$form->field('bool');
    $this->assertRegExp('/<input[^>]* checked\b/', $o, "Input data not in output for type bool");
  }

  public function testInputAsOutputEnum() {
    self::$form->setInput(array('enum' => 'left'));
    $o = self::$form->field('enum');
    $this->assertRegExp('/<option[^>]* value="left"[^>]* selected\b/', $o, "Input data not in output for type enum");
    $this->assertNotRegExp('/<option[^>]* value="right"[^>]* selected\b/', $o, "Non-input data in output for type enum");
  }

  public function testInputOverSource() {
    $ts = array();
    foreach (self::$form->fieldSpecs() as $f => $spec) {
      $t = $spec['type'];
      if (!in_array($t, self::$novalue) && !array_key_exists($t, $ts)) {
        if (isset($spec['isconfirm']) && $spec['isconfirm']) { continue; }
        if (in_array($f, self::$nocopy)) { continue; }
        $ts[] = $t;
        self::$form->setSource(array($f => 'stest'));
        self::$form->setInput(array($f => 'itest'));
        $o = self::$form->field($f);
        $this->assertStringMatchesFormat('%Avalue="itest"%A', $o, "Input not taking presedence over source for type $t");
      }
    }
  }

  public function testInputOverOutputLong() {
    self::$form->setSource(array('long' => 'stest'));
    self::$form->setInput(array('long' => 'itest'));
    $o = self::$form->field('long');
    $this->assertStringMatchesFormat('%A>itest</textarea>%A', $o, "Input data not taking presedence over source for type long");
  }

  public function testInputOverOutputBool() {
    self::$form->setSource(array('bool' => 0));
    self::$form->setInput(array('bool' => 'yes'));
    $o = self::$form->field('bool');
    $this->assertRegExp('/<input[^>]* checked\b/', $o, "Input data not taking presedence over unset source for type bool");

    self::$form->setSource(array('bool' => 1));
    self::$form->setInput(array());
    $o = self::$form->field('bool');
    $this->assertNotRegExp('/<input[^>]* checked\b/', $o, "Input data not taking presedence over set source for type bool");
  }

  public function testInputOverOutputEnum() {
    self::$form->setSource(array('enum' => 'right'));
    self::$form->setInput(array('enum' => 'left'));
    $o = self::$form->field('enum');
    $this->assertRegExp('/<option[^>]* value="left"[^>]* selected\b/', $o, "Input data not taking presedence over source for type enum");
    $this->assertNotRegExp('/<option[^>]* value="right"[^>]* selected\b/', $o, "Non-input data taking presedence over source output for type enum");
  }

  // 'required' => array('validate' => array('required')),
  public function testRequiredAttribute() {
    $ts = array();
    foreach (self::$form->fieldSpecs() as $f => $spec) {
      $t = $spec['type'];
      if (!array_key_exists($t, $ts)) {
        $ts[] = $t;
        $f = new Form(array($t => array('validate' => array('required'))));
        $o = $f->field($t);
        $this->assertRegExp('/<[^>]+ required\b/', $o, "Required attribute not kept for type $t");
      }
    }
  }

  // 'confirm' => array('type' => 'email', 'validate' => array('confirmed')),
  public function testConfirmed() {
    $o = self::$form->fields();
    $n = 'name="confirm_confirmation"';
    $t = 'type="email"';
    $s = '[^>]+';
    $this->assertStringMatchesFormat("%A$n%A", $o, 'Confirmation field not added');
    $this->assertRegExp("/<input$s($n$s$t|$t$s$n)/", $o, 'Confirmation field does not retain type');

    $v = 'value="test"';
    self::$form->setInput(array('confirm_confirmation' => 'test'));
    $o = self::$form->fields();
    $this->assertNotRegExp("/<input$s($n$s$v|$v$s$n)/", $o, 'Confirmation field retains input value');
  }

  // 'confirm_pos' => array('validate' => array('confirmed')),
  // 'confirm_pos_confirmation' => array(),
  public function testConfirmPosition() {
    $o = self::$form->fields();
    $matches = array();
    preg_match_all('/name="([^"]+)"/', $o, $matches, PREG_PATTERN_ORDER);
    $matches = $matches[1];
    $confirm_pos = array_search('confirm_pos', $matches);
    $this->assertEquals('confirm_pos_confirmation', $matches[$confirm_pos+1], 'Explicity confirmation field not positioned correctly in output');
  }

  // 'padto' => array('type' => 'number', 'padto' => 8),
  public function testPadTo() {
    $o = self::$form->field('padto');
    $this->assertStringMatchesFormat('%Avalue=""%A', $o, 'Padto is padding empty values');
    $this->assertStringMatchesFormat('%Atype="text"%A', $o, 'Padded number fields should be changed to text');

    $v = '42';
    self::$form->setInput(array('padto' => $v));
    $o = self::$form->field('padto');
    $p = str_pad($v, self::$form->fieldSpec('padto')['padto'], '0', STR_PAD_LEFT);
    $this->assertStringMatchesFormat("%Avalue=\"$p\"%A", $o);
  }

  /*
  'enum' => array(
    'type' => 'enum',
    'options' => array (
      'left' => 'Left',
      'right' => 'Right'
    ),
    'default' => 'right'
  ),
  'enum-nodefault' => array(
    'type' => 'enum',
    'options' => array (
      'left' => 'Left',
      'right' => 'Right'
    )
  ),
   */
  public function testEnumType() {
    $o = self::$form->field('enum');
    $f = self::$form->fieldSpec('enum');

    $this->assertContains('in:left,right', $f['validate']);

    $matches = array();
    preg_match_all('/value="([^"]+)"[^>]*>([^<]*)<\/option>/', $o, $matches, PREG_PATTERN_ORDER);
    $values = $matches[1];
    $labels = $matches[2];

    $this->assertEquals(array_keys($f['options']), $values);
    $this->assertEquals(array_values($f['options']), $labels);
  }

  public function testEnumDefault() {
    $od = self::$form->field('enum');
    $ond = self::$form->field('enum-nodefault');
    $this->assertRegExp('/value="right"[^>]+selected/', $od);
    $this->assertNotRegExp('/value="left"[^>]+selected/', $od);
    $this->assertNotRegExp('/value="[^"]+"[^>]+selected/', $ond);
  }
}
