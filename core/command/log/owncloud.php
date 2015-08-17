<?php
/**
 * @author Robin McCorkell <rmccorkell@owncloud.com>
 *
 * @copyright Copyright (c) 2015, ownCloud, Inc.
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace OC\Core\Command\Log;

use \OCP\IConfig;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Owncloud extends Command {

	/** @var IConfig */
	protected $config;

	public function __construct(IConfig $config) {
		$this->config = $config;
		parent::__construct();
	}

	protected function configure() {
		$this
			->setName('log:owncloud')
			->setDescription('manipulate owncloud logging backend')
			->addOption(
				'enable',
				null,
				InputOption::VALUE_NONE,
				'enable this logging backend'
			)
			->addOption(
				'file',
				null,
				InputOption::VALUE_REQUIRED,
				'set the log file, relative to data'
			)
			->addOption(
				'rotate-size',
				null,
				InputOption::VALUE_REQUIRED,
				'set the filesize for log rotation, 0 = disabled'
			)
		;
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$toBeSet = [];

		if ($input->getOption('enable')) {
			$toBeSet['logtype'] = 'owncloud';
		}

		if ($file = $input->getOption('file')) {
			$toBeSet['log_file'] = $file;
		}

		if (($rotateSize = $input->getOption('rotate-size')) !== null) {
			$toBeSet['log_rotate_size'] = \OCP\Util::computerFileSize($rotateSize);
		}

		// set config
		foreach ($toBeSet as $option => $value) {
			$this->config->setSystemValue($option, $value);
		}

		// display config
		if ($this->config->getSystemValue('logtype', 'owncloud') === 'owncloud') {
			$enabledText = 'enabled';
		} else {
			$enabledText = 'disabled';
		}
		$output->writeln('Log backend owncloud: '.$enabledText);

		$output->writeln('Log file: '.$this->config->getSystemValue('log_file', 'owncloud.log'));

		$rotateSize = $this->config->getSystemValue('log_rotate_size', 0);
		if ($rotateSize) {
			$rotateString = \OCP\Util::humanFileSize($rotateSize);
		} else {
			$rotateString = 'disabled';
		}
		$output->writeln('Rotate at: '.$rotateString);
	}
}
