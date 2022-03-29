<?php
// define('_APP_ROOT', explode('/vendor/',__DIR__)[0]);

ini_set('display_errors', 'on');
ini_set('display_startup_errors', 'on');
ini_set('memory_limit', '1G');

error_reporting(E_ALL);

! defined('BASE_PATH') && define('BASE_PATH', explode('/vendor/',__DIR__)[0]);
! defined('SWOOLE_HOOK_FLAGS') && define('SWOOLE_HOOK_FLAGS', SWOOLE_HOOK_ALL);

require BASE_PATH . '/vendor/autoload.php';


// Self-called anonymous function that creates its own scope and keep the global namespace clean.
(function () {
    Hyperf\Di\ClassLoader::init();
    /** @var Psr\Container\ContainerInterface $container */
    $container = require BASE_PATH . '/config/container.php';

    $application = $container->get(Hyperf\Contract\ApplicationInterface::class);
    $application->run();
})();


// aa();

// function aa($value='')
// {
//     echo __DIR__."\n";
//     echo _APP_ROOT."\n";

//     // require _APP_ROOT."/bin/hyperf.php";
//     // echo shell_exec("sudo php "._APP_ROOT."/bin/hyperf.php");
// }




