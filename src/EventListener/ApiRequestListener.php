<?php

// src/EventListener/ApiRequestListener.php

namespace App\EventListener;

use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class ApiRequestListener
{
    private $session;

    public function __construct(SessionInterface $session)
    {
        $this->session = $session;
    }

    public function onKernelRequest(RequestEvent $event)
    {
        $request = $event->getRequest();

        if ($request->headers->has('X-Organization-Token')) {
            if ($this->session->has('session_id')) {
                $request->headers->set('X-Session-ID', $this->session->get('session_id'));
            }
        }
    }
}
