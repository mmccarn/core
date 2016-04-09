<?php
/**
 * @author Björn Schießle <schiessle@owncloud.com>
 * @author Lukas Reschke <lukas@owncloud.com>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <icewind@owncloud.com>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
 * @author Vincent Petry <pvince81@owncloud.com>
 *
 * @copyright Copyright (c) 2016, ownCloud, Inc.
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

namespace OCA\DAV\Connector;

use OCP\IRequest;
use OCP\ISession;
use OCP\Share\Exceptions\ShareNotFound;
use OCP\Share\IManager;

/**
 * Class PublicAuth
 *
 * @package OCA\DAV\Connector
 */
class PublicAuth extends \Sabre\DAV\Auth\Backend\AbstractBasic {

	/** @var \OCP\Share\IShare */
	private $share;

	/** @var IManager */
	private $shareManager;

	/** @var ISession */
	private $session;

	/** @var IRequest */
	private $request;

	/**
	 * @param IRequest $request
	 * @param IManager $shareManager
	 * @param ISession $session
	 */
	public function __construct(IRequest $request,
								IManager $shareManager,
								ISession $session) {
		$this->request = $request;
		$this->shareManager = $shareManager;
		$this->session = $session;
	}

	/**
	 * Validates a username and password
	 *
	 * This method should return true or false depending on if login
	 * succeeded.
	 *
	 * @param string $username
	 * @param string $password
	 *
	 * @return bool
	 * @throws \Sabre\DAV\Exception\NotAuthenticated
	 */
	protected function validateUserPass($username, $password) {
		try {
			$share = $this->shareManager->getShareByToken($username);
		} catch (ShareNotFound $e) {
			return false;
		}

		$this->share = $share;

		\OC_User::setIncognitoMode(true);

		// check if the share is password protected
		if ($share->getPassword() !== null) {
			if ($share->getShareType() === \OCP\Share::SHARE_TYPE_LINK) {
				if ($this->shareManager->checkPassword($share, $password)) {
					return true;
				} else if ($this->session->exists('public_link_authenticated')
					&& $this->session->get('public_link_authenticated') === $share->getId()) {
					return true;
				} else {
					if (in_array('XMLHttpRequest', explode(',', $this->request->getHeader('X-Requested-With')))) {
						// do not re-authenticate over ajax, use dummy auth name to prevent browser popup
						http_response_code(401);
						header('WWW-Authenticate', 'DummyBasic real="ownCloud"');
						throw new \Sabre\DAV\Exception\NotAuthenticated('Cannot authenticate over ajax calls');
					}
					return false;
				}
			} else if ($share->getShareType() === \OCP\Share::SHARE_TYPE_REMOTE) {
				return true;
			} else {
				return false;
			}
		} else {
			return true;
		}
	}

	/**
	 * @return \OCP\Share\IShare
	 */
	public function getShare() {
		return $this->share;
	}
}
