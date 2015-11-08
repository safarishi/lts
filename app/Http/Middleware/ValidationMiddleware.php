<?php

namespace App\Http\Middleware;

use Input;
use Closure;
// use Validator;
use App\Exceptions\ValidationException;
use Illuminate\Validation\Factory as Validator;

class ValidationMiddleware
{

    /**
     * Validator
     *
     * @var Illuminate\Validation\Factory
     */
    protected $validator;

    /**
     * Validator rules
     * @var string|array
     */
    protected $rules = [];

    /**
     * the property name used in auto validate mode
     * @var string
     */
    public $propertyName = '_validate';

    public $controllerName = '';

    public $actionName = '';

    /**
     * validator constuct
     * @param Validator $validator Validator
     */
    public function __construct(Validator $validator)
    {
      $this->validator = $validator;
      // get and set property name from config file
      // if ($propertyName = Config::get('api::validation.property_name')) {
      //   $this->propertyName = $propertyName;
      // }
      // // get validation rules from config file
      // if ($rules = Config::get('api::validation.rules')) {
      //   $this->rules = $rules;
      // }
    }

    /**
     * Run the validation filter
     *
     * @internal param mixed $route, mixed $request
     * @return $response
     */
    public function filter($route, $request)
    {
      $str = $route->getAction()['uses'];
      $pos = strrpos($str, '\\');
      // $routeParam = explode('@', $route->getActionName());
      $routeParam = explode('@', substr($str, $pos + 1));
      $this->controllerName = $routeParam[0];
      $this->actionName = $routeParam[1];
      $this->setControllerRule();
// var_dump($this->getRules());exit;
      // get and check the validation rules used in this request
      if (!$rules = $this->getRules()) {
        var_dump($rules);
        var_dump(3);exit;
        return;
      }
// var_dump('c');exit;
      $validator = $this->validator->make($request->all(), $rules);
      if ($validator->fails()) {
        $messages = $validator->messages()->all();
        throw new ValidationException($messages);
      }
    }

    private function setControllerRule()
    {
      $this->controllerName = '\App\Http\Controllers\\'.$this->controllerName;
      // check controller is exists
      if (!class_exists($this->controllerName)) {
        return;
      }
      // use reflection class to get the property value
      $controllerRlection = new \ReflectionClass($this->controllerName);
      if (!$controllerRlection->hasProperty($this->propertyName)) {
        return;
      }
      $prop = $controllerRlection->getProperty($this->propertyName);
      $prop->setAccessible(true);
      $controllerRules = $prop->getValue();
      // var_dump($this->actionName, $controllerRules);exit;
      // var_dump($this->actionName, $controllerRules);exit;
      // var_dump(array_key_exists($this->actionName, $controllerRules));exit;
      if (!array_key_exists($this->actionName, $controllerRules)) {
        var_dump(3);exit;
        return;
      }
      $this->rules[$this->controllerName] = $controllerRules;
    }

    /**
     * get needed rules
     * @return mixed              validator rules
     */
    private function getRules()
    {
      // var_dump($this->rules);
      // var_dump(3);exit;
      return !empty($this->rules) ?
        $this->rules[$this->controllerName][$this->actionName] : null;
    }
}