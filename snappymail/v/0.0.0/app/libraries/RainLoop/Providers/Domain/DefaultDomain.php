<?php

namespace RainLoop\Providers\Domain;

use MailSo\Cache\CacheClient;

class DefaultDomain implements DomainInterface
{
	const CACHE_KEY = '/WildCard/DomainCache/';

	protected string $sDomainPath;

	protected ?CacheClient $oCacher;

	public function __construct(string $sDomainPath, ?CacheClient $oCacher = null)
	{
		$this->sDomainPath = \rtrim(\trim($sDomainPath), '\\/');
		$this->oCacher = $oCacher;
	}

	private static function decodeFileName(string $sName) : string
	{
		return ('default' === $sName)
			? '*'
			: \str_replace('_wildcard_', '*', \MailSo\Base\Utils::IdnToUtf8($sName, true));
	}

	private static function encodeFileName(string $sName) : string
	{
		return ('*' === $sName)
			? 'default'
			: \str_replace('*', '_wildcard_', \MailSo\Base\Utils::IdnToAscii($sName, true));
	}

	private function getWildcardDomainsLine() : string
	{
		if ($this->oCacher) {
			$sResult = $this->oCacher->Get(static::CACHE_KEY);
			if (\strlen($sResult)) {
				return $sResult;
			}
		}

		$aNames = array();

//		$aList = \glob($this->sDomainPath.'/*.{ini,json}', GLOB_BRACE);
		$aList = \glob($this->sDomainPath.'/*.json');
		foreach ($aList as $sFile) {
			$sName = \substr(\basename($sFile), 0, -5);
			if ('default' === $sName || false !== \strpos($sName, '_wildcard_')) {
				$aNames[] = static::decodeFileName($sName);
			}
		}
		$aList = \glob($this->sDomainPath.'/*.ini');
		foreach ($aList as $sFile) {
			$sName = \substr(\basename($sFile), 0, -4);
			if ('default' === $sName || false !== \strpos($sName, '_wildcard_')) {
				$aNames[] = static::decodeFileName($sName);
			}
		}
		$aList = \glob($this->sDomainPath.'/*.alias');
		foreach ($aList as $sFile) {
			$sName = \substr(\basename($sFile), 0, -6);
			if ('default' === $sName || false !== \strpos($sName, '_wildcard_')) {
				$aNames[] = static::decodeFileName($sName);
			}
		}

		$sResult = '';
		if ($aNames) {
			\rsort($aNames, SORT_STRING);
			$sResult = \implode(' ', \array_unique($aNames));
		}

		if ($this->oCacher) {
			$this->oCacher->Set(static::CACHE_KEY, $sResult);
		}

		return $sResult;
	}

	public function Load(string $sName, bool $bFindWithWildCard = false, bool $bCheckDisabled = true, bool $bCheckAliases = true) : ?\RainLoop\Model\Domain
	{
		$sName = \MailSo\Base\Utils::IdnToUtf8($sName, true);
		if ($bCheckDisabled && \in_array($sName, $this->getDisabled())) {
			return null;
		}

		$sRealFileBase = $this->sDomainPath . '/' . static::encodeFileName($sName);

		if (\file_exists($sRealFileBase.'.json')) {
			$aDomain = \json_decode(\file_get_contents($sRealFileBase.'.json'), true) ?: array();
			return \RainLoop\Model\Domain::fromArray($sName, $aDomain);
		}
		if (\file_exists($sRealFileBase.'.ini')) {
			$aDomain = \parse_ini_file($sRealFileBase.'.ini') ?: array();
			return \RainLoop\Model\Domain::fromIniArray($sName, $aDomain);
		}

		if ($bCheckAliases && \file_exists($sRealFileBase.'.alias')) {
			$sAlias = \trim(\file_get_contents($sRealFileBase.'.alias'));
			if (!empty($sAlias)) {
				$oDomain = $this->Load($sAlias, false, false, false);
				$oDomain && $oDomain->SetAliasName($sName);
				return $oDomain;
			}
		}

		if ($bFindWithWildCard) {
			$sNames = $this->getWildcardDomainsLine();
			$sFoundValue = '';
			if (\strlen($sNames)
			 && \RainLoop\Plugins\Helper::ValidateWildcardValues($sName, $sNames, $sFoundValue)
			 && \strlen($sFoundValue)
			) {
				return $this->Load($sFoundValue);
			}
		}

		return null;
	}

