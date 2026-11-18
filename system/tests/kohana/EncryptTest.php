<?php

/**
 * Tests the encrypt class
 *
 * @group kohana
 * @group kohana.core
 * @group kohana.core.encrypt
 *
 * @package    Kohana
 * @category   Tests
 * @author     Kohana Team
 * @author     Samuel Demirdjian <sam@enov.ws>
 * @copyright  (c) 2014 Kohana Team
 * @license    http://kohanaframework.org/license
 */
class Kohana_EncryptTest extends Unittest_TestCase
{
	const KEY = 'trwQwVXX96TIJoKxyBHB9AJkwAOHixuV1ENZmIWyanI0j1zNgSVvqywy044Agaj';

	/**
	 * @return array of $txt_plain
	 */
	public function provider_plain_texts()
	{
		return array(
			array(''),
			array('a'),
			array('The quick brown fox jumps over the lazy dog'),
			array(str_repeat('x', 16)),
			array("binary \x00\x01\xff data with trailing nulls\x00\x00"),
			array('Lorem ipsum dolor sit amet, consectetur adipisicing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.'),
		);
	}

	/**
	 * @dataProvider provider_plain_texts
	 * @covers Encrypt::encode
	 * @covers Encrypt::decode
	 */
	public function test_encode_decode($txt_plain)
	{
		$e = new Encrypt(self::KEY);

		$this->assertSame($txt_plain, $e->decode($e->encode($txt_plain)));
	}

	/**
	 * @dataProvider provider_plain_texts
	 * @covers Encrypt::encode
	 */
	public function test_encode_output_is_printable($txt_plain)
	{
		$e = new Encrypt(self::KEY);

		$this->assertMatchesRegularExpression('#^v2:[A-Za-z0-9+/]+={0,2}$#D', $e->encode($txt_plain));
	}

	/**
	 * @dataProvider provider_plain_texts
	 * @covers Encrypt::encode
	 */
	public function test_consecutive_encode_produce_different_results($txt_plain)
	{
		$e = new Encrypt(self::KEY);

		$this->assertNotSame($e->encode($txt_plain), $e->encode($txt_plain));
	}

	/**
	 * @covers Encrypt::decode
	 */
	public function test_decode_fails_with_other_key()
	{
		$e1 = new Encrypt(self::KEY);
		$e2 = new Encrypt(self::KEY.'x');

		$this->assertFalse($e2->decode($e1->encode('secret')));
	}

	/**
	 * Changing any byte of the IV, ciphertext or MAC must make decode() fail.
	 *
	 * @covers Encrypt::decode
	 */
	public function test_decode_rejects_changed_values()
	{
		$e = new Encrypt(self::KEY);

		$encoded = $e->encode('The quick brown fox jumps over the lazy dog');
		$raw = base64_decode(substr($encoded, 3));

		for ($i = 0; $i < strlen($raw); $i++)
		{
			$changed = $raw;
			$changed[$i] = chr(ord($changed[$i]) ^ 0x01);

			$this->assertFalse($e->decode('v2:'.base64_encode($changed)), 'Byte '.$i.' was changed');
		}

		// Truncated and extended values
		$this->assertFalse($e->decode('v2:'.base64_encode(substr($raw, 0, -1))));
		$this->assertFalse($e->decode('v2:'.base64_encode(substr($raw, 16))));
		$this->assertFalse($e->decode('v2:'.base64_encode($raw.'x')));
	}

	/**
	 * @return array of $txt_invalid_encoded
	 */
	public function provider_decode_invalid_data()
	{
		return array(
			array(NULL),
			array(array()),
			array(''),
			array('v2:'),
			array('v2:.:This data is not a valid base 64 string:.'),
			array('v2:'.base64_encode('too short')),
			array(base64_encode(str_repeat('a', 64))),
			array('.:This data is not a valid base 64 string:.'),
		);
	}

