<?php

/*
 * This file is part of MailSo.
 *
 * (c) 2014 Usenko Timur
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MailSo\Mail;

use MailSo\Imap\BodyStructure;

/**
 * @category MailSo
 * @package Mail
 */
class Attachment implements \JsonSerializable
{
	private string $sFolder;

	private int $iUid;

	private ?BodyStructure $oBodyStructure;

	function __construct(string $sFolder, int $iUid, BodyStructure $oBodyStructure)
	{
		$this->sFolder = $sFolder;
		$this->iUid = $iUid;
		$this->oBodyStructure = $oBodyStructure;
	}

	public function Clear() : self
	{
		$this->sFolder = '';
		$this->iUid = 0;
		$this->oBodyStructure = null;

		return $this;
	}

	public function Folder() : string
	{
		return $this->sFolder;
	}

	public function Uid() : int
	{
		return $this->iUid;
	}

	public function FileName(bool $bCalculateOnEmpty = false) : string
	{
		if (!$this->oBodyStructure) {
			return '';
		}

		$sFileName = \trim($this->oBodyStructure->FileName());
		if (\strlen($sFileName) || !$bCalculateOnEmpty) {
			return $sFileName;
		}

		$sIdx = '-' . $this->oBodyStructure->PartID();

		$sMimeType = \strtolower(\trim($this->oBodyStructure->ContentType()));
		if ('message/rfc822' === $sMimeType) {
			return "message{$sIdx}.eml";
		}
		if ('text/calendar' === $sMimeType) {
			return "calendar{$sIdx}.ics";
		}
		if ('text/plain' === $sMimeType) {
			return "part{$sIdx}.txt";
		}
		if (\preg_match('@text/(vcard|html|csv|xml|css|asp)@', $sMimeType, $aMatch)
		 || \preg_match('@image/(png|jpeg|gif|bmp|cgm|ief|tiff|webp)@', $sMimeType, $aMatch)) {
			return "part{$sIdx}.{$aMatch[1]}";
		}
		if (\strlen($sMimeType)) {
			return \str_replace('/', $sIdx.'.', $sMimeType);
		}

		return ($this->oBodyStructure->IsInline() ? 'inline' : 'part' ) . $sIdx;
	}

	public function __call(string $name, array $arguments) //: mixed
	{
		return $this->oBodyStructure->{$name}(...$arguments);
	}

	#[\ReturnTypeWillChange]
	public function jsonSerialize()
	{
		return array(
			'@Object' => 'Object/Attachment',
			'Folder' => $this->sFolder,
			'Uid' => (string) $this->iUid,
			'MimeIndex' => (string) $this->oBodyStructure->PartID(),
			'MimeType' => $this->oBodyStructure->ContentType(),
			'MimeTypeParams' => $this->oBodyStructure->ContentTypeParameters(),
			'FileName' => \MailSo\Base\Utils::SecureFileName($this->FileName(true)),
			'EstimatedSize' => $this->oBodyStructure->EstimatedSize(),
			'Cid' => $this->oBodyStructure->ContentID(),
			'ContentLocation' => $this->oBodyStructure->ContentLocation(),
			'IsInline' => $this->oBodyStructure->IsInline()
		);
	}
}
