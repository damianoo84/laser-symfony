<?php

declare(strict_types=1);

namespace App\Command;

use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Block;
use App\Entity\Setting;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:import-sqlite', description: 'Kopiuje treść ze starego SQLite do pustej bazy (nie zmienia źródła)')]
final class ImportSqliteCommand extends Command
{
    public function __construct(private EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('file', InputArgument::REQUIRED, 'Ścieżka do pliku SQLite');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($this->em->getRepository(Setting::class)->count([]) || $this->em->getRepository(Block::class)->count([])) {
            $output->writeln('<error>Docelowa baza zawiera już treść. Nic nie zmieniono.</error>');
            return Command::FAILURE;
        }
        $file = realpath($input->getArgument('file'));
        if (!$file || !is_file($file)) {
            throw new \InvalidArgumentException('Nie znaleziono pliku SQLite.');
        }
        $source = new \PDO('sqlite:file:' . str_replace('\\', '/', $file) . '?mode=ro', null, null, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
        $settings = $source->query('SELECT * FROM app_settings')->fetchAll(\PDO::FETCH_ASSOC);
        $blocks = $source->query('SELECT * FROM app_blocks ORDER BY sort, id')->fetchAll(\PDO::FETCH_ASSOC);
        $this->em->wrapInTransaction(function () use ($settings, $blocks): void {
            foreach ($settings as $row) {
                $this->em->persist((new Setting())->setKey($row['key'])->setValue($row['value']));
            }
            foreach ($blocks as $row) {
                $this->em->persist((new Block())->setType($row['type'])->setSort((int) $row['sort'])->setData(json_decode($row['data'], true, 512, JSON_THROW_ON_ERROR)));
            }
        });
        $output->writeln(sprintf('<info>Zaimportowano %d ustawień i %d bloków. Źródło pozostało bez zmian.</info>', count($settings), count($blocks)));
        return Command::SUCCESS;
    }
}