	/**
	 * @dataProvider provider_decode_invalid_data
	 * @covers Encrypt::decode
	 */
	public function test_decode_invalid_data($txt_invalid_encoded)
	{
		$e = new Encrypt(self::KEY);

		$this->assertFalse($e->decode($txt_invalid_encoded));
	}

	/**
	 * Values from the mcrypt Encrypt of Kohana 3.3.6, made with PHP 5.6 and
	 * ext/mcrypt (test_data/encrypt_mcrypt_vectors.json).
	 *
	 * @return array of $mode, $key, $txt_plain, $txt_encoded
	 */
	public function provider_mcrypt_vectors()
	{
		$file = Kohana::find_file('tests', 'test_data/encrypt_mcrypt_vectors', 'json');
		$data = array();

		foreach (json_decode(file_get_contents($file), TRUE) as $row)
		{
			list($mode, $key, $txt_plain, $txt_encoded) = $row;

			$data[] = array($mode, base64_decode($key), base64_decode($txt_plain), $txt_encoded);
		}

		return $data;
	}

	/**
	 * @dataProvider provider_mcrypt_vectors
	 * @covers Encrypt::decode
	 */
	public function test_decode_mcrypt_values_in_legacy_mode($mode, $key, $txt_plain, $txt_encoded)
	{
		$e = new Encrypt($key, $mode, 'rijndael-128', TRUE);

		// mcrypt padded with \0 bytes and decode() removed them
		$this->assertSame(rtrim($txt_plain, "\0"), $e->decode($txt_encoded));
	}

	/**
	 * Unauthenticated mcrypt values must not decode unless legacy mode is on.
	 *
	 * @dataProvider provider_mcrypt_vectors
	 * @covers Encrypt::decode
	 */
	public function test_mcrypt_values_rejected_by_default($mode, $key, $txt_plain, $txt_encoded)
	{
		$e = new Encrypt($key, $mode, 'rijndael-128');

		$this->assertFalse($e->decode($txt_encoded));
	}

