<?php

return array (
  'person'     => array('validate' => array('required')),
  'company'    => array(),
  'email'      => array('type' => 'email',  'validate' => array('required')),
  'from_date'  => array('type' => 'date',   'validate' => array('required', 'after:' . date('Y-m-d'))),
  'to_date'    => array('type' => 'date',   'validate' => array('required')),
  'num_adults' => array('type' => 'number', 'validate' => array('required')),
  'num_kids'   => array('type' => 'number', 'validate' => array('required')),
  'callback'   => array('type' => 'bool'),
  'found' =>  array (
    'type' => 'enum',
    'default' => 'friends',
    'options' => array (
      'friends' => "Friends",
      'google'  => "Google",
      'article' => "Newsarticle",
      'ad'      => "Advertisement",
      'other'   => "Something else"
    )
  ),
  // Any Validator checks can be used in the validate array
  'phone' => array('type' => 'phone', 'validate' => array('required_width:callback')),
  'other' => array('type' => 'long')
);
