<?php
/**
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <icewind@owncloud.com>
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

namespace OCA\Files_External\Config;

use OCP\Files\Storage;
use OC\Files\Mount\MountPoint;
use OCP\Files\Storage\IStorageFactory;
use OCA\Files_External\Lib\PersonalMount;
use OCP\Files\Config\IMountProvider;
use OCP\IUser;
use OCA\Files_external\Service\UserStoragesService;
use OCA\Files_External\Service\UserGlobalStoragesService;
use OCA\Files_External\Lib\StorageConfig;

/**
 * Make the old files_external config work with the new public mount config api
 */
class ConfigAdapter implements IMountProvider {

	/** @var UserStoragesService */
	private $userStoragesService;

	/** @var UserGlobalStoragesService */
	private $userGlobalStoragesService;

	/**
	 * @param UserStoragesService $userStoragesService
	 * @param UserGlobalStoragesService $userGlobalStoragesService
	 */
	public function __construct(
		UserStoragesService $userStoragesService,
		UserGlobalStoragesService $userGlobalStoragesService
	) {
		$this->userStoragesService = $userStoragesService;
		$this->userGlobalStoragesService = $userGlobalStoragesService;
	}

	/**
	 * Process storage ready for mounting
	 *
	 * @param StorageConfig $storage
	 */
	private function prepareStorageConfig(StorageConfig &$storage) {
		$objectStore = $storage->getBackendOption('objectstore');
		if ($objectStore) {
			$objectClass = $objectStore['class'];
			$storage->setBackendOption('objectstore', new $objectClass($objectStore));
		}

		$storage->getAuthMechanism()->manipulateStorageConfig($storage);
		$storage->getBackend()->manipulateStorageConfig($storage);
	}

	/**
	 * Construct the storage implementation
	 *
	 * @param StorageConfig $storageConfig
	 * @return Storage
	 */
	private function constructStorage(StorageConfig $storageConfig) {
		$class = $storageConfig->getBackend()->getStorageClass();
		$storage = new $class($storageConfig->getBackendOptions());

		// auth mechanism should fire first
		$storage = $storageConfig->getBackend()->wrapStorage($storage);
		$storage = $storageConfig->getAuthMechanism()->wrapStorage($storage);

		return $storage;
	}

	/**
	 * Get all mountpoints applicable for the user
	 *
	 * @param \OCP\IUser $user
	 * @param \OCP\Files\Storage\IStorageFactory $loader
	 * @return \OCP\Files\Mount\IMountPoint[]
	 */
	public function getMountsForUser(IUser $user, IStorageFactory $loader) {
		$mounts = [];

		$this->userStoragesService->setUser($user);
		$this->userGlobalStoragesService->setUser($user);

		foreach ($this->userGlobalStoragesService->getAllStorages() as $storage) {
			$this->prepareStorageConfig($storage);
			$impl = $this->constructStorage($storage);

			$mount = new MountPoint(
				$impl,
				'/'.$user->getUID().'/files' . $storage->getMountPoint(),
				null,
				$loader,
				$storage->getMountOptions()
			);
			$mounts[$storage->getMountPoint()] = $mount;
		}

		foreach ($this->userStoragesService->getAllStorages() as $storage) {
			$this->prepareStorageConfig($storage);
			$impl = $this->constructStorage($storage);

			$mount = new PersonalMount(
				$impl,
				'/'.$user->getUID().'/files' . $storage->getMountPoint(),
				null,
				$loader,
				$storage->getMountOptions()
			);
			$mount->attachStoragesService($this->userStoragesService, $storage->getId());
			$mounts[$storage->getMountPoint()] = $mount;
		}

		$this->userStoragesService->resetUser();
		$this->userGlobalStoragesService->resetUser();

		return $mounts;
	}
}