	/**
	 * AES Multiblock Message Test (MMT) Sample Vectors - Known Answer Test (KAT)
	 * @link http://csrc.nist.gov/groups/STM/cavp/index.html NIST - Cryptographic Algorithm Validation Program
	 * @link http://csrc.nist.gov/groups/STM/cavp/documents/aes/aesmmt.zip file used CBCMMT128.rsp
	 *
	 * @return array of $mode, $cipher, $key, $iv, $txt_encoded, $txt_plain
	 */
	public function provider_decode()
	{
		return array(
			array(
				// mode
				'cbc',
				// cypher
				'rijndael-128',
				// key
				pack("H*", "6a7082cf8cda13eff48c8158dda206ae"),
				// IV
				pack("H*", "bd4172934078c2011cb1f31cffaf486e"),
				// txt_encoded
				pack("H*", "f8eb31b31e374e960030cd1cadb0ef0c"),
				// txt_plain
				pack("H*", "940bc76d61e2c49dddd5df7f37fcf105"),
			),
			array(
				// mode
				'cbc',
				// cypher
				'rijndael-128',
				// key
				pack("H*", "625eefa18a4756454e218d8bfed56e36"),
				// IV
				pack("H*", "73d9d0e27c2ec568fbc11f6a0998d7c8"),
				// txt_encoded
				pack("H*", "5d6fed86f0c4fe59a078d6361a142812514b295dc62ff5d608a42ea37614e6a1"),
				// txt_plain
				pack("H*", "360dc1896ce601dfb2a949250067aad96737847a4580ede2654a329b842fe81e"),
			),
			array(
				// mode
				'cbc',
				// cypher
				'rijndael-128',
				// key
				pack("H*", "fd6e0b954ae2e3b723d6c9fcae6ab09b"),
				// IV
				pack("H*", "f08b65c9f4dd950039941da2e8058c4e"),
				// txt_encoded
				pack("H*", "e29e3114c8000eb484395b256b1b3267894f290d3999819ff35da03e6463c186c4d7ebb964941f1986a2d69572fcaba8"),
				// txt_plain
				pack("H*", "a206385945b21f812a9475f47fddbb7fbdda958a8d14c0dbcdaec36e8b28f1f6ececa1ceae4ce17721d162c1d42a66c1"),
			),
			array(
				// mode
				'cbc',
				// cypher
				'rijndael-128',
				// key
				pack("H*", "7b1ab9144b0239315cd5eec6c75663bd"),
				// IV
				pack("H*", "0b1e74f45c17ff304d99c059ce5cde09"),
				// txt_encoded
				pack("H*", "d3f89b71e033070f9d7516a6cb4ea5ef51d6fb63d4f0fea089d0a60e47bbb3c2e10e9ba3b282c7cb79aefe3068ce228377c21a58fe5a0f8883d0dbd3d096beca"),
				// txt_plain
				pack("H*", "b968aeb199ad6b3c8e01f26c2edad444538c78bfa36ed68ca76123b8cdce615a01f6112bb80bfc3f17490578fb1f909a52e162637b062db04efee291a1f1af60"),
			),
			array(
				// mode
				'cbc',
				// cypher
				'rijndael-128',
				// key
				pack("H*", "36466b6bd25ea3857ea42f0cac1919b1"),
				// IV
				pack("H*", "7186fb6bdfa98a16189544b228f3bcd3"),
				// txt_encoded
				pack("H*", "9ed957bd9bc52bba76f68cfbcde52157a8ca4f71ac050a3d92bdebbfd7c78316b4c9f0ba509fad0235fdafe90056ad115dfdbf08338b2acb1c807a88182dd2a882d1810d4302d598454e34ef2b23687d"),
				// txt_plain
				pack("H*", "999983467c47bb1d66d7327ab5c58f61ddb09b93bd2460cb78cbc12b5fa1ea0c5f759ccc5e478697687012ff4673f6e61eecaeda0ccad2d674d3098c7d17f887b62b56f56b03b4d055bf3a4460e83efa"),
			),
			array(
				// mode
				'cbc',
				// cypher
				'rijndael-128',
				// key
				pack("H*", "89373ee6e28397640d5082eed4123239"),
				// IV
				pack("H*", "1a74d7c859672c804b82472f7e6d3c6b"),
				// txt_encoded
				pack("H*", "1bcba44ddff503db7c8c2ec4c4eea0e827957740cce125c1e11769842fa97e25f1b89269e6d77923a512a358312f4ba1cd33f2d111280cd83e1ef9e7cf7036d55048d5c273652afa611cc81b4e9dac7b5078b7c4716062e1032ead1e3329588a"),
				// txt_plain
				pack("H*", "45efd00daa4cdc8273ef785cae9e944a7664a2391e1e2c449f475acec0124bbc22944331678617408a1702917971f4654310ffb9229bec6173715ae512d37f93aaa6abf009f7e30d65669d1db0366b5bce4c7b00f871014f5753744a1878dc57"),
			),
			array(
				// mode
				'cbc',
				// cypher
				'rijndael-128',
				// key
				pack("H*", "bab0cceddc0abd63e3f82e9fbff7b8aa"),
				// IV
				pack("H*", "68b9140f300490c5c942f66e777eb806"),
				// txt_encoded
				pack("H*", "c65b94b1f291fa9f0600f22c3c0432c895ad5d177bcccc9ea44e8ec339c9adf43855b326179d6d81aa36ef59462fd86127e9d81b0f286f93306bf74d4c79e47c1b3d4b74edd3a16290e3c63b742e41f20d66ceee794316bb63d3bd002712a1b136ba6185bd5c1dab81b07db90d2af5e5"),
				// txt_plain
				pack("H*", "c5585ff215bbb73ba5393440852fb199436de0d15e55c631f877670aa3eda9f672eb1f876f09544e63558436b8928000db2f02a5ad90f95b05ac4cf49e198e617e7678480fdf0efacc6aae691271e6cdd3541ebf719a1ccaedb24e2f80f92455dd5910cb5086b0960a3942ec182dcbd7"),
			),
			array(
				// mode
				'cbc',
				// cypher
				'rijndael-128',
				// key
				pack("H*", "9c702898efa44557b29ed283f5bc0293"),
				// IV
				pack("H*", "cec6e1b82e8b2a591a9fa5ff1cf5cc51"),
				// txt_encoded
				pack("H*", "ba9f646755dacc22911f51d7de2f7e7cb0bc0b75257ea44fe883edb055c7c28ede04c3a0adcb10128ad4517d0093fa16bb0bcd2635e7a0ba92c7609bc8d8568002a7a983473724d256513aa7d51b477aabec1975ab5faf2872a6407e922180eff02f1ef86a4591c8bd3d143da6f0ef0e4806f94ace0d5b0151c99640fccbc843"),
				// txt_plain
				pack("H*", "1d1f8d81bdc3e2c7cb057f408e6450000c5aaed3260ff1e87fbb6f324df6887ffd8f78d7e2a04c9ed9deda9d64482d2b002f4a2b78d8b4f691875c8295d4a64b22257ceaf713ed2f4b92530d7ad7151d629acda882b4829577a43990b0948c1149c22fe4273656d1b08833930e8b06709a94579a78fc220f7057bbc1fa9f6563"),
			),
			array(
				// mode
				'cbc',
				// cypher
				'rijndael-128',
				// key
				pack("H*", "5674636dbdb38f705f0b08c372ef4785"),
				// IV
				pack("H*", "3f20ce0509b57420d53b6be4d0b7f0a9"),
				// txt_encoded
				pack("H*", "198351f453103face6655666fe90bdbd9630e3733b2d66c013a634e91f2bf015bd2d975d71b26322e44defa32d4e9dce50363557046ece08ba38f258dae5fd3e5049c647476c81e73482e40c171d89f9fea29452caf995733589b0061464fbd5dabe27dc5ea463a3deeb7dcb43664ae6a65c498c143883ab8e83b51e5410b181647602443dc3cfffe86f0205398fa83c"),
				// txt_plain
				pack("H*", "6d40fd2f908f48ce19241b6b278b1b1676dffd4a97ce9f8a1574c33bc59237deb536bee376fd6c381e6987700e39283aa111cf1a59f26fae6fb6700bf012646a2ab80239bf5e1632329043aa87d7911978b36523a2bc0bed9a9737ccf7a00baa2f3822b4e9e742e168e7069290705fed2eb63aa044b78f97dd33a8d6b24741ec1fd8c8db79d93b884e762dba0f406961"),
			),
			array(
				// mode
				'cbc',
				// cypher
				'rijndael-128',
				// key
				pack("H*", "97a1025529b9925e25bbe78770ca2f99"),
				// IV
				pack("H*", "d4b4eab92aa9637e87d366384ed6915c"),
				// txt_encoded
				pack("H*", "22cdc3306fcd4d31ccd32720cbb61bad28d855670657c48c7b88c31f4fa1f93c01b57da90be63ead67d6a325525e6ed45083e6fb70a53529d1fa0f55653b942af59d78a2660361d63a7290155ac5c43312a25b235dacbbc863faf00940c99624076dfa44068e7c554c9038176953e571751dfc0954d41d113771b06466b1c8d13e0d4cb675ed58d1a619e1540970983781dc11d2dd8525ab5745958d615defda"),
				// txt_plain
				pack("H*", "e8b89150d8438bf5b17449d6ed26bd72127e10e4aa57cad85283e8359e089208e84921649f5b60ea21f7867cbc9620560c4c6238db021216db453c9943f1f1a60546173daef2557c3cdd855031b353d4bf176f28439e48785c37d38f270aa4a6faad2baabcb0c0b2d1dd5322937498ce803ba1148440a52e227ddba4872fe4d81d2d76a939d24755adb8a7b8452ceed2d179e1a5848f316f5c016300a390bfa7"),
			),
		);
	}

