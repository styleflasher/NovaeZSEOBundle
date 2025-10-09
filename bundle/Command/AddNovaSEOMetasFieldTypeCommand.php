<?php

declare(strict_types=1);

/**
 * NovaeZSEOBundle AddNovaSEOMetasFieldTypeCommand.
 *
 * @package   Novactive\Bundle\eZSEOBundle
 *
 * @author    Novactive <novaseobundle@novactive.com>
 * @copyright 2015 Novactive
 * @license   https://github.com/Novactive/NovaeZSEOBundle/blob/master/LICENSE MIT Licence
 */
namespace Novactive\Bundle\eZSEOBundle\Command;

use Ibexa\Contracts\Core\Repository\Repository;
use Ibexa\Contracts\Core\Repository\UserService;
use Ibexa\Contracts\Core\Repository\Values\ContentType\ContentType;
use Ibexa\Contracts\Core\SiteAccess\ConfigResolverInterface;
use Novactive\Bundle\eZSEOBundle\Core\Converter\ContentTypesHelper;
use Novactive\Bundle\eZSEOBundle\Core\Installer\Field as FieldInstaller;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @SuppressWarnings(PHPMD.NPathComplexity)
 */
#[\Symfony\Component\Console\Attribute\AsCommand(name: 'nova_ezseo:addnovaseometasfieldtype', description: 'Add the novaseometas FieldType to Content Types', help: <<<'TXT'
The command <info>%command.name%</info> add the FieldType 'novaseometas'.
You can select the Content Type via the <info>identifier</info>, <info>identifiers</info>,
<info>group_identifier</info> option.
    - Identifier will be: <comment>%nova_ezseo.default.fieldtype_metas_identifier%</comment>
    - Name will be: <comment>Metas</comment>
    - Category will be: <comment>SEO</comment>
TXT)]
class AddNovaSEOMetasFieldTypeCommand extends Command
{
    public function __construct(
        private readonly ConfigResolverInterface $configResolver,
        private readonly Repository $repository,
        private readonly UserService $userService,
        private readonly FieldInstaller $fieldInstaller,
        private readonly ContentTypesHelper $contentTypesHelper
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('identifier', null, InputOption::VALUE_REQUIRED, 'a content type identifier')
            ->addOption(
                'identifiers',
                null,
                InputOption::VALUE_REQUIRED,
                'some content types identifier, separated by a comma'
            )
            ->addOption('group_identifier', null, InputOption::VALUE_REQUIRED, 'a content type group identifier');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $symfonyStyle = new SymfonyStyle($input, $output);

        $contentTypes = [];

        $groupIdentifier = $input->getOption('group_identifier');
        if (!empty($groupIdentifier)) {
            $contentTypes = $this->contentTypesHelper->getContentTypesByGroup($groupIdentifier);
        }

        $identifiers = $input->getOption('identifiers');
        if (!empty($identifiers)) {
            $contentTypes = $this->contentTypesHelper->getContentTypesByIdentifier($identifiers);
        }

        $identifier = $input->getOption('identifier');
        if (!empty($identifier)) {
            $contentTypes = $this->contentTypesHelper->getContentTypesByIdentifier($identifier);
        }

        $output->writeln('<info>Selected Content Type:</info>');
        foreach ($contentTypes as $contentType) {
            /* @var ContentType $contentType */
            $output->writeln('	- ' . $contentType->getName($contentType->mainLanguageCode));
        }

        $helper = $this->getHelper('question');
        $confirmationQuestion = new ConfirmationQuestion(
            "\n<question>Are you sure you want to add novaseometas all these Content Type?</question>[yes]",
            true
        );

        if (!$helper->ask($input, $output, $confirmationQuestion)) {
            $symfonyStyle->success('Nothing to do.');

            return 0;
        }

        if ([] === $contentTypes) {
            $symfonyStyle->success('Nothing to do.');

            return 0;
        }

        $fieldName = $this->configResolver->getParameter('fieldtype_metas_identifier', 'nova_ezseo');

        foreach ($contentTypes as $contentType) {
            $symfonyStyle->section('Doing ' . $contentType->getName());
            if ($this->fieldInstaller->fieldExists($fieldName, $contentType)) {
                $symfonyStyle->block('Field exists');
                continue;
            }

            if (!$this->fieldInstaller->addToContentType($fieldName, $contentType)) {
                $symfonyStyle->error(
                    sprintf(
                        'There were errors when adding new field to <info>%s</info> ContentType: <error>%s</error>',
                        $contentType->getName($contentType->mainLanguageCode),
                        $this->fieldInstaller->getErrorMessage()
                    )
                );
                continue;
            }

            $symfonyStyle->block('FieldType added.');
        }

        $symfonyStyle->success('Done.');

        return 0;
    }

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        $symfonyStyle = new SymfonyStyle($input, $output);
        $symfonyStyle->comment('Switching to Admin');

        $this->repository->getPermissionResolver()->setCurrentUserReference(
            $this->userService->loadUser(
                $this->configResolver->getParameter('admin_user_id', 'novactive.novaseobundle')
            )
        );
    }
}
