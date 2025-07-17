<?php

declare(strict_types=1);

namespace AdvancedWishlist\Service;

use Defuse\Crypto\Crypto;
use Defuse\Crypto\Exception\EnvironmentIsBrokenException;
use Defuse\Crypto\Exception\WrongKeyOrModifiedCiphertextException;
use Defuse\Crypto\Key;

class EncryptionService
{
    private Key $encryptionKey;

    /**
     * @param string $encryptionKeyString The encryption key as a string
     *
     * @throws EnvironmentIsBrokenException If the cryptographic environment is broken
     */
    public function __construct(string $encryptionKeyString)
    {
        $this->encryptionKey = Key::loadFromAsciiSafeString($encryptionKeyString);
    }

    /**
     * Encrypts data using authenticated encryption.
     *
     * @param string $data The data to encrypt
     *
     * @return string The encrypted data
     *
     * @throws EnvironmentIsBrokenException If the cryptographic environment is broken
     */
    public function encrypt(string $data): string
    {
        return Crypto::encrypt($data, $this->encryptionKey);
    }

    /**
     * Decrypts data that was encrypted using the encrypt() method.
     *
     * @param string $encryptedData The encrypted data
     *
     * @return string The decrypted data
     *
     * @throws EnvironmentIsBrokenException          If the cryptographic environment is broken
     * @throws WrongKeyOrModifiedCiphertextException If the ciphertext has been modified or the wrong key was used
     */
    public function decrypt(string $encryptedData): string
    {
        return Crypto::decrypt($encryptedData, $this->encryptionKey);
    }

    /**
     * Generates a new encryption key.
     *
     * @return string The new encryption key as a string
     *
     * @throws EnvironmentIsBrokenException If the cryptographic environment is broken
     */
    public static function generateEncryptionKey(): string
    {
        return Key::createNewRandomKey()->saveToAsciiSafeString();
    }

    /**
     * Generates a random token for sharing.
     *
     * @return string The generated token
     */
    public function generateToken(): string
    {
        return \Shopware\Core\Framework\Uuid\Uuid::randomHex();
    }
}
