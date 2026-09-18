<?php

declare(strict_types=1);

/**
 * This source file is available under the terms of the MIT License.
 * Full copyright and license information is available in
 * LICENSE which is distributed with this source code.
 *
 * @copyright Copyright (c) Basilicom GmbH (https://basilicom.de)
 * @license   MIT
 */

namespace Basilicom\PathFormatterBundle\Command;

use Basilicom\PathFormatterBundle\DependencyInjection\BasilicomPathFormatter;
use Basilicom\PathFormatterBundle\DependencyInjection\ConfigDefinition;
use Basilicom\PathFormatterBundle\DependencyInjection\PimcoreAdapter;
use Pimcore\Model\Element\ElementInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'basilicom:path-formatter:debug',
    description: 'Shows which pattern the path formatter applies to an element and what it renders.',
)]
final class DebugPathFormatterCommand extends Command
{
    public function __construct(
        private readonly BasilicomPathFormatter $formatter,
        private readonly PimcoreAdapter $pimcoreAdapter,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('target', InputArgument::REQUIRED, 'ID of the related element to format')
            ->addOption('type', null, InputOption::VALUE_REQUIRED, 'Element type of the target', 'object')
            ->addOption('source', null, InputOption::VALUE_REQUIRED, 'ID of the object holding the relation field')
            ->addOption('field', null, InputOption::VALUE_REQUIRED, 'Name of the relation field')
            ->addOption(
                'container-type',
                null,
                InputOption::VALUE_REQUIRED,
                'Container of the relation field: ' . implode(', ', ConfigDefinition::CONTAINER_TYPES),
                'object'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $target = $this->load((string)$input->getOption('type'), (int)$input->getArgument('target'));
        if ($target === null) {
            $io->error(sprintf('No %s found with ID %s.', $input->getOption('type'), $input->getArgument('target')));

            return Command::FAILURE;
        }

        $source = $target;
        if ($input->getOption('source') !== null) {
            $source = $this->load('object', (int)$input->getOption('source'));
            if ($source === null) {
                $io->error(sprintf('No object found with ID %s.', $input->getOption('source')));

                return Command::FAILURE;
            }
        }

        $context = [
            'containerType' => (string)$input->getOption('container-type'),
            'fieldname'     => $input->getOption('field'),
        ];

        $explanation = $this->formatter->explain($target, $source, $context);

        $io->section(sprintf('%s (%s)', $target->getFullPath(), $target::class));

        if ($explanation['pattern'] === null) {
            $io->warning('No pattern matches this element — it keeps its own path.');

            return Command::SUCCESS;
        }

        $io->definitionList(
            ['Pattern' => $explanation['pattern']],
            ['Result' => (string)$explanation['value']],
            ['Cache tags' => implode(', ', $explanation['tags'])],
        );

        if ($explanation['trace'] !== []) {
            $io->table(
                ['Placeholder', 'Rendered'],
                array_map(
                    static fn (string $key, string $value): array => [$key, $value === '' ? '<comment>(empty)</comment>' : $value],
                    array_keys($explanation['trace']),
                    $explanation['trace']
                )
            );
        }

        return Command::SUCCESS;
    }

    private function load(string $type, int $id): ?ElementInterface
    {
        return $id > 0 ? $this->pimcoreAdapter->getElementById($type, $id) : null;
    }
}
