<?php

namespace Kv3tak\Command;

use MySQLDump;
use MySQLImport;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;

class ImportCommand extends Command
{
	use ImportExportTrait;

	protected function configure()
	{
		$this->setName('import')
			->setDescription('Imports the database.')
			->addArgument('config', InputArgument::REQUIRED, 'Path to database NEON config file.')
			->addArgument('sql-file', InputArgument::REQUIRED, 'SQL dump script for creating the database.');
	}

	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$dataFile = $input->getArgument("sql-file");
		if (!file_exists($dataFile)) {
			$output->writeln("File \"${dataFile}\" does not exists.");
			return false;
		}

		$configFile = $input->getArgument('config');
		$dbList = $this->getDbList($configFile, $output);

		if (count($dbList) < 1) {
			$output->writeln("<comment>There is no valid MySQL database connection defined in \"${configFile}\".</comment>");
			return false;
		}

		$this->printDbInfo($dbList, $output);

		$helper = $this->getHelper('question');
		$question = new ChoiceQuestion(
			"Please select database(s) to import structure and data from \"${configFile}\".",
			array_keys($dbList),
			null
		);
		$question->setMultiselect(true);
		$dbs = $helper->ask($input, $output, $question);

		foreach ($dbs as $db) {
			$dbConfig = $dbList[$db];
			$host = $dbConfig['dsnParsed']['host'];
			$user = $dbConfig['user'];
			$password = $dbConfig['password'];
			$dbName = $dbConfig['dsnParsed']['dbname'];

			$question = new ConfirmationQuestion("<question>Do you really wish to upload \"${dataFile}\" to the \"${dbName}\"?</question> [y/N]: ", false);
			$confirmImport = $helper->ask($input, $output, $question);
			$output->write("\n");

			if (!$confirmImport) {
				$output->writeln("<comment>-> Skipping \"${dbName}\".</comment>");
				continue;
			}

			try {
				$import = new MySQLImport(new \mysqli($host, $user, $password, $dbName));
			} catch (\Exception $e) {
				$output->writeln("Error: Couldn't connect to \"${db}\".");
				continue;
			}


			$progress = new ProgressBar($output);
			$progress->setMessage("Importing to \"${dbName}\".");
			$import->onProgress = function ($count, $percent) use ($progress) {
				if ($percent !== null) {
					$progress->setProgress($percent);
				}
			};

			$progress->start();
			$import->load($dataFile);
			$progress->finish();

			$output->writeln("\n\n<info>-> Import to the \"${dbName}\" database is finished.</info>");
		}

		$output->writeln("\nImporting is done.\n");
		return true;
	}


}
