<?php

/*
 * This file is part of the Behat.
 * (c) Konstantin Kudryashov <ever.zet@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Behat\Behat\Context\Environment;

use Behat\Behat\Context\Context;
use Behat\Behat\Context\Environment\Handler\ContextEnvironmentHandler;
use Behat\Behat\Context\Exception\ContextNotFoundException;
use Behat\Behat\HelperContainer\Environment\ServiceContainerEnvironment;
use Behat\Testwork\Call\Callee;
use Behat\Testwork\Suite\Suite;
use Psr\Container\ContainerInterface;

/**
 * Context environment based on a list of instantiated context objects.
 *
 * @see ContextEnvironmentHandler
 *
 * @author Konstantin Kudryashov <ever.zet@gmail.com>
 */
final class InitializedContextEnvironment implements ContextEnvironment, ServiceContainerEnvironment
{
    /**
     * @var Suite
     */
    private $suite;

    private ?ContainerInterface $serviceContainer = null;

    /**
     * @var array<class-string<Context>, Context>
     *
     * TODO use a class-string-map type to have an accurate type once https://github.com/phpstan/phpstan/issues/9521 is implemented
     */
    private $contexts = [];

    /**
     * Initializes environment.
     */
    public function __construct(Suite $suite)
    {
        $this->suite = $suite;
    }

    /**
     * Registers context instance in the environment.
     */
    public function registerContext(Context $context)
    {
        $this->contexts[get_class($context)] = $context;
    }

    public function setServiceContainer(?ContainerInterface $container = null)
    {
        $this->serviceContainer = $container;
    }

    public function getSuite()
    {
        return $this->suite;
    }

    public function hasContexts()
    {
        return count($this->contexts) > 0;
    }

    public function getContextClasses()
    {
        return array_keys($this->contexts);
    }

    public function hasContextClass($class)
    {
        return $class ? (bool) $this->resolveContextClass($class) : false;
    }

    /**
     * Resolves the specified class to a registered context.
     *
     * Will return an instance of the specified class if it is registered directly,
     * or any registered context that extends/implements the specified class/interface.
     *
     * @param  string       $class the fully qualified class name.
     * @return Context|null The context class, or null if no matching context was found.
     */
    public function resolveContextClass($class) {
        if (isset($this->contexts[$class])) {
            return $this->contexts[$class];
        }

        foreach ($this->contexts as $context) {
            if ($context instanceof $class) {
                return $context;
            }
        }

        return null;
    }

    /**
     * Returns list of registered context instances.
     *
     * @return list<Context>
     */
    public function getContexts()
    {
        return array_values($this->contexts);
    }

    /**
     * Returns registered context by its class name.
     *
     * @template T of Context
     *
     * @param class-string<T> $class
     *
     * @return T
     *
     * @throws ContextNotFoundException If context is not in the environment
     */
    public function getContext($class)
    {
        if (!$context = $this->resolveContextClass($class)) {
            throw new ContextNotFoundException(sprintf(
                '`%s` context is not found in the suite environment. Have you registered it?',
                $class
            ), $class);
        }

        return $context;
    }

    public function getServiceContainer()
    {
        return $this->serviceContainer;
    }

    public function bindCallee(Callee $callee)
    {
        $callable = $callee->getCallable();

        if ($callee->isAnInstanceMethod() && is_array($callable)) {
            return [$this->getContext($callable[0]), $callable[1]];
        }

        return $callable;
    }
}
