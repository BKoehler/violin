<?php

namespace Violin;

use Closure;

use RecursiveArrayIterator;
use RecursiveIteratorIterator;

use Violin\Support\MessageBag;
use Violin\Contracts\ValidatorContract;

class Violin implements ValidatorContract
{
    /**
     * Rule objects that have already been instantiated.
     *
     * @var array
     */
    protected $usedRules = [];

    /**
     * Custom user-defined rules
     *
     * @var array
     */
    protected $customRules = [];

    /**
     * Collection of errors.
     *
     * @var array
     */
    public $errors = [];

    /**
     * Inputted fields and values.
     *
     * @var array
     */
    protected $input = [];

    /**
     * Rule messages.
     *
     * @var array
     */
    protected $ruleMessages = [
        'required'  => '{field} is required',
        'int'       => '{field} must be a number',
        'between'   => '{field} must be between {arg} and {arg:1}.',
        'matches'   => '{field} must match {arg}.',
        'alnumDash' => '{field} must be alphanumeric with dashes and underscores permitted.',
        'alnum'     => '{field} must be alphanumeric.',
        'alpha'     => '{field} must be alphabetic.',
        'array'     => '{field} must be an array.',
        'bool'      => '{field} must be a boolean.',
        'email'     => '{field} must be a valid email address.',
        'ip'        => '{field} must be a valid IP address.',
        'max'       => '{field} must be a maximum of {arg}',
        'min'       => '{field} must be a minimum of {arg}',
        'url'       => '{field} must be a valid URL.',
        'number'    => '{field} must be a number.',
        'date'      => '{field} must be a valid date.',
        'checked'   => 'You need to check the {field} field.',
        'regex'     => '{field} was not in the correct format.'
    ];

    /**
     * Field messages
     *
     * @var array
     */
    protected $fieldMessages = [];

    /**
     * The default format that errors should take, used
     * for replacing values in messages.
     *
     * @var array
     */
    public $format = ['{field}', '{value}', '{arg}'];

    /**
     * Kick off the validation using input and rules.
     *
     * @param  array  $input
     * @param  array  $rules
     *
     * @return void
     */
    public function validate(array $input, array $rules)
    {
        $this->clearErrors();

        $this->input = $input;

        foreach ($input as $field => $fieldRules) {
            $fieldRules = explode('|', $rules[$field]);

            foreach ($fieldRules as $rule) {
                $this->validateAgainstRule(
                    $field,
                    $input[$field],
                    $this->getRuleName($rule),
                    $this->getRuleArgs($rule)
                );
            }
        }
    }

    /**
     * Checks if validation has passed.
     *
     * @return bool
     */
    public function passes()
    {
        return empty($this->errors);
    }

    /**
     * Checks if validation has failed.
     *
     * @return bool
     */
    public function fails()
    {
        return !empty($this->errors);
    }

    /**
     * Gather errors, format them and return them.
     *
     * @return array
     */
    public function errors()
    {
        if ($this->passes()) {
            return null;
        }

        $messages = [];

        foreach ($this->errors as $rule => $items) {
            foreach ($items as $item) {
                $field = $item['field'];

                $message = $this->fetchMessage($field, $rule);

                $messages[$field][] = $this->replaceMessageFormat($message, $item);
            }
        }

        return new MessageBag($messages);
    }

    /**
     * Adds a custom rule message.
     *
     * @param string $rule
     * @param string $message
    */
    public function addRuleMessage($rule, $message)
    {
        $this->ruleMessages[$rule] = $message;
    }

    /**
     * Adds custom rule messages.
     *
     * @param array $messages
    */
    public function addRuleMessages(array $messages)
    {
        $this->ruleMessages = array_merge($this->ruleMessages, $messages);
    }

    /**
     * Adds a custom field message.
     *
     * @param string $field
     * @param string $rule
     * @param string $message
    */
    public function addFieldMessage($field, $rule, $message)
    {
        $this->fieldMessages[$field][$rule] = $message;
    }

    /**
     * Adds custom field messages
     *
     * @param array $messages
    */
    public function addFieldMessages(array $messages)
    {
        $this->fieldMessages = $messages;
    }

