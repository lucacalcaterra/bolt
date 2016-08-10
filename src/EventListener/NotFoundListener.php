<?php
namespace Bolt\EventListener;

use Bolt\Controller\Zone;
use Bolt\Legacy\Content;
use Bolt\Legacy\Storage;
use Bolt\Render;
use Bolt\TemplateChooser;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Twig_Environment as TwigEnvironment;
use Twig_Error_Loader as TwigErrorLoader;

/**
 * Renders the not found page in the event of an HTTP exception
 *
 * @author Carson Full <carsonfull@gmail.com>
 */
class NotFoundListener implements EventSubscriberInterface
{
    /** @var string */
    protected $notFoundPage;
    /** @var Storage */
    protected $storage;
    /** @var TemplateChooser */
    protected $templateChooser;
    /** @var TwigEnvironment */
    private $twig;
    /** @var Render */
    protected $render;

    /**
     * NotFoundListener constructor.
     *
     * @param string          $notFoundPage
     * @param Storage         $storage
     * @param TemplateChooser $templateChooser
     * @param TwigEnvironment $twig
     * @param Render          $render
     */
    public function __construct($notFoundPage, Storage $storage, TemplateChooser $templateChooser, TwigEnvironment $twig, Render $render)
    {
        $this->notFoundPage = $notFoundPage;
        $this->storage = $storage;
        $this->templateChooser = $templateChooser;
        $this->twig = $twig;
        $this->render = $render;
    }

    /**
     * Render the not found page if on frontend and http exception
     *
     * @param GetResponseForExceptionEvent $event
     */
    public function onKernelException(GetResponseForExceptionEvent $event)
    {
        $exception = $event->getException();
        if (!$exception instanceof HttpExceptionInterface || Zone::isBackend($event->getRequest())) {
            return;
        }
        if ($exception->getStatusCode() !== Response::HTTP_NOT_FOUND) {
            return;
        }

        // If $notFoundPage is referencing a template, render it and be done.
        if ($this->render->hasTemplate($this->notFoundPage)) {
            try {
                $html = $this->twig->render($this->notFoundPage);
                $response = new Response($html, Response::HTTP_NOT_FOUND);
                $event->setResponse($response);

                return;
            } catch (TwigErrorLoader $e) {
                // Template not found, fall though to see if we can render a
                // record, failing that let the exception handler take over
            }
        }

        // Next try for referencing DB content.
        $content = $this->storage->getContent($this->notFoundPage, ['returnsingle' => true]);
        if (!$content instanceof Content || empty($content->id)) {
            return;
        }

        $template = $this->templateChooser->record($content);
        $html = $this->twig->render($template, $content->getTemplateContext());
        $response = new Response($html);

        $event->setResponse($response);
    }

    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::EXCEPTION => ['onKernelException', 512],
        ];
    }
}
