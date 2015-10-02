<?php

namespace PluginUploader {
	use Minute\Composer\Installer;

	class Plugin {
		public static function install() {
			if ( $installer = Installer::getInstance() ) {			
				if ($fn = realpath(__DIR__ . '/install.sql')) {
					$installer->importSQL($fn); 
				}
			}
		}

		public static function uninstall() {
			if ($fn = realpath(__DIR__ . '/remove.sql')) {
				Installer::getInstance()->importSQL($fn);
			}
		}
	}
}		