    /**
     * Add a custom rule
     *
     * @param string $name
     * @param Closure $callback
     */
    public function addRule($name, Closure $callback)
    {
        $this->customRules[$name] = $callback;
    }

    /**
     * Fetch the message for an error by field or rule type.
     *
     * @param  string $field
     * @param  string $rule
     *
     * @return string
     */
    protected function fetchMessage($field, $rule)
    {
        return isset($this->fieldMessages[$field][$rule])
                ? $this->fieldMessages[$field][$rule]
                : $this->ruleMessages[$rule];
    }

    /**
     * Replaces message variables.
     *
     * @param  string $message
     * @param  array $item
     *
     * @return string
     */
    protected function replaceMessageFormat($message, array $item)
    {
        $format = $this->format;

        if (!empty($item['args'])) {
            for ($i = 0; $i < count($item['args']); $i++) {
                $format[] = '{arg:' . ($i + 1) . '}';
            }
        }

        return str_replace(
            $format,
            $this->flattenArray($item),
            $message
        );
    }

    /**
     * Validates value against a specific rule and handles
     * errors if the rule validation fails.
     *
     * @param  string $field
     * @param  string $value
     * @param  string $rule
     * @param  array $args
     *
     * @return void
     */
    protected function validateAgainstRule($field, $value, $rule, array $args)
    {
        $ruleToCall = $this->getRuleToCall($rule);

        $passed = call_user_func_array($ruleToCall, [
            $value,
            $this->input,
            $args
        ]);

        if (!$passed) {
            $this->handleError($field, $value, $rule, $args);
        }
    }

    /**
     * Clears all previously stored errors.
     *
     * @return void
     */
    protected function clearErrors()
    {
        $this->errors = [];
    }

    /**
     * Stores an error.
     *
     * @param  string $field
     * @param  string $value
     * @param  string $rule
     * @param  array $args
     *
     * @return void
     */
    protected function handleError($field, $value, $rule, array $args)
    {
        $this->errors[$rule][] = [
            'field' => $field,
            'value' => $value,
            'args' => $args,
        ];
    }

    /**
     * Gets and instantiates a rule object, e.g. IntRule. If it has
     * already been used, it pulls from the stored rule objects.
     *
     * @param  string $rule
     *
     * @return mixed
     */
    protected function getRuleToCall($rule)
    {
        if (isset($this->customRules[$rule])) {
            return $this->customRules[$rule];
        }

        if (method_exists($this, 'validate_' . $rule)) {
            return [$this, 'validate_' . $rule];
        }

        if (isset($this->usedRules[$rule])) {
            return [$this->usedRules[$rule], 'run'];
        }

        $ruleClass = 'Violin\\Rules\\' . ucfirst($rule) . 'Rule';
        $ruleObject = new $ruleClass();

        $this->usedRules[$rule] = $ruleObject;

        return [$ruleObject, 'run'];
    }

    /**
     * Determine whether a rule has arguments.
     *
     * @param  string $rule
     *
     * @return bool
     */
    protected function ruleHasArgs($rule)
    {
        return (bool)preg_match("/.+\([a-zA-Z0-9,'\" _]+\)/", $rule);
    }

    /**
     * Get rule arguments.
     *
     * @param  string $rule
     *
     * @return array
     */
    protected function getRuleArgs($rule)
    {
        if (!$this->ruleHasArgs($rule)) {
            return [];
        }

        list($ruleName, $argsWithBracketAtTheEnd) = explode('(', $rule);

        $args = rtrim($argsWithBracketAtTheEnd, ')');
        $args = preg_replace('/\s+/', '', $args);
        $args = explode(',', $args);

        return $args;
    }

    /**
     * Gets a rule name.
     *
     * @param  string $rule
     *
     * @return string
     */
    protected function getRuleName($rule)
    {
        return explode('(', $rule)[0];
    }

    /**
     * Flatten an array.
     *
     * @param  array  $args
     *
     * @return array
     */
    protected function flattenArray(array $args)
    {
        return iterator_to_array(new RecursiveIteratorIterator(
            new RecursiveArrayIterator($args)
        ), false);
    }
}
