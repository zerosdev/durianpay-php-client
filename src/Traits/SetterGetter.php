<?php

namespace ZerosDev\Durianpay\Traits;

use Closure;
use Exception;
use ReflectionFunction;
use InvalidArgumentException;
use BadMethodCallException;
use ZerosDev\Durianpay\Constant;

trait SetterGetter
{
	public function __call(string $method, $args = []) {
		$baseNamespace = str_replace('\Traits', '', __NAMESPACE__);
		$type = strtolower(substr($method, 0, 3));
		$property = substr($method, 3);
		$property = preg_replace('/(?<!^)([A-Z])/', '_\\1', $property);
		$property = strtolower($property);

		if (strlen($property) === 0) {
			throw new BadMethodCallException("Call to undefined method ".get_called_class()."::".$method."()");
		}

		switch ($type) {
			case "set":
				if (! isset($args[0])) {
					$this->{$property} = null;
				} else {
					if ($args[0] instanceof Closure) {
						$params = (new ReflectionFunction($args[0]))->getParameters();
						$binding = $params[0];
						$class = $binding->getClass();
						$class = $class ? $class->getName() : "";
						if (! preg_match('/^'.preg_quote($baseNamespace.'\\Components\\').'/is', $class)) {
							throw new InvalidArgumentException('Parameter $'.$binding->getName().' passed to '.get_called_class().'::'.$method.'(function(...)) must be type hint of component class');
						}
						$object = new $class();
						$args[0]($object);
						$this->{$property} = $object;
					} else {
						$this->{$property} = $args[0];
					}
				}
				return $this;
				break;

			case "get":
				if (property_exists($this, $property)) {
					if (! isset($args[0]) || ! is_object($this->{$property})) {
						return $this->{$property};
					}

					switch ($args[0]) {
						case Constant::ARRAY:
							if (! method_exists($this->{$property}, 'toArray')) {
								throw new BadMethodCallException('Call to undefined method '.get_class($this->{$property}).'::toArray()');
							}
							return $this->{$property}->toArray();
							break;

						case Constant::JSON:
							if (! method_exists($this->{$property}, 'toJson')) {
								throw new BadMethodCallException('Call to undefined method '.get_class($this->{$property}).'::toJson()');
							}
							return $this->{$property}->toJson();
							break;

						default:
							throw new InvalidArgumentException('Unsupported serialization method passed to '.get_called_class().'::'.$method.'()');
							break;
					}
				}
				return null;
				break;

			case "add":
				if (! property_exists($this, $property)) {
					$this->{$property} = [];
				}

				if (is_array($args[0])) {
					if (isset($args[1]) && $args[1] === Constant::ARRAY_MERGE) {
						$this->{$property} = array_merge($this->{$property}, $args[0]);
					} else {
						$this->{$property}[] = $args[0];
					}
				} else {
					$this->{$property}[$args[0]] = isset($args[1]) ? $args[1] : null;
				}
				return $this;
				break;

			default:
				$class = preg_replace_callback("/_[a-z]?/", function($matches) {
					return strtoupper(ltrim($matches[0], "_"));
				}, $method);
				$fullclass = $baseNamespace.'\\Services\\'.ucfirst($class);
				if (class_exists($fullclass)) {
					return new $fullclass($this);
				}
				throw new BadMethodCallException("Call to undefined method ".get_called_class()."::".$method."(), class ".$fullclass." does not exists");
				break;
		}
	}

	public function __get(string $property) {
		if (property_exists($this, $property)) {
			return $this->{$property};
		}
		return null;
	}

	public function properties() {
		return get_object_vars($this);
	}
}