<?php

declare(strict_types=1);

/**
 * NovaeZSEOBundle SEOController.
 *
 * @package   Novactive\Bundle\eZSEOBundle
 *
 * @author    Novactive <novaseobundle@novactive.com>
 * @copyright 2015 Novactive
 * @license   https://github.com/Novactive/NovaeZSEOBundle/blob/master/LICENSE MIT Licence
 */
namespace Novactive\Bundle\eZSEOBundle\Controller;

use DOMDocument;
use Ibexa\Bundle\Core\Controller;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class SEOController extends Controller
{
    #[Route(path: '/robots.txt', methods: ['GET'])]
    public function robots(): Response
    {
        $response = new Response();
        $response->setSharedMaxAge(86400);

        $robots = ['User-agent: *'];

        $robotsRules = $this->getConfigResolver()->getParameter('robots', 'nova_ezseo');
        $backwardCompatibleRules = $this->getConfigResolver()->getParameter('robots_disallow', 'nova_ezseo');

        if (\is_array($robotsRules['sitemap'])) {
            foreach ($robotsRules['sitemap'] as $sitemapRules) {
                foreach ($sitemapRules as $key => $value) {
                    if ('route' === $key) {
                        $url = $this->generateUrl($value, [], UrlGeneratorInterface::ABSOLUTE_URL);
                        $robots[] = 'Sitemap: ' . $url;
                    }

                    if ('url' === $key) {
                        $robots[] = 'Sitemap: ' . $value;
                    }
                }
            }
        }

        if (\is_array($robotsRules['allow'])) {
            foreach ($robotsRules['allow'] as $rule) {
                $robots[] = 'Allow: ' . $rule;
            }
        }

        if ('prod' !== $this->getParameter('kernel.environment')) {
            $robots[] = 'Disallow: /';
        }

        if (\is_array($robotsRules['disallow'])) {
            foreach ($robotsRules['disallow'] as $rule) {
                $robots[] = 'Disallow: ' . $rule;
            }
        }

        if (\is_array($backwardCompatibleRules)) {
            foreach ($backwardCompatibleRules as $backwardCompatibleRule) {
                $robots[] = 'Disallow: ' . $backwardCompatibleRule;
            }
        }

        $response->setContent(implode("\n", $robots));
        $response->headers->set('Content-Type', 'text/plain');

        return $response;
    }

    #[Route(path: '/google{key}.html', requirements: ['key' => '[a-zA-Z0-9]*'], methods: ['GET'])]
    public function googleVerif(string $key): Response
    {
        if ($this->getConfigResolver()->getParameter('google_verification', 'nova_ezseo') !== $key) {
            throw new NotFoundHttpException('Google Verification Key not found');
        }

        $response = new Response();
        $response->setSharedMaxAge(86400);
        $response->setContent(sprintf('google-site-verification: google%s.html', $key));

        return $response;
    }

    #[Route(path: '/BingSiteAuth.xml', methods: ['GET'])]
    public function bingVerif(): Response
    {
        if (!$this->getConfigResolver()->hasParameter('bing_verification', 'nova_ezseo')) {
            throw new NotFoundHttpException('Bing Verification Key not found');
        }

        $key = $this->getConfigResolver()->getParameter('bing_verification', 'nova_ezseo');

        $domDocument = new DOMDocument('1.0', 'UTF-8');
        $domDocument->formatOutput = true;

        $root = $domDocument->createElement('users');
        $root->appendChild($domDocument->createElement('user', $key));

        $domDocument->appendChild($root);

        $response = new Response($domDocument->saveXML());
        $response->setSharedMaxAge(86400);
        $response->headers->set('Content-Type', 'text/xml');

        return $response;
    }
}
