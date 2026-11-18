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

#[AsCommand(name: 'app:seo-upgrade', description: 'Ulepsza wyłącznie rozpoznane domyślne metadane SEO, zachowując własne teksty')]
final class UpgradeSeoCommand extends Command
{
    public function __construct(private EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $old = [
            'site.title' => 'Oczyszczanie laserowe — drewno, cegła, kamień, beton, metal | LAZER Limanowa',
            'site.description' => 'Mobilne czyszczenie laserowe metalu, drewna, kamienia, cegły i betonu. Małopolska i cała Polska. Darmowa wycena.',
            'site.heading' => 'Mobilne czyszczenie laserowe — LAZER Limanowa',
        ];
        $defaults = Defaults::data()['settings'];
        $changed = 0;
        foreach ($old as $key => $value) {
            $setting = $this->em->getRepository(Setting::class)->findOneBy(['key' => $key]);
            if ($setting === null || $setting->getValue() === $value) {
                $setting ??= (new Setting())->setKey($key);
                $setting->setValue($defaults[$key]);
                $this->em->persist($setting);
                ++$changed;
            }
        }
        $this->em->flush();
        $output->writeln(sprintf('<info>Zaktualizowano %d domyślnych metadanych SEO. Własne teksty pozostawiono bez zmian.</info>', $changed));
        return Command::SUCCESS;
    }
}
