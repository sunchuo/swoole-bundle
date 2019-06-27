<?php

declare(strict_types=1);

namespace K911\Swoole\Server\Config;

use Assert\Assertion;

final class ServerConfig
{
    private const SWOOLE_RUNNING_MODE_PROCESS = 'process';
    private const SWOOLE_RUNNING_MODE_REACTOR = 'reactor';
    private const SWOOLE_RUNNING_MODES = [
        self::SWOOLE_RUNNING_MODE_PROCESS => \SWOOLE_PROCESS,
        self::SWOOLE_RUNNING_MODE_REACTOR => \SWOOLE_BASE,
    ];

    private const CONFIG_REACTOR_COUNT = 'reactor_count';
    private const CONFIG_WORKER_COUNT = 'worker_count';
    private const CONFIG_TASK_WORKER_COUNT = 'task_worker_count';
    private const CONFIG_PUBLIC_DIR = 'public_dir';
    private const CONFIG_DAEMON_MODE = 'daemon_mode';
    private const CONFIG_STATIC_HANDLER = 'static_handler';

    private const RENAMED_CONFIGS = [
        self::CONFIG_REACTOR_COUNT => 'reactor_num',
        self::CONFIG_WORKER_COUNT => 'worker_num',
        self::CONFIG_TASK_WORKER_COUNT => 'task_worker_num',
        self::CONFIG_PUBLIC_DIR => 'document_root',
        self::CONFIG_DAEMON_MODE => 'daemonize',
        self::CONFIG_STATIC_HANDLER => 'enable_static_handler',
    ];

    private const SWOOLE_LOG_LEVEL_DEBUG = 'debug';
    private const SWOOLE_LOG_LEVEL_TRACE = 'trace';
    private const SWOOLE_LOG_LEVEL_INFO = 'info';
    private const SWOOLE_LOG_LEVEL_NOTICE = 'notice';
    private const SWOOLE_LOG_LEVEL_WARNING = 'warning';
    private const SWOOLE_LOG_LEVEL_ERROR = 'error';

    private const SWOOLE_LOG_LEVELS = [
        self::SWOOLE_LOG_LEVEL_DEBUG => SWOOLE_LOG_DEBUG,
        self::SWOOLE_LOG_LEVEL_TRACE => SWOOLE_LOG_TRACE,
        self::SWOOLE_LOG_LEVEL_INFO => SWOOLE_LOG_INFO,
        self::SWOOLE_LOG_LEVEL_NOTICE => SWOOLE_LOG_NOTICE,
        self::SWOOLE_LOG_LEVEL_WARNING => SWOOLE_LOG_WARNING,
        self::SWOOLE_LOG_LEVEL_ERROR => SWOOLE_LOG_ERROR,
    ];

    private const CONFIG_HANDLERS = [
        self::CONFIG_PUBLIC_DIR => 'validatePublicDir',
    ];

    /**
     * @var array
     */
    private $config;

    public function __construct(string $runningMode = self::SWOOLE_RUNNING_MODE_PROCESS, array $config = [])
    {
        Assertion::inArray($runningMode, self::SWOOLE_RUNNING_MODES);
        $this->add($config);
    }

    public function add(array $config): void
    {
        $errorBag = [];

        foreach ($config as $key => $value) {
            $key = \mb_strtolower($key);

            if (\array_key_exists($key, self::CONFIG_HANDLERS)) {
                try {
                    $method = self::CONFIG_HANDLERS[$key];
                    Assertion::methodExists($method, $this);
                    $value = $this->$method($value, $key);
                } catch (\Throwable $err) {
                    $errorBag[] = $err;
                }
            }

            $this->config[$key] = $value;
        }

        if (!empty($errorBag)) {
            throw new \RuntimeException(\sprintf('Configuration errors have occurred: %s', \implode(', ', \array_map(function (\Throwable $err): string {
                return $err->getMessage();
            }, $errorBag))));
        }
    }

    public function config(): array
    {
        return $this->transformConfig($this->config);
    }

    private function transformConfig(array $config): array
    {
        $renamed = [];
        $original = [];
        foreach ($config as $key => $value) {
            if (\array_key_exists($key, self::RENAMED_CONFIGS)) {
                $renamed[self::RENAMED_CONFIGS[$key]] = $renamed;
            } else {
                $original[$key] = $value;
            }
        }

        return \array_merge($renamed, $original);
    }
}
