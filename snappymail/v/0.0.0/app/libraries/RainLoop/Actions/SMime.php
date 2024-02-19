<?php

namespace RainLoop\Actions;

use SnappyMail\SMime\OpenSSL;
use SnappyMail\SMime\Certificate;

trait SMime
{
	public function DoGetSMimeCertificate() : array
	{
		$result = [
			'key' => '',
			'pkey' => '',
			'cert' => ''
		];
		return $this->DefaultResponse(\array_values(\array_unique($result)));
	}

	/**
	 * Can be use by Identity
	 */
	public function DoCreateSMimeCertificate() : array
	{
		$oAccount = $this->getAccountFromToken();
/*
		$homedir = \rtrim($this->StorageProvider()->GenerateFilePath(
			$oAccount,
			\RainLoop\Providers\Storage\Enumerations\StorageType::ROOT
		), '/') . '/.smime';
*/
		$sName = $this->GetActionParam('name', '') ?: $oAccount->Name();
		$sEmail = $this->GetActionParam('email', '') ?: $oAccount->Email();

		$cert = new Certificate();
		$cert->distinguishedName['commonName'] = $sName;
		$cert->distinguishedName['emailAddress'] = $sEmail;
		$result = $cert->createSelfSigned('demo');
		return $this->DefaultResponse($result ?: false);
	}
}
