<?php

declare(strict_types=1);

/**
 * NovaeZSEOBundle ImportUrlsHelper.
 *
 * @package   Novactive\Bundle\eZSEOBundle
 *
 * @author    Novactive <m.bouchaala@novactive.com>
 * @copyright 2015 Novactive
 * @license   https://github.com/Novactive/NovaeZSEOBundle/blob/master/LICENSE MIT Licence
 */
namespace Novactive\Bundle\eZSEOBundle\Core\Helper;

use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Ibexa\Contracts\Core\Repository\URLWildcardService;
use Ibexa\Core\IO\IOService;
use Novactive\Bundle\eZSEOBundle\Entity\RedirectImportHistory;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Contracts\Translation\TranslatorInterface;

class ImportUrlsHelper
{
    public function __construct(private readonly IOService $ioService, private readonly URLWildcardService $urlWildCardService, private readonly EntityManagerInterface $entityManager, private readonly TranslatorInterface $translator, private readonly LoggerInterface $logger, private readonly Filesystem $filesystem, private readonly string $cacheDirectory)
    {
    }

    public function importUrlRedirection(string $filePath): array
    {
        $counter = 0;
        $params = [];
        $return = [];

        $fileToImport = fopen($filePath, 'r');
        if (false !== $fileToImport) {
            $totalImported = 0;
            $totalUrls = 0;
            $filename = 'redirectUrls/report/redirect_import_urls-'.date('d-m-Y-H-i-s').'.csv';
            $filePath = $this->cacheDirectory.$filename;
            $this->filesystem->dumpFile($filePath, "Source;Destination;Message;Status\n");

            while (false !== ($data = fgetcsv($fileToImport, 1000, ';'))) {
                if (0 === $counter) {
                    ++$counter;
                    continue;
                }

                if (isset($data[0]) && isset($data[1])) {
                    $source = $data[0]; // source
                    $destination = $data[1]; // destination
                    ++$totalUrls;
                    // verify if URL destination exists in source URL
                    $verifResult = $this->checkUrlDestinationExist($destination);

                    if (
                        ('' !== $source || '' !== $destination)
                        && ($source !== $destination)
                        && !$verifResult
                    ) {
                        // try to save data in table ezurlwildcard
                        $saveResult = $this->saveUrls($filePath, $source, $destination);
                        if ('OK' === $saveResult['imported']) {
                            ++$totalImported;
                        }

                        $return[] = $saveResult;
                    } else {
                        $msg = $this->translator->trans('nova.import.list.table.exists', [], 'redirect');
                        $status = 'KO';
                        $return[] = [
                            'source' => $source,
                            'destination' => $destination,
                            'msg' => $msg,
                            'imported' => $status,
                        ];
                        $this->filesystem->appendToFile(
                            $filePath,
                            sprintf('%s;%s;%s;%s%s', $source, $destination, $msg, $status, PHP_EOL)
                        );
                    }
                } else {
                    $params['errorType'] = $this->translator->trans(
                        'nova.import.root.form.error.invalid_file',
                        [],
                        'redirect'
                    );
                }
            }

            if (!isset($params['errorType'])) {
                try {
                    $uploadedFileStruct = $this->ioService->newBinaryCreateStructFromLocalFile($filePath);
                    $uploadedFileStruct->id = $filename;
                    $this->ioService->createBinaryFile($uploadedFileStruct);
                    $this->filesystem->remove($filePath);
                } catch (\Exception $e) {
                    $this->logger->log(LogLevel::ERROR, $e->getMessage());
                }
            }

            $params += [
                'totalImported' => $totalImported,
                'totalUrls' => $totalUrls,
                'return' => $return,
                'fileLog' => $filename,
            ];
        }

        return $params;
    }

    public function checkUrlDestinationExist(string $destination): bool
    {
        $urlExists = false;

        try {
            $urlExists = $this->urlWildCardService->translate($destination);
        } catch (\Exception $exception) {
            $this->logger->log(LogLevel::ERROR, $exception->getMessage());
        }

        return $urlExists;
    }

    public function saveUrls(string $filePath, string $source, string $destination): array
    {
        $return = [];
        try {
            $result = $this->urlWildCardService->create($source, $destination, 'Redirection');
            if ($result) {
                $return = [
                    'source' => $source,
                    'destination' => $destination,
                    'msg' => $this->translator->trans('nova.import.list.table.info', [], 'redirect'),
                    'imported' => 'OK',
                ];

                $msg = $return['msg'];
                $status = $return['imported'];
                $this->filesystem->appendToFile(
                    $filePath,
                    sprintf('%s;%s;%s;%s%s', $source, $destination, $msg, $status, PHP_EOL)
                );
            }
        } catch (\Exception $exception) {
            $return = [
                'source' => $source,
                'destination' => $destination,
                'msg' => $exception->getMessage(),
                'imported' => 'KO',
            ];
            $msg = $return['msg'];
            $status = $return['imported'];
            $this->filesystem->appendToFile(
                $filePath,
                sprintf('%s;%s;%s;%s%s', $source, $destination, $msg, $status, PHP_EOL)
            );
            $this->logger->log(LogLevel::ERROR, $exception->getMessage());
        }

        return $return;
    }

    public function saveFileHistory(string $originalFileName, string $fileLog): void
    {
        try {
            $redirectImportHistory = new RedirectImportHistory();
            $redirectImportHistory->setNameFile($originalFileName);
            $redirectImportHistory->setDate(new DateTime());
            $redirectImportHistory->setPath($fileLog);
            $this->entityManager->persist($redirectImportHistory);
            $this->entityManager->flush();
        } catch (\Exception $exception) {
            $this->logger->log(LogLevel::ERROR, $exception->getMessage());
        }
    }

    public function downloadFile(RedirectImportHistory $redirectImportHistory): ?string
    {
        try {
            $file = $this->ioService->loadBinaryFile($redirectImportHistory->getPath());

            return $this->ioService->getFileContents($file);
        } catch (\Exception $exception) {
            $this->logger->log(LogLevel::ERROR, $exception->getMessage());
        }

        return null;
    }

    public function getLogsHistory(): array
    {
        return $this->entityManager->getRepository(RedirectImportHistory::class)->findAll();
    }
}
