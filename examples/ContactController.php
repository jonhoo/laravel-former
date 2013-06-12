<?php

use Former\Form;
use Illuminate\Support\MessageBag;

class ContactController extends Controller {

  public function getIndex() {
    return View::make('contact')->with('form', Form::fromFile(__DIR__ . '/contact.form.php'));
  }

  public function postIndex() {
    $form = Form::fromFile(__DIR__ . '/contact.form.php');
    $form->setInput(Input::all());

    $validator = $form->accept();
    if ($validator->fails()) {
      $form->setErrors($validator->messages());
      return View::make('contact')->with('form', $form);
    }

    $data = $validator->getData();

    // Get the label for an enum
    $data['found'] = $form->fieldSpec('found')['options'][$data['found']];

    // Parse date inputs
    $data['from_date'] = strtotime($data['from_date']);
    $data['to_date'] = strtotime($data['to_date']);

    if (!Mail::send('emails.contact', $data, function($message) use ($data) {
      $message->to('contact@example.com')
              ->replyTo($data['email'], $data['person'])
              ->subject('Contact form')
              ->setCharset('UTF-8');
    })) {
      $errors = new MessageBag();

      // global here can be anything that's not an actual field name
      $errors->add('global', 'Could not send your contact request to contact@example.com. Please try again later.');

      return View::make('contact')->with('form', $form)->with('errors', $errors);
    }

    return View::make('success');
  }

}
