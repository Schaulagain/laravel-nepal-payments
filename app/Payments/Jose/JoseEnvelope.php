<?php

namespace App\Payments\Jose;

use JsonException;
use OpenSSLAsymmetricKey;
use RuntimeException;

/**
 * Nested JOSE tokens as used by HBL's card gateway (2C2P PACO):
 * the JSON claims are signed (JWS, PS256) and the signed token is then
 * encrypted for the other side (JWE, RSA-OAEP + A128CBC-HS256).
 *
 * Built on PHP's OpenSSL extension. Pure-PHP JOSE libraries do the RSA maths
 * themselves and take minutes per token on servers without GMP.
 *
 * The same class works in both directions, which is what lets the tests
 * play the bank's side with their own key pairs.
 *
 * @see https://www.rfc-editor.org/rfc/rfc7518 (algorithms)
 * @see https://www.rfc-editor.org/rfc/rfc8017#section-9.1 (PSS encoding)
 */
class JoseEnvelope
{
    private const HASH_LENGTH = 32; // SHA-256, also the PS256 salt length

    private OpenSSLAsymmetricKey $signingKey;

    private OpenSSLAsymmetricKey $encryptionKey;

    private OpenSSLAsymmetricKey $decryptionKey;

    private OpenSSLAsymmetricKey $verificationKey;

    /**
     * @param  string  $signingKey  Our private key, used to sign what we send.
     * @param  string  $encryptionKey  Their public key, used to encrypt what we send.
     * @param  string  $decryptionKey  Our private key, used to decrypt what we receive.
     * @param  string  $verificationKey  Their public key, used to check what we receive.
     * @param  string|null  $keyId  `kid` header the receiver uses to pick its decryption key.
     */
    public function __construct(
        string $signingKey,
        string $encryptionKey,
        string $decryptionKey,
        string $verificationKey,
        private ?string $keyId = null,
    ) {
        $this->signingKey = self::privateKey($signingKey);
        $this->encryptionKey = self::publicKey($encryptionKey);
        $this->decryptionKey = self::privateKey($decryptionKey);
        $this->verificationKey = self::publicKey($verificationKey);
    }

    /**
     * Sign and encrypt a set of claims.
     *
     * @param  array<string, mixed>  $claims
     */
    public function seal(array $claims): string
    {
        return $this->encrypt($this->sign(json_encode($claims, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)));
    }

