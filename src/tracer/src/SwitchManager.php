<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */
namespace Hyperf\Tracer;

use Hyperf\Utils\Context;
use OpenTracing\Span;

class SwitchManager
{
    /**
     * @var array
     */
    private $config
        = [
            'guzzle' => false,
            'redis' => false,
            'db' => false,
            // beta feature, please don't enable 'method' in production environment
            'method' => false,
            'error' => false,
        ];

    /**
     * Apply the configuration to SwitchManager.
     */
    public function apply(array $config): void
    {
        $this->config = array_replace($this->config, $config);
    }

    /**
     * Determine if the tracer is enabled ?
     */
    public function isEnable(string $identifier): bool
    {
        if (! isset($this->config[$identifier])) {
            return false;
        }

        return $this->config[$identifier] && Context::get('tracer.root') instanceof Span;
    }
}
