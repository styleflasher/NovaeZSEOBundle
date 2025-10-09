<?php

declare(strict_types=1);

/**
 * NovaeZSEOBundle Bundle.
 *
 * @package   Novactive\Bundle\eZSEOBundle
 *
 * @author    Novactive <novaseobundle@novactive.com>
 * @copyright 2015 Novactive
 * @license   https://github.com/Novactive/NovaeZSEOBundle/blob/master/LICENSE MIT Licence
 */
namespace Novactive\Bundle\eZSEOBundle\Core;

use Ibexa\Contracts\Core\Repository\URLWildcardService;
use Ibexa\Core\MVC\Symfony\Routing\UrlAliasRouter;
use Ibexa\Core\MVC\Symfony\Routing\UrlWildcardRouter as BaseUrlWildcardRouter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;

class UrlWildcardRouter extends BaseUrlWildcardRouter
{
    private ?\Ibexa\Contracts\Core\Repository\URLWildcardService $urlWildcardService = null;

    public function setWildcardService(URLWildcardService $urlWildcardService): void
    {
        $this->urlWildcardService = $urlWildcardService;
    }

    #[\Override]
    public function matchRequest(Request $request): array
    {
        try {
            // Manage full url : http://host.com/uri
            $requestedPath = $request->getPathInfo();
            $requestUriFull = $request->getSchemeAndHttpHost().$requestedPath;
            $urlWildcard = $this->urlWildcardService->translate($requestUriFull);
        } catch (\Exception $exception) {
            try {
                // Manage full url : /uri
                $urlWildcard = $this->urlWildcardService->translate($requestedPath);
            } catch (\Exception $exception) {
                throw new ResourceNotFoundException($exception->getMessage(), $exception->getCode(), $exception);
            }
        }

        $params = [
            '_route' => UrlAliasRouter::URL_ALIAS_ROUTE_NAME,
        ];

        if (str_starts_with((string) $urlWildcard->uri, 'http://') || str_starts_with((string) $urlWildcard->uri, 'https://')) {
            $params += ['semanticPathinfo' => trim((string) $urlWildcard->uri, '/')];
            $params += ['prependSiteaccessOnRedirect' => false];
        } else {
            $params += ['semanticPathinfo' => '/'.trim((string) $urlWildcard->uri, '/')];
        }

        // In URLAlias terms, "forward" means "redirect".
        if ($urlWildcard->forward) {
            $params += ['needsRedirect' => true];
        } else {
            $params += ['needsForward' => true];
        }

        return $params;
    }
}
