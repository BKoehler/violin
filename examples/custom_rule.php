<?php

require '../vendor/autoload.php';

use Violin\Violin;

$v = new Violin;

$v->addRuleMessage('isBanana', '{field} expects banana, found "{input}" instead.');

$v->addRule('isBanana', function($field, $value) {
    return $value === 'banana';
});

$v->validate([
    'name' => 'billy',
    'age' => 20
], [
    'name' => 'required|isBanana',
    'age' => 'required|int'
]);

if($v->valid()) {
    echo 'Valid!';
} else {
    echo '<pre>', var_dump($v->messages()->all()), '</pre>';
}
