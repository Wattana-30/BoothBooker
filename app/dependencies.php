<?php

declare(strict_types=1);

use App\Application\Settings\SettingsInterface;
use DI\ContainerBuilder;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Monolog\Processor\UidProcessor;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;


return function (ContainerBuilder $containerBuilder) {
    $containerBuilder->addDefinitions([
        'db' => function () {
            $servername = "151.106.124.154";
            $username = "u583789277_wag21";
            $password = "Coconut2567";
            $dbname = "u583789277_wag21";

            // สร้างการเชื่อมต่อ PDO
            $pdo = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

            return $pdo;
        },
    ]);
};
