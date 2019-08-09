<?php

use kissj\Orm\Mapper;
use kissj\Participant\Guest\GuestRepository;
use kissj\Participant\Ist\IstService;
use kissj\Participant\Patrol\PatrolLeaderRepository;
use kissj\Participant\Patrol\PatrolParticipantRepository;
use kissj\Participant\Patrol\PatrolService;
use kissj\Payment\PaymentRepository;
use kissj\User\LoginTokenRepository;
use kissj\User\UserRepository;
use kissj\User\UserService;
use Psr\Container\ContainerInterface as C;

$container = $app->getContainer();

$container['logger'] = function (C $c) {
    $settings = $c->get('settings')['logger'];
    $logger = new Monolog\Logger($settings['name']);
    $logger->pushProcessor(new Monolog\Processor\UidProcessor());
    $logger->pushHandler(new Monolog\Handler\StreamHandler($settings['path'], $settings['level']));

    return $logger;
};

$container['mailer'] = function (C $c) {
    $settings = $c->get('settings');

    return new \kissj\Mailer\PhpMailerWrapper($settings['mailer']);
};

$container['random'] = function (C $c) {
    return new \kissj\Random();
};

$container['banks'] = function (C $c) {
    return new \kissj\Payment\Banks();
};

// db
$container['db'] = function (C $c) {
    $path = $c->get('settings')['db']['path'];
    $connection = new LeanMapper\Connection([
        'driver' => 'sqlite3',
        'database' => $path,
    ]);

    return $connection;
};

// db_mapper
$container['dbMapper'] = function (C $c) {
    return new Mapper();
};

$container['dbFactory'] = function (C $c) {
    return new LeanMapper\DefaultEntityFactory;
};

// repositories
$container['userRepository'] = function (C $c) {
    return new UserRepository(
        $c->get('db'),
        $c->get('dbMapper'),
        $c->get('dbFactory')
    );
};

$container['tokenRepository'] = function (C $c) {
    return new LoginTokenRepository(
        $c->get('db'),
        $c->get('dbMapper'),
        $c->get('dbFactory')
    );
};

$container['guestRepository'] = function (C $c) {
    return new GuestRepository(
        $c->get('db'),
        $c->get('dbMapper'),
        $c->get('dbFactory')
    );
};

$container['istRepository'] = function (C $c) {
    return new GuestRepository(
        $c->get('db'),
        $c->get('dbMapper'),
        $c->get('dbFactory')
    );
};

$container['patrolParticipantRepository'] = function (C $c) {
    return new PatrolParticipantRepository(
        $c->get('db'),
        $c->get('dbMapper'),
        $c->get('dbFactory')
    );
};

$container['patrolLeaderRepository'] = function (C $c) {
    return new PatrolLeaderRepository(
        $c->get('db'),
        $c->get('dbMapper'),
        $c->get('dbFactory')
    );
};


$container['paymentRepository'] = function (C $c) {
    return new PaymentRepository(
        $c->get('db'),
        $c->get('dbMapper'),
        $c->get('dbFactory')
    );
};

$container['eventRepository'] = function (C $c) {
    return new \kissj\Event\EventRepository(
        $c->get('db'),
        $c->get('dbMapper'),
        $c->get('dbFactory')
    );
};

// services
$container['userRegeneration'] = function (C $c) {
    return new \kissj\User\UserRegeneration(
        $c->get('userRepository'),
        $_SESSION['user'] ?? []
    );
};

$container['exportService'] = function (C $c) {
    return new \kissj\Export\ExportService(
        $c->get('patrolParticipantRepository'),
        $c->get('patrolLeaderRepository'),
        $c->get('istRepository'),
        $c->get('roleRepository')
    );
};

$container['userService'] = function (C $c) {
    return new UserService(
        $c->get('userRepository'),
        $c->get('tokenRepository'),
        $c->get('mailer'),
        $c->get('router'),
        $c->get('random'),
        $c->get('settings')['eventName'],
        $c->get('view')
    );
};