	/**
	 * Legacy mode decodes base64(IV + ciphertext), as the mcrypt Encrypt wrote it.
	 *
	 * @dataProvider provider_decode
	 * @covers Encrypt::decode
	 */
	public function test_decode_nist_vectors_in_legacy_mode($mode, $cipher, $key, $iv, $txt_encoded, $txt_plain)
	{
		$e = new Encrypt($key, $mode, $cipher, TRUE);

		$this->assertSame(rtrim($txt_plain, "\0"), $e->decode(base64_encode($iv.$txt_encoded)));
	}

	/**
	 * @covers Encrypt::decode
	 */
	public function test_legacy_mode_rejects_unsupported_cipher()
	{
		$e = new Encrypt(self::KEY, 'cbc', 'blowfish', TRUE);

		$this->assertFalse($e->decode(base64_encode(str_repeat('a', 32))));
	}

	/**
	 * @covers Encrypt::__construct
	 */
	public function test_constructor_rejects_empty_key()
	{
		$this->expectException(Kohana_Exception::class);

		new Encrypt('');
	}

	/**
	 * @covers Encrypt::instance
	 */
	public function test_instance_throw_exception_when_no_key_provided()
	{
		$this->expectException(Kohana_Exception::class);
		$this->expectExceptionMessage('No encryption key is defined in the encryption configuration group');

		Encrypt::instance();
	}

