<?php

namespace OCA\SnappyMail\Util;

class SnappyMailHelper
{

	public function registerHooks()
	{
		$userSession = \OC::$server->getUserSession();
		$userSession->listen('\OC\User', 'postLogin', function($user, $loginName, $password, $isTokenLogin) {
			$config = \OC::$server->getConfig();
			$sEmail = '';
			// Only store the user's password in the current session if they have
			// enabled auto-login using Nextcloud username or email address.
			if ($config->getAppValue('snappymail', 'snappymail-autologin', false)) {
				$sEmail = $user->getUID();
			} else if ($config->getAppValue('snappymail', 'snappymail-autologin-with-email', false)) {
				$sEmail = $config->getUserValue($user->getUID(), 'settings', 'email', '');
			}
			if ($sEmail) {
				static::startApp(true);
				\OC::$server->getSession()['snappymail-sso-hash'] = \RainLoop\Api::CreateUserSsoHash($sEmail, $password/*, array $aAdditionalOptions = array(), bool $bUseTimeout = true*/);
			}
		});

		$userSession->listen('\OC\User', 'logout', function($user) {
			\OC::$server->getSession()['snappymail-sso-hash'] = '';
			static::startApp(true);
			\RainLoop\Api::LogoutCurrentLogginedUser();
		});
	}

	public static function startApp(bool $api = false)
	{
		if (!\class_exists('RainLoop\\Api')) {
			$_ENV['SNAPPYMAIL_NEXTCLOUD'] = true;

			// Nextcloud the default spl_autoload_register() not working
			\spl_autoload_register(function($sClassName){
				$file = RAINLOOP_APP_LIBRARIES_PATH . \strtolower(\strtr($sClassName, '\\', DIRECTORY_SEPARATOR)) . '.php';
				if (is_file($file)) {
					include_once $file;
				}
			});

			$path = \OC::$server->getAppManager()->getAppPath('snappymail') . '/app/';

			$sData = \rtrim(\trim(\OC::$server->getSystemConfig()->getValue('datadirectory', '')), '\\/').'/appdata_snappymail/';
			if (!\is_file("{$path}include.php")) {
				\file_put_contents("{$path}include.php",
					\file_get_contents("{$path}_include.php")
					. "define('APP_DATA_FOLDER_PATH', '{$sData}');\n"
				);
			}
//			\define('APP_DATA_FOLDER_PATH', $sData);

			if ($api) {
				$_ENV['SNAPPYMAIL_INCLUDE_AS_API'] = true;
			}

			require_once "{$path}index.php";

			if ($api) {
				$oConfig = \RainLoop\Api::Config();
				if (!$oConfig->Get('webmail', 'app_path')) {
					$oConfig->Set('webmail', 'app_path', \OC::$server->getAppManager()->getAppWebPath('snappymail') . '/app/');
					$oConfig->Save();
				}
/*
				if (!\is_dir(APP_PLUGINS_PATH . 'nextcloud')) {
					\SnappyMail\Repository::installPackage('plugin', 'nextcloud');
					$oConfig->Set('plugins', 'enable', true);
					$aList = \SnappyMail\Repository::getEnabledPackagesNames();
					$aList[] = 'nextcloud';
					$oConfig->Set('plugins', 'enabled_list', \implode(',', \array_unique($aList)));
					$oConfig->Save();
				}
*/
			}
		}
	}

	/**
	 * @return string
	 */
	public static function getAppUrl()
	{
		$sRequestUri = \OC::$server->getURLGenerator()->linkToRoute('snappymail.page.appGet');
		if ($sRequestUri) {
			return $sRequestUri;
		}
		$sRequestUri = empty($_SERVER['REQUEST_URI']) ? '': \trim($_SERVER['REQUEST_URI']);
		$sRequestUri = \preg_replace('/index.php\/.+$/', 'index.php/', $sRequestUri);
		$sRequestUri = $sRequestUri.'apps/snappymail/app/';
		return '/'.\ltrim($sRequestUri, '/\\');
	}

	/**
	 * @param string $sUrl
	 *
	 * @return string
	 */
	public static function normalizeUrl($sUrl)
	{
		$sUrl = \rtrim(\trim($sUrl), '/\\');
		if ('.php' !== \strtolower(\substr($sUrl, -4))) {
			$sUrl .= '/';
		}

		return $sUrl;
	}

	/**
	 * @param string $sPassword
	 * @param string $sSalt
	 *
	 * @return string
	 */
	public static function encodePassword($sPassword, $sSalt)
	{
		static::startApp(true);
		return \SnappyMail\Crypt::EncryptUrlSafe($sPassword, $sSalt);
	}

	/**
	 * @param string $sPassword
	 * @param string $sSalt
	 *
	 * @return string
	 */
	public static function decodePassword($sPassword, $sSalt)
	{
		static::startApp(true);
		return \SnappyMail\Crypt::DecryptUrlSafe($sPassword, $sSalt);
	}
}
