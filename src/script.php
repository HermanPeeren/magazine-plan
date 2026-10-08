<?php

/**
 * @package     MagazineChecklist
 *
 * @copyright   Copyright (C) 2026 Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\Installer\InstallerAdapter;
use Joomla\CMS\Installer\InstallerScriptInterface;
use Joomla\Database\DatabaseInterface;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;

/**
 * Install script for the magazine checklist task plugin, as a service provider:
 * Joomla 6 deprecates the old named-class scripts.
 *
 * It refuses a site it cannot run on, and enables the plugin on a fresh install.
 * Joomla installs plugins disabled, and a disabled task plugin offers no task
 * type, so the first thing anyone would see is that it "does nothing". It still
 * does nothing until somebody creates a task with it.
 */
return new class () implements ServiceProviderInterface {
	public function register(Container $container): void
	{
		$container->set(
			InstallerScriptInterface::class,
			static fn (Container $container): InstallerScriptInterface => new class (
				$container->get(DatabaseInterface::class)
			) implements InstallerScriptInterface {
				/**
				 * The oldest Joomla this runs on: the plugin's provider uses the
				 * lazy plugin loading of Joomla 6.
				 */
				private const MINIMUM_JOOMLA = '6.0';

				/**
				 * The oldest PHP this runs on, which is Joomla 6's own minimum.
				 */
				private const MINIMUM_PHP = '8.3';

				private ?CMSApplicationInterface $app = null;

				public function __construct(private readonly DatabaseInterface $db)
				{
				}

				/**
				 * Called by the installer, which has the application at hand.
				 */
				public function setApplication(CMSApplicationInterface $app): void
				{
					$this->app = $app;
				}

				public function install(InstallerAdapter $adapter): bool
				{
					return true;
				}

				public function update(InstallerAdapter $adapter): bool
				{
					return true;
				}

				public function uninstall(InstallerAdapter $adapter): bool
				{
					return true;
				}

				public function preflight(string $type, InstallerAdapter $adapter): bool
				{
					if ($type === 'uninstall') {
						return true;
					}

					if (version_compare(PHP_VERSION, self::MINIMUM_PHP, '<')) {
						$this->app?->enqueueMessage(
							\sprintf('The magazine checklist plugin needs PHP %s or later.', self::MINIMUM_PHP),
							'error'
						);

						return false;
					}

					if (version_compare(JVERSION, self::MINIMUM_JOOMLA, '<')) {
						$this->app?->enqueueMessage(
							\sprintf('The magazine checklist plugin needs Joomla %s or later.', self::MINIMUM_JOOMLA),
							'error'
						);

						return false;
					}

					return true;
				}

				public function postflight(string $type, InstallerAdapter $adapter): bool
				{
					if ($type !== 'install') {
						return true;
					}

					$query = $this->db->getQuery(true)
						->update($this->db->quoteName('#__extensions'))
						->set($this->db->quoteName('enabled') . ' = 1')
						->where($this->db->quoteName('type') . ' = ' . $this->db->quote('plugin'))
						->where($this->db->quoteName('folder') . ' = ' . $this->db->quote('task'))
						->where($this->db->quoteName('element') . ' = ' . $this->db->quote('magazinechecklist'));

					$this->db->setQuery($query)->execute();

					return true;
				}
			}
		);
	}
};