$container['istService'] = function (C $c) {
    $eventSettings = $c->get('settings')['event'];

    return new IstService(
        $c->get('istRepository'),
        $c->get('roleRepository'),
        $c->get('paymentRepository'),
        $c->get('roleService'),
        $c->get('flashMessages'),
        $c->get('mailer'),
        $c->get('view'),
        $eventSettings);
};

$container['patrolService'] = function (C $c) {
    $eventSettings = $c->get('settings')['event'];

    return new PatrolService(
        $c->get('patrolParticipantRepository'),
        $c->get('patrolLeaderRepository'),
        $c->get('roleRepository'),
        $c->get('paymentRepository'),
        $c->get('roleService'),
        $c->get('flashMessages'),
        $c->get('mailer'),
        $c->get('view'),
        $eventSettings);
};


$container['paymentService'] = function (C $c) {
    $paymentsSettings = $c->get('settings')['paymentSettings'];

    return new \kissj\Payment\PaymentService(
        $paymentsSettings,
        $c->get('paymentRepository'),
        $c->get('paymentAutoMatcherFio'),
        $c->get('roleRepository'),
        $c->get('mailer'),
        $c->get('view'),
        $c->get('flashMessages'),
        $c->get('logger'),
        $c->get('settings')['eventName'],
        $c->get('random')
    );
};

$container['paymentMatcherService'] = function (C $c) {
    return new \kissj\PaymentImport\PaymentMatcherService(
        $c->get('paymentService'),
        $c->get('paymentRepository')
    );
};

$container['paymentAutoMatcherFio'] = function (C $c) {
    // using h4kuna/fio - https://github.com/h4kuna/fio
    $paymentSettings = $c->get('settings')['paymentSettings'];
    $fioFactory = new \h4kuna\Fio\Utils\FioFactory([
        'mainAccount' => [
            'account' => $paymentSettings['accountNumber'],
            'token' => $paymentSettings['fioApiToken'],
        ],
    ]);

    return $fioFactory->createFioRead('mainAccount');
};

$container['eventService'] = function (C $c) {
    return new \kissj\Event\EventService(
        $c->get('eventRepository')
    );
};

// views
$container['flashMessages'] = function (C $c) {
    return new kissj\FlashMessages\FlashMessagesBySession();
};

$container['view'] = function (C $c) {
    $rendererSettings = $c->get('settings')['renderer'];

    $view = new \Slim\Views\Twig($rendererSettings['templates_path'], [
        'cache' => $rendererSettings['enable_cache'] ? __DIR__.'/../temp/twig' : false,
    ]);

    // Instantiate and add Slim specific extension
    $uri = $c['request']->getUri();
    $basePath = rtrim(str_ireplace('index.php', '',
        $uri->getScheme().'://'.$uri->getHost().':'.$uri->getPort().$uri->getBasePath()), '/');

    // Add few elements for rendering
    $portString = '';
    $port = $uri->getPort();
    if ($port !== null) {
        $portString .= ':'.$port;
    }
    $baseHostScheme = $uri->getScheme().'://'.$uri->getHost().$portString;
    $view->addExtension(new \Slim\Views\TwigExtension($c['router'], $basePath));
    $view->getEnvironment()->addGlobal('baseHostScheme', $baseHostScheme);
    $view->getEnvironment()->addGlobal('flashMessages', $c['flashMessages']);
    /** @var \kissj\User\User $user */
    $user = $c['userRegeneration']->getCurrentUser();
    $view->getEnvironment()->addGlobal('user', $user);
    $view->getEnvironment()->addGlobal('userRole', $user->role);
    $view->getEnvironment()->addGlobal('userCustomHelp', $roleService->getHelpForRole($user->role));

    if ($c->get('settings')['useTestingSite']) {
        $flashMessages = $c->get('flashMessages');
        $flashMessages->info('Testovací verze - prosím nevkládej jakékoliv reálné osobní údaje!');
        $flashMessages->info('Login pro administraci: admin, heslo: admin, link: '.$c->get('router')
                ->pathFor('administration'));
    }

    return $view;
};
