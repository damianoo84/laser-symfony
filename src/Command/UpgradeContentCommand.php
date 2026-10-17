<?php

declare(strict_types=1);

namespace App\Command;

use App\Content\Defaults;
use App\Entity\Setting;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:upgrade-content', description: 'Dodaje nowe ustawienia i aktualizuje wyłącznie znane, nieedytowane teksty starego szablonu')]
final class UpgradeContentCommand extends Command
{
    public function __construct(private EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $old = [
            'formOk' => 'Dopisz zdjęcia powierzchni — przyspieszy to wycenę.',
            'stepsHead.title' => 'Wycena w 24 h — w 3 krokach',
            'galeriaHead.p' => 'Kliknij, aby powiększyć. Nowe zdjęcia dodasz w panelu admina.',
        ];
        $defaults = Defaults::data()['settings'];
        $defaults['galeriaHead.p'] = 'Zobacz efekty naszej pracy. Kliknij zdjęcie, aby je powiększyć.';
        $changed = 0;
        foreach ($defaults as $key => $value) {
            $setting = $this->em->getRepository(Setting::class)->findOneBy(['key' => $key]);
            if ($setting === null || (isset($old[$key]) && $setting->getValue() === $old[$key])) {
                $setting ??= (new Setting())->setKey($key);
                $setting->setValue($value);
                $this->em->persist($setting);
                ++$changed;
            }
        }
        $this->em->flush();
        $output->writeln(sprintf('<info>Zaktualizowano %d ustawień. Własne teksty i bloki pozostawiono bez zmian.</info>', $changed));
        return Command::SUCCESS;
    }
}
