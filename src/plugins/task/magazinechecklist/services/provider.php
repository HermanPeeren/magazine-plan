<?php

/**
 * @package     MagazineChecklist
 *
 * @copyright   Copyright (C) 2026 Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Database\DatabaseInterface;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Http\HttpFactory;
use Yepr\Plugin\Task\MagazineChecklist\Checklist\ChecklistEditor;
use Yepr\Plugin\Task\MagazineChecklist\Checklist\TitleMatcher;
use Yepr\Plugin\Task\MagazineChecklist\Extension\MagazineChecklist;
use Yepr\Plugin\Task\MagazineChecklist\Github\HttpTransport;
use Yepr\Plugin\Task\MagazineChecklist\Github\JoomlaHttpTransport;
use Yepr\Plugin\Task\MagazineChecklist\Sync\ChecklistSyncFactory;
use Yepr\Plugin\Task\MagazineChecklist\Sync\CursorStore;
use Yepr\Plugin\Task\MagazineChecklist\Sync\DatabaseCursorStore;

/**
 * The composition root: the only place the plugin's services are put together.
 *
 * Joomla hands each extension a child container, so what is registered here is
 * the plugin's own and does not leak into the site's container. Every service
 * is shared and built on first use, and the plugin itself is a lazy proxy (on
 * PHP 8.4 and later): a page that never runs a task never builds any of it.
 */
return new class () implements ServiceProviderInterface {
	public function register(Container $container): void
	{
		$container->share(
			HttpTransport::class,
			static fn (): HttpTransport => new JoomlaHttpTransport((new HttpFactory())->getHttp())
		);

		$container->share(
			CursorStore::class,
			static fn (Container $container): CursorStore => new DatabaseCursorStore($container->get(DatabaseInterface::class))
		);

		$container->share(ChecklistEditor::class, static fn (): ChecklistEditor => new ChecklistEditor());
		$container->share(TitleMatcher::class, static fn (): TitleMatcher => new TitleMatcher());

		$container->share(
			ChecklistSyncFactory::class,
			static fn (Container $container): ChecklistSyncFactory => new ChecklistSyncFactory(
				$container->get(HttpTransport::class),
				$container->get(CursorStore::class),
				$container->get(ChecklistEditor::class),
				$container->get(TitleMatcher::class)
			)
		);

		$container->set(
			PluginInterface::class,
			$container->lazy(MagazineChecklist::class, function (Container $container) {
				$plugin = new MagazineChecklist(
					(array) PluginHelper::getPlugin('task', 'magazinechecklist'),
					$container->get(ChecklistSyncFactory::class)
				);
				$plugin->setApplication(Factory::getApplication());

				return $plugin;
			})
		);
	}
};