	public function Save(\RainLoop\Model\Domain $oDomain) : bool
	{
		$sRealFileName = static::encodeFileName($oDomain->Name());
		\RainLoop\Utils::saveFile($this->sDomainPath.'/'.$sRealFileName.'.json', \json_encode($oDomain, \JSON_PRETTY_PRINT));
		$this->oCacher && $this->oCacher->Delete(static::CACHE_KEY);
		return true;
	}

	public function SaveAlias(string $sName, string $sAlias) : bool
	{
		$sAlias = \MailSo\Base\Utils::IdnToUtf8($sAlias, true);
		$sRealFileName = static::encodeFileName($sName);
		\RainLoop\Utils::saveFile($this->sDomainPath.'/'.$sRealFileName.'.alias', $sAlias);
		$this->oCacher && $this->oCacher->Delete(static::CACHE_KEY);
		return true;
	}

	protected function getDisabled() : array
	{
		$sFile = '';
		if (\file_exists($this->sDomainPath.'/disabled')) {
			$sFile = \file_get_contents($this->sDomainPath.'/disabled');
		}
		$aDisabled = array();
		// RainLoop use comma, we use newline
		$sItem = \strtok($sFile, ",\n");
		while (false !== $sItem) {
			$aDisabled[] = \MailSo\Base\Utils::IdnToUtf8($sItem, true);
			$sItem = \strtok(",\n");
		}
		return $aDisabled;
//		return \array_unique($aDisabled);
	}

	public function Disable(string $sName, bool $bDisable) : bool
	{
		$sName = \MailSo\Base\Utils::IdnToUtf8($sName, true);
		if ($sName) {
			$aResult = $this->getDisabled();
			if ($bDisable) {
				$aResult[] = $sName;
			} else {
				$aResult = \array_filter($aResult, fn($v) => $v !== $sName);
			}
			\RainLoop\Utils::saveFile($this->sDomainPath.'/disabled', \implode("\n", \array_unique($aResult)));
		}
		return true;
	}

	public function Delete(string $sName) : bool
	{
		$bResult = 0 < \strlen($sName);
		if ($bResult) {
			$sRealFileName = static::encodeFileName($sName);
			if (\file_exists($this->sDomainPath.'/'.$sRealFileName.'.json')) {
				$bResult = \unlink($this->sDomainPath.'/'.$sRealFileName.'.json');
			}
			if (\file_exists($this->sDomainPath.'/'.$sRealFileName.'.ini')) {
				$bResult = \unlink($this->sDomainPath.'/'.$sRealFileName.'.ini');
			}
			if (\file_exists($this->sDomainPath.'/'.$sRealFileName.'.alias')) {
				$bResult = \unlink($this->sDomainPath.'/'.$sRealFileName.'.alias');
			}
			if ($bResult) {
				$this->Disable($sName, false);
			}
			if ($this->oCacher) {
				$this->oCacher->Delete(static::CACHE_KEY);
			}
		}
		return $bResult;
	}

	public function GetList(bool $bIncludeAliases = true) : array
	{
		$aDisabledNames = $this->getDisabled();
		$aResult = array();
		$aWildCards = array();
		$aAliases = array();

//		$aList = \glob($this->sDomainPath.'/*.{ini,json,alias}', GLOB_BRACE);
		$aList = \array_diff(\scandir($this->sDomainPath), array('.', '..'));
		foreach ($aList as $sFile) {
			$bAlias = '.alias' === \substr($sFile, -6);
			if ($bAlias || '.json' === \substr($sFile, -5) || '.ini' === \substr($sFile, -4)) {
				$sName = static::decodeFileName(\preg_replace('/\.(ini|json|alias)$/', '', $sFile));
				if ($bAlias) {
					if ($bIncludeAliases) {
						$aAliases[$sName] = array(
							'name' => $sName,
							'disabled' => \in_array($sName, $aDisabledNames),
							'alias' => true
						);
					}
				} else if (false !== \strpos($sName, '*')) {
					$aWildCards[$sName] = array(
						'name' => $sName,
						'disabled' => \in_array($sName, $aDisabledNames),
						'alias' => false
					);
				} else {
					$aResult[$sName] = array(
						'name' => $sName,
						'disabled' => \in_array($sName, $aDisabledNames),
						'alias' => false
					);
				}
			}
		}

		\ksort($aResult, SORT_STRING);
		\ksort($aAliases, SORT_STRING);
		\krsort($aWildCards, SORT_STRING);
		return \array_values(\array_merge($aResult, $aAliases, $aWildCards));
	}
}
