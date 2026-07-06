<?php

declare(strict_types=1);

namespace Wazum\LiveReload\Fluid;

use ReflectionClass;
use ReflectionObject;
use Throwable;
use TYPO3\CMS\Fluid\Core\ViewHelper\ViewHelperResolver;
use TYPO3Fluid\Fluid\Core\ViewHelper\ViewHelperInterface;
use Wazum\LiveReload\Collector\RenderedFileCollector;

final class CapturingViewHelperResolver extends ViewHelperResolver
{
    private ?RenderedFileCollector $collector = null;

    public static function fromExisting(ViewHelperResolver $source, RenderedFileCollector $collector): self
    {
        $instance = (new ReflectionClass(self::class))->newInstanceWithoutConstructor();
        $reflection = new ReflectionObject($source);
        foreach ($reflection->getProperties() as $property) {
            $property->setValue($instance, $property->getValue($source));
        }
        $instance->collector = $collector;

        return $instance;
    }

    /**
     * @param string $viewHelperClassName
     */
    public function createViewHelperInstanceFromClassName($viewHelperClassName): ViewHelperInterface
    {
        $this->capture($viewHelperClassName);

        return parent::createViewHelperInstanceFromClassName($viewHelperClassName);
    }

    private function capture(string $viewHelperClassName): void
    {
        if (!$this->collector instanceof RenderedFileCollector || !class_exists($viewHelperClassName)) {
            return;
        }
        try {
            $file = (new ReflectionClass($viewHelperClassName))->getFileName();
        } catch (Throwable) {
            return;
        }
        if (is_string($file)) {
            $this->collector->add($file);
        }
    }
}