	/**
	 * Provider for test_instance_returns_singleton
	 *
	 * @return array of $instance_name, $config_array
	 */
	public function provider_instance_returns_singleton()
	{
		return array(
			array(
				'default',
				array(
					'key' => self::KEY,
				)
			),
			array(
				'legacy_cbc',
				array(
					'key'    => '7bZJJkmNrelj5NaKoY6h6rMSRSmeUlJuTeOd5HHka5XknyMX4uGSfeVolTz4IYy',
					'cipher' => 'rijndael-128',
					'mode'   => 'cbc',
					'legacy' => TRUE,
				)
			),
		);
	}

	/**
	 * Multiple calls to instance() return the same, configured instance.
	 *
	 * @param string $instance_name instance name
	 * @param array $config_array array of config variables missing from config
	 *
	 * @dataProvider provider_instance_returns_singleton
	 * @covers Encrypt::instance
	 */
	public function test_instance_returns_singleton($instance_name, array $config_array)
	{
		$config = Kohana::$config->load('encrypt');

		$config_group = $instance_name ? : Encrypt::$default;

		if ( ! isset($config[$config_group]))
		{
			$config[$config_group] = array();
		}

		$config[$config_group] = $config_array + $config[$config_group];

		$e = Encrypt::instance($instance_name);
		$e2 = Encrypt::instance($instance_name);

		$this->assertInstanceOf('Encrypt', $e);
		$this->assertSame($e, $e2);

		$this->assertSameProtectedProperty($config_array['key'], $e, '_key');
		$this->assertSameProtectedProperty(Arr::get($config_array, 'legacy', FALSE), $e, '_legacy');
		$this->assertSame('test', $e->decode($e->encode('test')));

		unset(Encrypt::$instances[$config_group], $config[$config_group]);
	}

	/**
	 * Helper method to test for private/protected properties
	 *
	 * @param mixed $expect Expected value
	 * @param mixed $object object that holds the private/protected property
	 * @param string $name the name of the private/protected property
	 */
	protected function assertSameProtectedProperty($expect, $object, $name)
	{
		$property = new ReflectionProperty($object, $name);

		$this->assertSame($expect, $property->getValue($object));
	}

}
