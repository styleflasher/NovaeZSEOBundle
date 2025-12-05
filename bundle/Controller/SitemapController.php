<?php

declare(strict_types=1);

/**
 * NovaeZSEOBundle SitemapController.
 *
 * @package   Novactive\Bundle\eZSEOBundle
 *
 * @author    Novactive <novaseobundle@novactive.com>
 * @copyright 2015 Novactive
 * @license   https://github.com/Novactive/NovaeZSEOBundle/blob/master/LICENSE MIT Licence
 */
namespace Novactive\Bundle\eZSEOBundle\Controller;

use DateTime;
use DOMDocument;
use Ibexa\Bundle\Core\Controller;
use Ibexa\Contracts\Core\Repository\Values\Content\Location;
use Ibexa\Contracts\Core\Repository\Values\Content\Search\SearchResult;
use Ibexa\Contracts\Core\Variation\VariationHandler;
use Ibexa\Core\Helper\FieldHelper;
use Ibexa\Core\MVC\Symfony\Routing\UrlAliasRouter;
use Novactive\Bundle\eZSEOBundle\Core\Sitemap\QueryFactory;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class SitemapController extends Controller
{
    /**
     * How many in a Sitemap.
     *
     * @var int
     */
    public const PACKET_MAX = 1000;

    public function __construct(
        protected readonly FieldHelper $fieldHelper,
        protected readonly VariationHandler $imageVariationService,
    ) {
    }

    #[Route(path: '/sitemap.xml', name: '_novaseo_sitemap_index', methods: ['GET'])]
    public function index(QueryFactory $queryFactory): Response
    {
        $searchService = $this->getRepository()->getSearchService();
        $locationQuery = $queryFactory();
        $locationQuery->limit = 0;

        $resultCount = $searchService->findLocations($locationQuery)->totalCount;

        // Dom Doc
        $domDocument = new DOMDocument('1.0', 'UTF-8');
        $domDocument->formatOutput = true;

        // create an index if we are greater than th PACKET_MAX
        if ($resultCount > static::PACKET_MAX) {
            $root = $domDocument->createElement('sitemapindex');
            $root->setAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');
            $domDocument->appendChild($root);

            $this->fillSitemapIndex($domDocument, $resultCount, $root);
        } else {
            // if we are less or equal than the PACKET_SIZE, redo the search with no limit and list directly the urlmap
            $locationQuery->limit = $resultCount;
            $results = $searchService->findLocations($locationQuery);
            $root = $domDocument->createElement('urlset');
            $root->setAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');
            $root->setAttribute('xmlns:image', 'http://www.google.com/schemas/sitemap-image/1.1');
            $this->fillSitemap($domDocument, $root, $results);
            $domDocument->appendChild($root);
        }

        $response = new Response($domDocument->saveXML(), \Symfony\Component\HttpFoundation\Response::HTTP_OK, ['Content-type' => 'text/xml']);
        $response->setSharedMaxAge(86400);

        return $response;
    }

    #[Route(path: '/sitemap-{page}.xml', name: '_novaseo_sitemap_page', requirements: ['page' => '\\d+'], defaults: ['page' => 1], methods: ['GET'])]
    public function page(QueryFactory $queryFactory, int $page = 1): Response
    {
        $domDocument = new DOMDocument('1.0', 'UTF-8');
        $root = $domDocument->createElement('urlset');
        $domDocument->formatOutput = true;
        $root->setAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');
        $root->setAttribute('xmlns:image', 'http://www.google.com/schemas/sitemap-image/1.1');

        $domDocument->appendChild($root);
        $locationQuery = $queryFactory();
        $locationQuery->limit = static::PACKET_MAX;
        $locationQuery->offset = static::PACKET_MAX * ($page - 1);

        $searchService = $this->getRepository()->getSearchService();

        $searchResult = $searchService->findLocations($locationQuery);
        $this->fillSitemap($domDocument, $root, $searchResult);

        $response = new Response($domDocument->saveXML(), \Symfony\Component\HttpFoundation\Response::HTTP_OK, ['Content-type' => 'text/xml']);
        $response->setSharedMaxAge(86400);

        return $response;
    }

    /**
     * Fill a sitemap.
     */
    protected function fillSitemap(DOMDocument $domDocument, \DOMElement $domElement, SearchResult $searchResult): void
    {
        foreach ($searchResult->searchHits as $searchHit) {
            /** @var Location $location */
            $location = $searchHit->valueObject;

            try {
                $url = $this->generateUrl(
                    UrlAliasRouter::URL_ALIAS_ROUTE_NAME,
                    ['locationId' => $location->id],
                    UrlGeneratorInterface::ABSOLUTE_URL
                );
            } catch (\Exception $exception) {
                if ($this->has('logger')) {
                    $this->get('logger')->error('NovaeZSEO: '.$exception->getMessage());
                }

                continue;
            }

            if (0 != strpos($url, 'view/content/')) {
                continue;
            }

            $modified = $location->contentInfo->modificationDate ?
                $location->contentInfo->modificationDate->format('c') : null;
            $loc = $domDocument->createElement('loc', $url);
            $lastmod = $domDocument->createElement('lastmod', $modified);
            $urlElt = $domDocument->createElement('url');

            // Inject the image tags if config is enabl

            $displayImage = $this->getConfigResolver()->getParameter('display_images_in_sitemap', 'nova_ezseo');
            if (true === $displayImage) {
                $content = $this->getRepository()->getContentService()->loadContentByContentInfo(
                    $location->contentInfo
                );
                foreach ($content->getFields() as $field) {
                    $fieldTypeIdentifier = $content->getContentType()->getFieldDefinition(
                        $field->fieldDefIdentifier
                    )->fieldTypeIdentifier;

                    if ('ezimage' !== $fieldTypeIdentifier) {
                        continue;
                    }

                    if ($this->fieldHelper->isFieldEmpty($content, $field->fieldDefIdentifier)) {
                        continue;
                    }

                    $variation = $this->imageVariationService->getVariation(
                        $field,
                        $content->getVersionInfo(),
                        'original'
                    );
                    $imageContainer = $domDocument->createElement('image:image');
                    $imageLoc = $domDocument->createElement('image:loc', $variation->uri);
                    $imageContainer->appendChild($imageLoc);
                    $urlElt->appendChild($imageContainer);
                }
            }

            $urlElt->appendChild($loc);
            $urlElt->appendChild($lastmod);
            $domElement->appendChild($urlElt);
        }
    }

    /**
     * Fill the sitemap index.
     */
    protected function fillSitemapIndex(DOMDocument $domDocument, int $numberOfResults, \DOMElement $domElement): void
    {
        $numberOfPage = (int) ceil($numberOfResults / static::PACKET_MAX);
        for ($sitemapNumber = 1; $sitemapNumber <= $numberOfPage; ++$sitemapNumber) {
            $sitemapElt = $domDocument->createElement('sitemap');

            try {
                $locUrl = $this->generateUrl(
                    '_novaseo_sitemap_page',
                    ['page' => $sitemapNumber],
                    UrlGeneratorInterface::ABSOLUTE_URL
                );
            } catch (\Exception $exception) {
                if ($this->has('logger')) {
                    $this->get('logger')->error('NovaeZSEO: '.$exception->getMessage());
                }

                continue;
            }

            $loc = $domDocument->createElement('loc', $locUrl);
            $date = new DateTime();
            $modificationDate = $date->format('c');
            $mod = $domDocument->createElement('lastmod', $modificationDate);
            $sitemapElt->appendChild($loc);
            $sitemapElt->appendChild($mod);
            $domElement->appendChild($sitemapElt);
        }
    }
}
