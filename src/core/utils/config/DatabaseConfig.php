<?php

namespace core\utils\config;

class DatabaseConfig {
    public string $host = "127.0.0.1";
    public string $username = "foo";
    public string $password = "bar";
    public string $schema = "swim";
    public int $port = 3306;
    /**
     * @config worker-limit
     */
    public int $workerLimit = 2;
}