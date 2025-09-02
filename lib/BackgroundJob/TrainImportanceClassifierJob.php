<?php

declare(strict_types=1);

/**
 * @copyright 2020 Christoph Wurst <christoph@winzerhof-wurst.at>
 *
 * @author 2020 Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author 2024 Richard Steinmetz <richard@steinmetz.cloud>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace OCA\Mail\BackgroundJob;

use OCA\Mail\Service\AccountService;
use OCA\Mail\Service\Classification\ClassificationSettingsService;
use OCA\Mail\Service\Classification\ImportanceClassifier;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJobList;
use OCP\BackgroundJob\TimedJob;
use Psr\Log\LoggerInterface;
use Throwable;

class TrainImportanceClassifierJob extends TimedJob {
	private AccountService $accountService;
	private ImportanceClassifier $classifier;
	private IJobList $jobList;
	private LoggerInterface $logger;
	private ClassificationSettingsService $classificationSettingsService;

	public function __construct(ITimeFactory $time,
		AccountService $accountService,
		ImportanceClassifier $classifier,
		IJobList $jobList,
		LoggerInterface $logger,
		ClassificationSettingsService $classificationSettingsService) {
		parent::__construct($time);

		$this->accountService = $accountService;
		$this->classifier = $classifier;
		$this->jobList = $jobList;
		$this->logger = $logger;
		$this->classificationSettingsService = $classificationSettingsService;

		$this->setInterval(24 * 60 * 60);
		$this->setTimeSensitivity(self::TIME_INSENSITIVE);
	}

	/**
	 * @return void
	 */
	protected function run($argument) {
		$accountId = (int)$argument['accountId'];

		try {
			$account = $this->accountService->findById($accountId);
		} catch (DoesNotExistException $e) {
			$this->logger->debug('Could not find account <' . $accountId . '> removing from jobs');
			$this->jobList->remove(self::class, $argument);
			return;
		}

		if(!$account->getMailAccount()->canAuthenticateImap()) {
			$this->logger->debug('Cron importance classifier training not possible: no authentication on IMAP possible');
			return;
		}

		if (!$this->classificationSettingsService->isClassificationEnabled($account->getUserId())) {
			$this->logger->debug("classification is turned off for account $accountId");
			return;
		}

		try {
			$this->classifier->train(
				$account,
				$this->logger
			);
		} catch (Throwable $e) {
			$this->logger->error('Cron importance classifier training failed: ' . $e->getMessage(), [
				'exception' => $e,
			]);
		}
	}
}
