<?php

declare(strict_types=1);

namespace App\Command;

use App\Content\Defaults;
use App\Entity\Block;
use App\Entity\Setting;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:seed', description: 'Wypełnia bazę domyślną treścią strony')]
class SeedCommand extends Command
{
    public function __construct(private EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $data = Defaults::data();

        foreach ($data['settings'] as $key => $value) {
            if ($this->em->getRepository(Setting::class)->findOneBy(['key' => $key])) {
                continue;
            }
            $s = new Setting();
            $s->setKey((string) $key);
            $s->setValue((string) $value);
            $this->em->persist($s);
        }

        foreach ($data['blocks'] as $type => $items) {
            if ($this->em->getRepository(Block::class)->count(['type' => $type]) > 0) {
                continue;
            }
            foreach ($items as $i => $item) {
                $b = new Block();
                $b->setType((string) $type);
                $b->setSort((int) $i);
                $b->setData($item);
                $this->em->persist($b);
            }
        }

        $this->em->flush();
        $output->writeln('<info>Uzupełniono brakujące ustawienia i puste typy bloków. Istniejąca treść nie została nadpisana.</info>');
        return Command::SUCCESS;
    }
}
