<?php

namespace kissj\Middleware;

use kissj\FlashMessages\FlashMessagesInterface;
use kissj\User\User;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as ResponseHandler;
use Symfony\Contracts\Translation\TranslatorInterface;

class NonChoosedRoleOnlyMiddleware extends BaseMiddleware {
    private FlashMessagesInterface $flashMessages;
    private TranslatorInterface $translator;

    public function __construct(
        FlashMessagesInterface $flashMessages,
        TranslatorInterface $translator
    ) {
        $this->flashMessages = $flashMessages;
        $this->translator = $translator;
    }

    public function process(Request $request, ResponseHandler $handler): Response {
        /** @var User $user */
        $user = $request->getAttribute('user');

        if ($user->status !== User::STATUS_WITHOUT_ROLE) {
            $this->flashMessages->warning($this->translator->trans('flash.warning.roleChoosed'));

            $url = $this->getRouter($request)->urlFor('landing');
            $response = new \Slim\Psr7\Response();

            return $response->withHeader('Location', $url)->withStatus(302);
        }

        return $handler->handle($request);
    }
}
