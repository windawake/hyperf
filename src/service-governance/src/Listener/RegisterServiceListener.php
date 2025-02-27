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
namespace Hyperf\ServiceGovernance\Listener;

use Hyperf\Consul\Exception\ServerException;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Contract\IPReaderInterface;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\Event\Contract\ListenerInterface;
use Hyperf\Framework\Event\MainWorkerStart;
use Hyperf\Server\Event\MainCoroutineServerStart;
use Hyperf\ServiceGovernance\DriverManager;
use Hyperf\ServiceGovernance\ServiceManager;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

class RegisterServiceListener implements ListenerInterface
{
    protected LoggerInterface $logger;

    protected ServiceManager $serviceManager;

    protected ConfigInterface $config;

    protected IPReaderInterface $ipReader;

    protected DriverManager $governanceManager;

    public function __construct(ContainerInterface $container)
    {
        $this->logger = $container->get(StdoutLoggerInterface::class);
        $this->serviceManager = $container->get(ServiceManager::class);
        $this->config = $container->get(ConfigInterface::class);
        $this->ipReader = $container->get(IPReaderInterface::class);
        $this->governanceManager = $container->get(DriverManager::class);
    }

    public function listen(): array
    {
        return [
            MainWorkerStart::class,
            MainCoroutineServerStart::class,
        ];
    }

    /**
     * @param MainCoroutineServerStart|MainWorkerStart $event
     */
    public function process(object $event)
    {
        $continue = true;
        while ($continue) {
            try {
                $services = $this->serviceManager->all();
                $servers = $this->getServers();
                foreach ($services as $serviceName => $serviceProtocols) {
                    foreach ($serviceProtocols as $paths) {
                        foreach ($paths as $service) {
                            if (! isset($service['publishTo'], $service['server'])) {
                                continue;
                            }
                            [$address, $port] = $servers[$service['server']];
                            if ($governance = $this->governanceManager->get($service['publishTo'])) {
                                if (! $governance->isRegistered($serviceName, $address, (int) $port, $service)) {
                                    $governance->register($serviceName, $address, (int) $port, $service);
                                }
                            }
                        }
                    }
                }
                $continue = false;
            } catch (ServerException $throwable) {
                if (str_contains($throwable->getMessage(), 'Connection failed')) {
                    $this->logger->warning('Cannot register service, connection of service center failed, re-register after 10 seconds.');
                    sleep(10);
                } else {
                    throw $throwable;
                }
            }
        }
    }

    protected function getServers(): array
    {
        $result = [];
        $servers = $this->config->get('server.servers', []);
        foreach ($servers as $server) {
            if (! isset($server['name'], $server['host'], $server['port'])) {
                continue;
            }
            if (! $server['name']) {
                throw new \InvalidArgumentException('Invalid server name');
            }
            $host = $server['host'];
            if (in_array($host, ['0.0.0.0', 'localhost'])) {
                $host = $this->ipReader->read();
            }
            if (! filter_var($host, FILTER_VALIDATE_IP)) {
                throw new \InvalidArgumentException(sprintf('Invalid host %s', $host));
            }
            $port = $server['port'];
            if (! is_numeric($port) || ($port < 0 || $port > 65535)) {
                throw new \InvalidArgumentException(sprintf('Invalid port %s', $port));
            }
            $port = (int) $port;
            $result[$server['name']] = [$host, $port];
        }
        return $result;
    }
}
