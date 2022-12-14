<?php

/*
 * This file is part of MailSo.
 *
 * (c) 2014 Usenko Timur
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MailSo\Imap;

use MailSo\Imap\Enumerations\FolderType;
use MailSo\Imap\Enumerations\MetadataKeys;

/**
 * @category MailSo
 * @package Imap
 */
class Folder implements \JsonSerializable
{
	// RFC5258 Response data STATUS items when using LIST-EXTENDED
	use Traits\Status;

	private ?string $sDelimiter;

	private array $aFlagsLowerCase;

	/**
	 * RFC 5464
	 */
	private array $aMetadata = array();

	/**
	 * @throws \InvalidArgumentException
	 */
	function __construct(string $sFullName, string $sDelimiter = null, array $aFlags = array())
	{
		if (!\strlen($sFullName)) {
			throw new \InvalidArgumentException;
		}
		$this->FolderName = $sFullName;
		$this->setDelimiter($sDelimiter);
		$this->setFlags($aFlags);
/*
		// RFC 5738
		if (\in_array('\\noutf8', $this->aFlagsLowerCase)) {
		}
		if (\in_array('\\utf8only', $this->aFlagsLowerCase)) {
		}
*/
	}

	public function setFlags(array $aFlags) : void
	{
		$this->aFlagsLowerCase = \array_map('mb_strtolower', $aFlags);
	}

	public function setSubscribed() : void
	{
		$this->aFlagsLowerCase = \array_unique(\array_merge($this->aFlagsLowerCase, ['\\subscribed']));
	}

	public function setDelimiter(?string $sDelimiter) : void
	{
		$this->sDelimiter = $sDelimiter;
	}

	public function Name() : string
	{
		$sNameRaw = $this->FolderName;
		if ($this->sDelimiter) {
			$aNames = \explode($this->sDelimiter, $sNameRaw);
			return \end($aNames);
		}
		return $sNameRaw;
	}

	public function FullName() : string
	{
		return $this->FolderName;
	}

	public function Delimiter() : ?string
	{
		return $this->sDelimiter;
	}

	public function FlagsLowerCase() : array
	{
		return $this->aFlagsLowerCase;
	}

	public function Exists() : bool
	{
		return !\in_array('\\nonexistent', $this->aFlagsLowerCase);
	}

	public function Selectable() : bool
	{
		return !\in_array('\\noselect', $this->aFlagsLowerCase) && $this->Exists();
	}

	public function IsSubscribed() : bool
	{
		return \in_array('\\subscribed', $this->aFlagsLowerCase);
	}

	public function IsInbox() : bool
	{
		return 'INBOX' === \strtoupper($this->FolderName) || \in_array('\\inbox', $this->aFlagsLowerCase);
	}

	public function SetMetadata(string $sName, string $sData) : void
	{
		$this->aMetadata[$sName] = $sData;
	}

	public function SetAllMetadata(array $aMetadata) : void
	{
		$this->aMetadata = $aMetadata;
	}

	public function GetMetadata(string $sName) : ?string
	{
		return isset($this->aMetadata[$sName]) ? $this->aMetadata[$sName] : null;
	}

	public function Metadata() : array
	{
		return $this->aMetadata;
	}

	// JMAP RFC 8621
	public function Role() : ?string
	{
		$aFlags = $this->aFlagsLowerCase;
		$aFlags[] = \strtolower($this->GetMetadata(MetadataKeys::SPECIALUSE));

		$match = \array_intersect([
			'\\inbox',
			'\\all',       // '\\allmail'
			'\\archive',
			'\\drafts',
			'\\flagged',   // '\\starred'
			'\\important',
			'\\junk',      // '\\spam'
			'\\sent',      // '\\sentmail'
			'\\trash',     // '\\bin'
		], $aFlags);
		if ($match) {
			return \substr(\array_shift($match), 1);
		}

		if ('INBOX' === \strtoupper($this->FolderName)) {
			return 'inbox';
		}
/*
		// Kolab
		$type = $this->GetMetadata(MetadataKeys::KOLAB_CTYPE) ?: $this->GetMetadata(MetadataKeys::KOLAB_CTYPE_SHARED);
		switch ($type) {
			case 'mail.inbox':
				return 'inbox';
//			case 'mail.outbox':
			case 'mail.sentitems':
				return 'sent';
			case 'mail.drafts':
				return 'drafts';
			case 'mail.junkemail':
				return 'junk';
			case 'mail.wastebasket':
				return 'trash';
		}
*/
		return null;
	}

	public function Hash(string $sClientHash) : ?string
	{
		return $this->getHash($sClientHash);
	}

