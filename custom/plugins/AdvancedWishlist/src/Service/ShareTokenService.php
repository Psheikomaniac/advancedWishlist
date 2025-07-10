<?php declare(strict_types=1);

namespace AdvancedWishlist\Service;

use Shopware\Core\Framework\Uuid\Uuid;

class ShareTokenService
{
    private string $encryptionKey;

    public function __construct(string $encryptionKey)
    {
        $this->encryptionKey = $encryptionKey;
    }

    public function encrypt(string $data): string
    {
        $cipher = 'AES-256-CBC';
        $ivlen = openssl_cipher_iv_length($cipher);
        $iv = openssl_random_pseudo_bytes($ivlen);
        $encrypted = openssl_encrypt($data, $cipher, $this->encryptionKey, 0, $iv);

        return base64_encode($iv . ':' . $encrypted);
    }

    public function decrypt(string $encryptedData): string
    {
        $cipher = 'AES-256-CBC';
        $parts = explode(':', base64_decode($encryptedData), 2);
        $iv = $parts[0];
        $encrypted = $parts[1];

        return openssl_decrypt($encrypted, $cipher, $this->encryptionKey, 0, $iv);
    }

    public function generateToken(): string
    {
        return Uuid::randomHex();
    }
}
