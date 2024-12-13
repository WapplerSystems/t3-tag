<?php

declare(strict_types=1);


namespace TYPO3\CMS\Backend\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Backend\Backend\Shortcut\ShortcutRepository;
use TYPO3\CMS\Backend\Backend\ToolbarItems\ShortcutToolbarItem;
use TYPO3\CMS\Backend\View\BackendViewFactory;
use TYPO3\CMS\Core\Http\HtmlResponse;
use TYPO3\CMS\Core\Http\JsonResponse;

/**
 * Controller for shortcut processing.
 *
 * @internal This class is a specific Backend controller implementation and is not considered part of the Public TYPO3 API.
 */
#[AsController]
class TagController
{
    public function __construct(
        protected readonly BackendViewFactory $backendViewFactory,
    ) {}


    /**
     * Creates a shortcut through an AJAX call.
     */
    public function addAction(ServerRequestInterface $request): ResponseInterface
    {
        $result = 'success';
        $responseCode = 201;
        $parsedBody = $request->getParsedBody();
        $routeIdentifier = $parsedBody['routeIdentifier'] ?? '';
        $arguments = $parsedBody['arguments'] ?? '';
        if ($routeIdentifier === '') {
            $result = 'missingRoute';
            $responseCode = 400;
        } elseif ($this->shortcutRepository->shortcutExists($routeIdentifier, $arguments)) {
            $result = 'alreadyExists';
            $responseCode = 200;
        } else {
            $shortcutName = $parsedBody['displayName'] ?? '';
            $success = $this->shortcutRepository->addShortcut($routeIdentifier, $arguments, $shortcutName);
            if (!$success) {
                $result = 'failed';
                $responseCode = 500;
            }
        }
        return new JsonResponse(['result' => $result], $responseCode);
    }

}
