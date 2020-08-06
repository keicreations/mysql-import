#!/usr/bin/env php
<?php
require __DIR__.'/vendor/autoload.php';

use Doctrine\DBAL\Exception\ConnectionException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\SingleCommandApplication;
use Symfony\Component\Dotenv\Dotenv;
use Doctrine\DBAL\DriverManager;

(new SingleCommandApplication())
    ->addArgument('sourceFolder', InputArgument::REQUIRED)
    ->setCode(function (InputInterface $input, OutputInterface $output) {

        $dotenv = new Dotenv();
        $dotenv->loadEnv(__DIR__.'/.env');





        $finder = new Symfony\Component\Finder\Finder();
        $finder->files()->name('*.sql.gz');

        foreach ($finder->in($input->getArgument('sourceFolder')) as $file) {

            $fileNameWithExtension = $file->getRelativePathname();
            //$output->writeln(sprintf('Found compressed sql: "%s"', $fileNameWithExtension));

            $dbName = str_ireplace('.sql.gz', '', pathinfo($fileNameWithExtension, PATHINFO_BASENAME));


            if (
                    (
                            $dbName !== 'admin_vp'
                            &&
                            substr($dbName, 0, 4) !== 'vp_c'
                    )
                    ||
                    stripos($dbName, 'bu') !== false
                    ||
                    stripos($dbName, 'old') !== false
            ) {
                //$output->writeln(sprintf('Skipping "%s"', $dbName));
                continue;
            }

            $connectionParams = [
                'host' => $_ENV['DATABASE_HOST'],
                'user' => $_ENV['DATABASE_USER'],
                'password' => $_ENV['DATABASE_PASS'],
                'dbname' => $dbName,
                'driver' => 'pdo_mysql',
            ];

            $hasDb = false;
            $tables = [];
            try {
                $output->writeln(sprintf('Connecting to: "%s"', $connectionParams['dbname']));
                $connection = DriverManager::getConnection($connectionParams);
                $sm = $connection->getSchemaManager();
                $tables = $sm->listTables();
                $hasDb = true;
            }
            catch (ConnectionException $exception) {
                $output->writeln(sprintf('Database "%s" does not exists, creating...', $dbName));
                exec(sprintf('mysql -u %s -p%s -e "CREATE DATABASE IF NOT EXISTS %s"', $_ENV['DATABASE_USER'], $_ENV['DATABASE_PASS'], $dbName));

                try {
                    $connection = DriverManager::getConnection($connectionParams);
                    $sm = $connection->getSchemaManager();
                    $tables = $sm->listTables();
                    $hasDb = true;
                }
                catch (ConnectionException $exception) {
                    $output->writeln('Could not create database');
                }
            }

            try {
                if ($hasDb) {
                    if (!$tables) {
                        $output->writeln(sprintf('Database "%s" is empty, importing...', $dbName));
                        exec(sprintf('pv %s | gunzip -c | mysql -u %s -p%s %s', $file->getRealPath(),
                            $_ENV['DATABASE_USER'], $_ENV['DATABASE_PASS'], $dbName));
                    } else {
                        $output->writeln(sprintf('Database "%s" is NOT empty, skipping', $dbName));
                    }
                }
            }
            catch (\Exception $exception) {
                var_dump(get_class($exception));
            }

        }
    })
    ->run();
