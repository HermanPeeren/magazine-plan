<?php

/**
 * @package     MagazineChecklist
 *
 * @copyright   Copyright (C) 2026 Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Installer\InstallerAdapter;
use Joomla\Database\DatabaseInterface;

/**
 * Install script for the magazine checklist task plugin.
 *
 * Refuses a site it cannot run on, and enables the plugin on a fresh install:
 * Joomla installs plugins disabled, and a disabled task plugin offers no task
 * type, so the first thing anyone would see is that it "does nothing". It still
 * does nothing until somebody creates a task with it.
 */
class PlgTaskMagazinechecklistInstallerScript
{
	/**
	 * The oldest Joomla this runs on. The Task Scheduler arrived in 4.1; 5.0 is
	 * the oldest version still supported.
	 */
	private string $minimumJoomlaVersion = '5.0';

	/**
	 * The oldest PHP this runs on: Joomla 5's own minimum.
	 */
	private string $minimumPHPVersion = '8.1';

	/**
	 * Refuse an environment that cannot run this.
	 *
	 * @param   string            $type    install, update, discover_install or uninstall
	 * @param   InstallerAdapter  $parent  The installer
	 *
	 * @return  boolean  False stops the installation.
	 */
	public function preflight($type, $parent): bool
	{
		if ($type === 'uninstall') {
			return true;
		}

		$app = Factory::getApplication();

		if (version_compare(PHP_VERSION, $this->minimumPHPVersion, '<')) {
			$app->enqueueMessage(
				sprintf('The magazine checklist plugin needs PHP %s or later.', $this->minimumPHPVersion),
				'error'
			);

			return false;
		}

		if (version_compare(JVERSION, $this->minimumJoomlaVersion, '<')) {
			$app->enqueueMessage(
				sprintf('The magazine checklist plugin needs Joomla %s or later.', $this->minimumJoomlaVersion),
				'error'
			);

			return false;
		}

		return true;
	}

	/**
	 * Enable the plugin after a fresh install.
	 *
	 * @param   string            $type    install, update or discover_install
	 * @param   InstallerAdapter  $parent  The installer
	 *
	 * @return  void
	 */
	public function postflight($type, $parent): void
	{
		if ($type !== 'install') {
			return;
		}

		$db    = Factory::getContainer()->get(DatabaseInterface::class);
		$query = $db->getQuery(true)
			->update($db->quoteName('#__extensions'))
			->set($db->quoteName('enabled') . ' = 1')
			->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
			->where($db->quoteName('folder') . ' = ' . $db->quote('task'))
			->where($db->quoteName('element') . ' = ' . $db->quote('magazinechecklist'));

		$db->setQuery($query)->execute();
	}
}