    /**
     * Decrypt a token, check its signature and return the claims.
     *
     * @return array<string, mixed>
     *
     * @throws InvalidTokenException
     */
    public function open(string $token): array
    {
        try {
            $claims = json_decode($this->verify($this->decrypt(trim($token))), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new InvalidTokenException('Token payload is not JSON.');
        }

        if (! is_array($claims)) {
            throw new InvalidTokenException('Token payload is not a JSON object.');
        }

        return $claims;
    }

    private function sign(string $payload): string
    {
        $input = self::encode(['alg' => 'PS256', 'typ' => 'JWT']).'.'.self::base64url($payload);

        $bits = openssl_pkey_get_details($this->signingKey)['bits'];
        $encoded = self::pssEncode(hash('sha256', $input, true), $bits - 1);

        if (! openssl_private_encrypt(self::leftPad($encoded, intdiv($bits + 7, 8)), $signature, $this->signingKey, OPENSSL_NO_PADDING)) {
            throw new RuntimeException('Signing failed: '.openssl_error_string());
        }

        return $input.'.'.self::base64url($signature);
    }

    /**
     * @throws InvalidTokenException
     */
    private function verify(string $jws): string
    {
        $parts = explode('.', $jws);

        if (count($parts) !== 3) {
            throw new InvalidTokenException('Signed token is malformed.');
        }

        [$header, $payload, $signature] = $parts;

        if ((self::decodeJson($header)['alg'] ?? null) !== 'PS256') {
            throw new InvalidTokenException('Unexpected JWS algorithm.');
        }

        $bits = openssl_pkey_get_details($this->verificationKey)['bits'];
        $signature = self::base64urlDecode($signature);

        if (strlen($signature) !== intdiv($bits + 7, 8)
            || ! openssl_public_decrypt($signature, $decrypted, $this->verificationKey, OPENSSL_NO_PADDING)
            || ! self::pssVerify(hash('sha256', "{$header}.{$payload}", true), $decrypted, $bits - 1)) {
            throw new InvalidTokenException('Token signature is invalid.');
        }

        return self::base64urlDecode($payload);
    }

    private function encrypt(string $plaintext): string
    {
        $header = self::encode(array_filter([
            'alg' => 'RSA-OAEP',
            'enc' => 'A128CBC-HS256',
            'kid' => $this->keyId,
            'typ' => 'JWT',
        ]));

        // A128CBC-HS256 uses a 32-byte key: the first half for the HMAC, the second for AES.
        $cek = random_bytes(32);
        $iv = random_bytes(16);

        if (! openssl_public_encrypt($cek, $encryptedKey, $this->encryptionKey, OPENSSL_PKCS1_OAEP_PADDING)) {
            throw new RuntimeException('Key encryption failed: '.openssl_error_string());
        }

        $ciphertext = openssl_encrypt($plaintext, 'aes-128-cbc', substr($cek, 16), OPENSSL_RAW_DATA, $iv);

        return implode('.', [
            $header,
            self::base64url($encryptedKey),
            self::base64url($iv),
            self::base64url($ciphertext),
            self::base64url(self::tag(substr($cek, 0, 16), $header, $iv, $ciphertext)),
        ]);
    }

    /**
     * @throws InvalidTokenException
     */
    private function decrypt(string $jwe): string
    {
        $parts = explode('.', $jwe);

        if (count($parts) !== 5) {
            throw new InvalidTokenException('Encrypted token is malformed.');
        }

        [$header, $encryptedKey, $iv, $ciphertext, $tag] = $parts;
        $decoded = self::decodeJson($header);

        if (($decoded['alg'] ?? null) !== 'RSA-OAEP' || ($decoded['enc'] ?? null) !== 'A128CBC-HS256') {
            throw new InvalidTokenException('Unexpected JWE algorithms.');
        }

        $iv = self::base64urlDecode($iv);
        $ciphertext = self::base64urlDecode($ciphertext);

        if (! openssl_private_decrypt(self::base64urlDecode($encryptedKey), $cek, $this->decryptionKey, OPENSSL_PKCS1_OAEP_PADDING)
            || strlen($cek) !== 32
            || ! hash_equals(self::tag(substr($cek, 0, 16), $header, $iv, $ciphertext), self::base64urlDecode($tag))) {
            throw new InvalidTokenException('Token could not be decrypted.');
        }

        $plaintext = openssl_decrypt($ciphertext, 'aes-128-cbc', substr($cek, 16), OPENSSL_RAW_DATA, $iv);

        if ($plaintext === false) {
            throw new InvalidTokenException('Token could not be decrypted.');
        }

        return $plaintext;
    }

    /**
     * Authentication tag for A128CBC-HS256 (RFC 7518, section 5.2.2.1).
     */
    private static function tag(string $macKey, string $aad, string $iv, string $ciphertext): string
    {
        $aadBits = pack('J', strlen($aad) * 8);

        return substr(hash_hmac('sha256', $aad.$iv.$ciphertext.$aadBits, $macKey, true), 0, 16);
    }

    /**
     * EMSA-PSS-ENCODE with SHA-256, MGF1-SHA-256 and a 32-byte salt.
     */
    private static function pssEncode(string $hash, int $emBits): string
    {
        $emLength = intdiv($emBits + 7, 8);
        $salt = random_bytes(self::HASH_LENGTH);
        $h = hash('sha256', str_repeat("\0", 8).$hash.$salt, true);

        $db = str_repeat("\0", $emLength - 2 * self::HASH_LENGTH - 2)."\x01".$salt;
        $maskedDb = $db ^ self::mgf1($h, $emLength - self::HASH_LENGTH - 1);
        $maskedDb[0] = chr(ord($maskedDb[0]) & (0xFF >> (8 * $emLength - $emBits)));

        return $maskedDb.$h."\xbc";
    }

    /**
     * EMSA-PSS-VERIFY. $decrypted is the raw RSA result, left-padded to the key size.
     */
    private static function pssVerify(string $hash, string $decrypted, int $emBits): bool
    {
        $emLength = intdiv($emBits + 7, 8);
        $extra = strlen($decrypted) - $emLength;

        if ($extra < 0 || ltrim(substr($decrypted, 0, $extra), "\0") !== '') {
            return false;
        }

        $em = substr($decrypted, $extra);
        $dbLength = $emLength - self::HASH_LENGTH - 1;
        $topBitsMask = 0xFF >> (8 * $emLength - $emBits);

        if ($emLength < 2 * self::HASH_LENGTH + 2 || $em[$emLength - 1] !== "\xbc" || (ord($em[0]) & ~$topBitsMask & 0xFF) !== 0) {
            return false;
        }

        $h = substr($em, $dbLength, self::HASH_LENGTH);
        $db = substr($em, 0, $dbLength) ^ self::mgf1($h, $dbLength);
        $db[0] = chr(ord($db[0]) & $topBitsMask);

        $padding = $dbLength - self::HASH_LENGTH - 1;

        if (substr($db, 0, $padding) !== str_repeat("\0", $padding) || $db[$padding] !== "\x01") {
            return false;
        }

        $salt = substr($db, -self::HASH_LENGTH);

        return hash_equals($h, hash('sha256', str_repeat("\0", 8).$hash.$salt, true));
    }

    private static function mgf1(string $seed, int $length): string
    {
        $mask = '';

        for ($counter = 0; strlen($mask) < $length; $counter++) {
            $mask .= hash('sha256', $seed.pack('N', $counter), true);
        }

        return substr($mask, 0, $length);
    }

    private static function leftPad(string $bytes, int $length): string
    {
        return str_pad($bytes, $length, "\0", STR_PAD_LEFT);
    }

    /**
     * @param  array<string, mixed>  $value
     */
    private static function encode(array $value): string
    {
        return self::base64url(json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }

    /**
     * @return array<string, mixed>
     *
     * @throws InvalidTokenException
     */
    private static function decodeJson(string $segment): array
    {
        $decoded = json_decode(self::base64urlDecode($segment), true);

        if (! is_array($decoded)) {
            throw new InvalidTokenException('Token header is not valid JSON.');
        }

        return $decoded;
    }

    private static function base64url(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    /**
     * @throws InvalidTokenException
     */
    private static function base64urlDecode(string $segment): string
    {
        $decoded = base64_decode(strtr($segment, '-_', '+/'), true);

        if ($decoded === false) {
            throw new InvalidTokenException('Token contains invalid base64.');
        }

        return $decoded;
    }

    /**
     * Banks often hand out keys as bare base64 without PEM headers, in
     * PKCS#1 or PKCS#8 form. OpenSSL works out which one it is.
     */
    private static function privateKey(string $key): OpenSSLAsymmetricKey
    {
        foreach (self::candidates($key, ['PRIVATE KEY', 'RSA PRIVATE KEY']) as $candidate) {
            if ($resource = openssl_pkey_get_private($candidate)) {
                return $resource;
            }
        }

        throw new RuntimeException('Could not read RSA private key.');
    }

    private static function publicKey(string $key): OpenSSLAsymmetricKey
    {
        foreach (self::candidates($key, ['PUBLIC KEY', 'RSA PUBLIC KEY']) as $candidate) {
            if ($resource = openssl_pkey_get_public($candidate)) {
                return $resource;
            }
        }

        throw new RuntimeException('Could not read RSA public key.');
    }

    /**
     * @param  list<string>  $labels
     * @return list<string>
     */
    private static function candidates(string $key, array $labels): array
    {
        if (str_contains($key, '-----BEGIN')) {
            return [$key];
        }

        $body = chunk_split(preg_replace('/\s+/', '', $key), 64, "\n");

        return array_map(fn (string $label): string => "-----BEGIN {$label}-----\n{$body}-----END {$label}-----\n", $labels);
    }
}
