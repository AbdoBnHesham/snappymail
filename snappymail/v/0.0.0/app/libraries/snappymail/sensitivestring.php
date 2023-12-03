<?php

namespace SnappyMail;

function xorIt(
	#[\SensitiveParameter]
	string $value
) : string
{
	$key = APP_SALT;
	$kl = \strlen($key);
	$i = \strlen($value);
	while ($i--) {
		$value[$i] = $value[$i] ^ $key[$i % $kl];
	}
	return $value;
}

class SensitiveString /* extends SensitiveParameterValue | SensitiveParameter */ implements \Stringable
{
	private string $value, $nonce;

	public function __construct(string $value)
	{
		$this->setValue($value);
	}

	public function setValue(
		#[\SensitiveParameter]
		string $value
	) : void
	{
		if (\is_callable('sodium_crypto_secretbox')) {
			$this->nonce = \random_bytes(\SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
//			$this->key = \sodium_crypto_secretbox_keygen();
			$this->value = \sodium_crypto_secretbox($value, $this->nonce, APP_SALT);
		} else {
			$this->value = xorIt($value);
		}
	}

	public function __toString() : string
	{
		if (\is_callable('sodium_crypto_secretbox')) {
			return \sodium_crypto_secretbox_open($this->value, $this->nonce, APP_SALT);
		}
		return xorIt($this->value);
	}
}
