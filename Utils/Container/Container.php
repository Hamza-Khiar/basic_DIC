<?php

declare(strict_types=1);

namespace Architect\Container;

use Architect\Interfaces\ContainerInterface;
use Exception;
use ReflectionClass;
use ReflectionParameter;

require __DIR__ . '../../helpers_func.php';



class Container implements ContainerInterface
{
    private static $instance;
    // the container instance
    protected $bindings = [];  // this where we bind interfaces/abstractClasses to ConcreteClasses
    protected $instances = []; // this is where all the instantiated instances will live; for the lifecycle of the app
    protected array $map;

    /**
     * determining functionality:
     *      -a generateMap method that will basically makes tree-like associative array to make the build 
     *      -a methodResolver method to resolve all of the class's method dependencies
     */
    public static function Instance()
    {
        if (!isset(static::$instance)) {
            return static::$instance = new static;
        }
        return static::$instance;
    }
    public function get($id): string|object
    {
        if (!$this->has($id)) {
            // if it's not in the Container's cache; we need to determine if it's an interface of an abstract class
            return $this->resolve($id);
        } else {
            // checks if it's already an instance if not ; resolve the binding
            if (array_key_exists($id, $this->instances) && $this->instances[$id] instanceof $id) return $this->instances[$id];
            return $this->resolve($this->bindings[$id]);
        }
    }

    public function has($id): bool
    {
        return isset($this->bindings[$id]) || isset($this->instances[$id]);
    }
    public function bind($abstract, $concrete)
    {
        return $this->bindings[$abstract] = $concrete;
    }
    public function bound($abstract): bool
    {
        return isset($this->bindings[$abstract]);
    }
    public function resolve($id)
    {
        /*
        - two jobs : check what's been asked is either an interface or an abstract class; if it is check if concrete is not null so you can bind it ; else throw an exception that it needs a binding to make the concrete instance 
         */
        $mainReflector = new ReflectionClass($id);
        if (!$mainReflector->isInstantiable()) {
            //if it's not instantiable; im checking if there is any bindings in the bindings[]
            if (array_key_exists($id, $this->bindings)) {
                return $this->resolve($this->bindings[$id]);
            }
            return throw new Exception("can't instantiate the " . $mainReflector->getName() . ", it's of type Interface | Abstract Class");
        }
        //  this returns a list of parameters | a new object 
        $parameters = $this->getConstructorParams($mainReflector, $id);
        if ($parameters == $id) {

            $this->bindings[$id] = $id;
            $this->instances[$id] = new $id;
            return $this->instances[$id];
        }
        if (is_array($parameters)) {
            $this->instances[$id] = $this->ResolveDependencies($parameters, $id, $mainReflector);
            return $this->instances[$id];
        }
    }
    public function getConstructorParams(ReflectionClass $mainReflector, $id)
    {
        $constructor = $mainReflector->getConstructor();
        if (!$constructor) {
            // if there is no constructor; instantiate the class add it to instances[]
            $instance = new $id;
            $this->instances[$id] = $instance;
            return $id; // idk if i should return an instance or a classString
        }
        $parameters = $constructor->getParameters();
        if (!$parameters) {
            $instance = new $id;
            $this->instances[$id] = $instance;
            return $id; // idk if i should return an instance or a classString
        }
        return $parameters;
    }
    public function ResolveDependencies(array $parameters, $id, ReflectionClass $mainReflector)
    {
        $dependencies = array_map(function (ReflectionParameter $parameter) use ($id) {
            $name = $parameter->getName();
            $type = $parameter->getType();
            if (!$type) {
                throw new Exception(
                    "the Class $id failed to resolve because $name is missing type-hintiting"
                );
            }
            if ($type instanceof \ReflectionUnionType) {
                throw new Exception(
                    "can't resolve class $id with union Types"
                );
            }
            if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
                $this->bindings[$id] = $id;
                return $this->instances[$id] = $this->get($type->getName());
            }
        }, $parameters);
        return $mainReflector->newInstanceArgs($dependencies);
    }
    public function getProp()
    {
        array_disp($this->instances, $this->bindings);
    }
}

// return new Container;