# Former
Former is a form library for Laravel that provides HTML5 form field types,
validation and various other convenient luxuries for modern app development.

## Notes on usage until examples are in place
Former will validate all data that is passed to it, but will not change the
array that is given to it. To avoid mass-assignment attacks and such, use

```php
$form = new Form(/* ... */);
$form->setInput(Input::all());
$validator = $form->accept();
if (!$validator->fails()) {
  $data = $validator->getData();
  // Work with $data, NOT Input::all()!
}
```

## Installing

- Install [Composer](http://getcomposer.org) and place the executable somewhere
  in your `$PATH` (for the rest of this README, I'll reference it as just
  `composer`)

- Add `jonhoo/former` to your project's `composer.json:

```json
{
    "require": {
        "jonhoo/former": "0.*"
    }
}
```

- Install/update your dependencies

```bash
$ cd my_project
$ composer install
```

And you're good to go! Have a look at the **example files** in `examples/` to
see how you might go about using Former.
