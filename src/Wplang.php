<?php

namespace Mirai\Wplang;

use Composer\Composer;
use Composer\Script\Event;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Installer\PackageEvent;
use Composer\DependencyResolver\Operation\InstallOperation;
use Composer\Package\PackageInterface;

class Wplang implements PluginInterface, EventSubscriberInterface {

	/**
	 * Array of the languages we are using.
	 *
	 * @var array
	 */
	protected $languages = [];

	/**
	 * Full path to the language files target directory.
	 *
	 * @var string
	 */
	protected $wpLanguageDir = '';

	/**
	 * @var Composer
	 */
	protected $composer;

	/**
	 * @var IOInterface
	 */
	protected $io;

	/**
	 * Composer plugin activation.
	 */
	public function activate( Composer $composer, IOInterface $io ) {
		$this->composer = $composer;
		$this->io = $io;
		$extra = $this->composer->getPackage()->getExtra();

		if ( ! empty( $extra['wordpress-languages'] ) ) {
			$this->languages = $extra['wordpress-languages'];
		}

		if ( ! empty( $extra['wordpress-language-dir'] ) ) {
			$this->wpLanguageDir = dirname( dirname( dirname( dirname( __DIR__ ) ) ) ) . '/' . $extra['wordpress-language-dir'];
		}
	}


	/**
	 * Subscribe to Composer events.
	 *
	 * @return array The events and callbacks.
	 */
	public static function getSubscribedEvents() {
		return [
			'post-package-install' => [
				[ 'onPackageAction', 0 ],
			],
			'post-package-update' => [
				[ 'onPackageAction', 0 ],
			],
		];
	}

	/**
	 * Our callback for the post-package-install event.
	 *
	 * @param  PackageEvent $event The package event object.
	 */
	public function onPackageAction( PackageEvent $event ) {
        $op = $event->getOperation();
        if ( 'update' === $op->getOperationType() ) {
            $package = $op->getTargetPackage();
        } else {
            $package = $op->getPackage();
        }
		$this->getTranslations( $package );
	}

	/**
	 * Get translations for a package, where applicable.
	 *
	 * @param PackageInterface $package
	 */
	protected function getTranslations( PackageInterface $package ) {

		try {
			$t = new \stdClass();

			list( $provider, $name ) = explode( '/', $package->getName(), 2 );

			switch ( $package->getType() ) {
				case 'wordpress-plugin':
					$t = new Translatable( 'plugin', $name, $package->getVersion(), $this->languages, $this->wpLanguageDir );
					break;
				case 'wordpress-theme':
					$t = new Translatable( 'theme', $name, $package->getVersion(), $this->languages, $this->wpLanguageDir );
					break;
				case 'package': case 'wordpress-core':
					if ( in_array($provider, ['roots', 'johnpbloch']) && in_array($name, ['wordpress', 'wordpress-no-content', 'wordpress-full', 'wordpress-core']) ) {
						$t = new Translatable( 'core', $name, $package->getVersion(), $this->languages, $this->wpLanguageDir );
					}
					break;

				default:
					break;
			}

			if ( is_a( $t, __NAMESPACE__ . '\Translatable' ) ) {

				$results = $t->fetch();

				if ( empty( $results ) ) {
					$this->io->write( '      - ' . sprintf( 'No translations updated for %s', $package->getName() ) );
				} else {
					foreach ( $results as $result ) {
						$this->io->write( '      - ' . sprintf( 'Updated translation to %1$s for %2$s', $result, $package->getName() ) );
					}
				}
			}
		} catch ( \Exception $e ) {
			$this->io->writeError( 'ERROR: ' . $e->getMessage() );
		}

	}

    public function deactivate(Composer $composer, IOInterface $io)
    {
        // TODO: Implement deactivate() method.
    }

    public function uninstall(Composer $composer, IOInterface $io)
    {
        // TODO: Implement uninstall() method.
    }
}