	public function GetType() : int
	{
		$aFlags = $this->aFlagsLowerCase;
		// RFC 6154
//		$aFlags[] = \strtolower($this->GetMetadata(MetadataKeys::SPECIALUSE));

		switch (true)
		{
			case $this->IsInbox():
				return FolderType::INBOX;

			case \in_array('\\sent', $this->aFlagsLowerCase):
			case \in_array('\\sentmail', $this->aFlagsLowerCase):
				return FolderType::SENT;

			case \in_array('\\drafts', $this->aFlagsLowerCase):
				return FolderType::DRAFTS;

			case \in_array('\\junk', $this->aFlagsLowerCase):
			case \in_array('\\spam', $this->aFlagsLowerCase):
				return FolderType::JUNK;

			case \in_array('\\trash', $this->aFlagsLowerCase):
			case \in_array('\\bin', $this->aFlagsLowerCase):
				return FolderType::TRASH;

			case \in_array('\\important', $this->aFlagsLowerCase):
				return FolderType::IMPORTANT;

			case \in_array('\\flagged', $this->aFlagsLowerCase):
			case \in_array('\\starred', $this->aFlagsLowerCase):
				return FolderType::FLAGGED;

			case \in_array('\\archive', $this->aFlagsLowerCase):
				return FolderType::ARCHIVE;

			case \in_array('\\all', $this->aFlagsLowerCase):
			case \in_array('\\allmail', $this->aFlagsLowerCase):
				return FolderType::ALL;

			// TODO
//			case 'Templates' === $this->FullName():
//				return FolderType::TEMPLATES;
		}

		// Kolab
		$type = $this->GetMetadata(MetadataKeys::KOLAB_CTYPE) ?: $this->GetMetadata(MetadataKeys::KOLAB_CTYPE_SHARED);
		switch ($type)
		{
/*
			// TODO: Kolab
			case 'event':
			case 'event.default':
				return FolderType::CALENDAR;
			case 'contact':
			case 'contact.default':
				return FolderType::CONTACTS;
			case 'task':
			case 'task.default':
				return FolderType::TASKS;
			case 'note':
			case 'note.default':
				return FolderType::NOTES;
			case 'file':
			case 'file.default':
				return FolderType::FILES;
			case 'configuration':
				return FolderType::CONFIGURATION;
			case 'journal':
			case 'journal.default':
				return FolderType::JOURNAL;
*/
			case 'mail.inbox':
				return FolderType::INBOX;
//			case 'mail.outbox':
			case 'mail.sentitems':
				return FolderType::SENT;
			case 'mail.drafts':
				return FolderType::DRAFTS;
			case 'mail.junkemail':
				return FolderType::JUNK;
			case 'mail.wastebasket':
				return FolderType::TRASH;
		}

		return FolderType::USER;
	}

	#[\ReturnTypeWillChange]
	public function jsonSerialize()
	{
/*
		$aExtended = null;
		if (isset($this->MESSAGES, $this->UNSEEN, $this->UIDNEXT)) {
			$aExtended = array(
				'totalEmails' => (int) $this->MESSAGES,
				'unreadEmails' => (int) $this->UNSEEN,
				'UidNext' => (int) $this->UIDNEXT,
//				'Hash' => $this->Hash($this->MailClient()->GenerateImapClientHash())
			);
		}
*/
/*
		if ($this->ImapClient->IsSupported('ACL') || $this->ImapClient->CapabilityValue('RIGHTS')) {
			// MailSo\Imap\Responses\ACL
			$rights = $this->ImapClient->FolderMyRights($this->FolderName);
		}
*/
		return array(
			'@Object' => 'Object/Folder',
			'name' => $this->Name(),
			'FullName' => $this->FolderName,
			'Delimiter' => (string) $this->sDelimiter,
			'isSubscribed' => $this->IsSubscribed(),
			'Exists' => $this->Exists(),
			'Selectable' => $this->Selectable(),
			'Flags' => $this->aFlagsLowerCase,
//			'Extended' => $aExtended,
//			'PermanentFlags' => $this->PermanentFlags,
			'Metadata' => $this->aMetadata,
			'UidNext' => $this->UIDNEXT,
			// https://datatracker.ietf.org/doc/html/rfc8621#section-2
			'totalEmails' => $this->MESSAGES,
			'unreadEmails' => $this->UNSEEN,
			'id' => $this->MAILBOXID,
			'role' => $this->Role()
/*
			'myRights' => [
				'mayReadItems'   => !$rights || ($rights->hasRight('l') && $rights->hasRight('r')),
				'mayAddItems'    => !$rights || $rights->hasRight('i'),
				'mayRemoveItems' => !$rights || ($rights->hasRight('t') && $rights->hasRight('e')),
				'maySetSeen'     => !$rights || $rights->hasRight('s'),
				'maySetKeywords' => !$rights || $rights->hasRight('w'),
				'mayCreateChild' => !$rights || $rights->hasRight('k'),
				'mayRename'      => !$rights || $rights->hasRight('x'),
				'mayDelete'      => !$rights || $rights->hasRight('x'),
				'maySubmit'      => !$rights || $rights->hasRight('p')
			]
*/
		);
	}
}
