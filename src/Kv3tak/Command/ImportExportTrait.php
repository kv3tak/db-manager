<?php

namespace Kv3tak\Command;

use Kv3tak\Exceptions\IncompleteDbConfigException;
use Kv3tak\Exceptions\UnsupportedDriverException;
use Nette\DI\Config;
use Nette\DI\Helpers;
use Nette\Utils\AssertionException;
use Symfony\Component\Console\Output\OutputInterface;

trait ImportExportTrait
{

	/**
	 * Prints info about databases to the terminal.
	 *
	 * @param array $dbList List of valid databases
	 * @param OutputInterface $output
	 */
	protected function printDbInfo($dbList, OutputInterface $output)
	{
		$output->writeln("\n<options=bold,underscore>Listing found databases:</>\n");
		$i = 0;
		foreach ($dbList as $dbConfName => $dbConfig) {
			$host = $dbConfig['dsnParsed']['host'];
			$user = $dbConfig['user'];
			$password = $dbConfig['password'];
			$dbName = $dbConfig['dsnParsed']['dbname'];

			$output->writeln("[" . ($i++) . "] <fg=green>\"${dbConfName}\"</> : database <fg=yellow>\"${dbName}\"</> at <fg=yellow>\"${host}\"</>");
		}
		$output->write("\n");
	}


	/**
	 * Loads and returns the list of databases from the given config file (from the "database" section)
	 *
	 * @param $configFile
	 * @param OutputInterface $output
	 * @return array|bool|mixed
	 */
	protected function getDbList($configFile, OutputInterface $output)
	{
		$configLoader = new Config\Loader();

		try {
			$params = $configLoader->load($configFile, 'parameters');
		} catch (AssertionException $e) {
			$params = [];
		}

		try {
			$dbList = $configLoader->load($configFile, 'database');
		} catch (AssertionException $e) {
			$output->writeln("Couldn't find \"database\" section in config file \"${configFile}\".");
			return false;
		}

		$dbList = Helpers::expand($dbList, $params, true);

		foreach ($dbList as $name => $dbConfig) {
			//If "database" section doesn't contain multiple connection definitions
			if (!is_array($dbConfig)) {
				$dbList = ['default' => $dbList];
				try {
					$this->addDatabase($dbList, 'default', $dbList['default'], $output);
				} catch (IncompleteDbConfigException $e) {
					//nothing to do
				} catch (UnsupportedDriverException $e) {
					//nothing to do
				}
				break;
			}

			try {
				$this->addDatabase($dbList, $name, $dbConfig, $output);
			} catch (IncompleteDbConfigException $e) {
				continue;
			} catch (UnsupportedDriverException $e) {
				continue;
			}
		}

		return $dbList;
	}


	/**
	 * Tries to pass the database to the $dbList.
	 *
	 * @internal
	 *
	 * @param $dbList
	 * @param $name
	 * @param $dbConfig
	 * @param OutputInterface $output
	 */
	private function addDatabase(&$dbList, $name, $dbConfig, OutputInterface $output)
	{

		if (!isset($dbConfig['dsn']) || !isset($dbConfig['user']) || !array_key_exists('password', $dbConfig)) {
			$output->writeln("Warning: \"${name}\" miss some config info.");
			unset($dbList[$name]);//not sure if this is ok?
			throw new IncompleteDbConfigException();
		}


		try {
			$dbList[$name]['dsnParsed'] = $this->parseDsn($dbConfig['dsn']);

		} catch (UnsupportedDriverException $e) {
			$output->writeln("Warning: \"${name}\" has different driver.");
			unset($dbList[$name]);//not sure if this is ok?
			throw $e;
		}
	}


	/**
	 * Parse dsn string to array
	 *
	 * @param string $dsn
	 * @return array
	 * @throws UnsupportedDriverException
	 */
	private function parseDsn($dsn = '')
	{
		$dsnParsed = [];
		$prefix = "mysql:";
		$prefixLen = strlen($prefix);

		if (substr($dsn, 0, $prefixLen) != $prefix) {
			throw new UnsupportedDriverException();
		}

		$dsn = substr($dsn, strlen($prefix));

		$dsnArr = explode(';', $dsn);
		foreach ($dsnArr as $dsnItem) {
			$vals = explode('=', $dsnItem);
			$dsnParsed[$vals[0]] = $vals[1];
		}

		return $dsnParsed;
	}
}
