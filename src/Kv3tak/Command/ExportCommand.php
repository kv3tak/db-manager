<?php

namespace Kv3tak\Command;

use MySQLDump;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;

class ExportCommand extends Command
{
	use ImportExportTrait;

	protected function configure()
	{
		$this->setName('export')
			->setDescription('Exports the database.')
			->addArgument('config', InputArgument::REQUIRED, 'Path to database NEON config file.');
	}

	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$configFile = $input->getArgument('config');
		$dbList = $this->getDbList($configFile, $output);

		if (count($dbList) < 1) {
			$output->writeln("<comment>There is no valid MySQL database connection defined in \"${configFile}\".</comment>");
			return false;
		}

		$this->printDbInfo($dbList, $output);

		$helper = $this->getHelper('question');
		$question = new ChoiceQuestion(
			'Please select database(s) to export.',
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

			try {
				$dump = new MySQLDump(new \mysqli($host, $user, $password, $dbName));
			} catch (\Exception $e) {
				$output->writeln("<error>Error: Couldn't connect to \"${db}\".</error>");
				continue;
			}

			$exportFileName = "./db-export-" . $dbConfig['dsnParsed']['dbname'] . "-" . date("Ymd-Hi") . ".sql";
			$dump->save($exportFileName);
			$output->writeln("\n<info>-> Database \"${dbName}\" exported to \"${exportFileName}\".</info>");
		}

		$output->writeln("\nExporting is done.\n");
		return true;
	}


}
