<?php

namespace App\Auth;

use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Auth\Authenticatable as UserContract;

/**
 * The source project stored admin passwords as unsalted SHA-256
 * (config/function.php: hash('sha256', $password)), which is rainbow-table
 * vulnerable and makes identical passwords produce identical digests.
 *
 * This provider keeps existing admins able to log in while transparently
 * upgrading each account to bcrypt on first successful login. Once every
 * account has a bcrypt hash, this provider can be dropped for the default one.
 */
class LegacyHashUserProvider extends EloquentUserProvider
{
    public function validateCredentials(UserContract $user, array $credentials): bool
    {
        $plain = $credentials['password'] ?? '';
        $stored = (string) $user->getAuthPassword();

        if ($plain === '' || $stored === '') {
            return false;
        }

        if ($this->isLegacySha256($stored)) {
            if (! hash_equals($stored, hash('sha256', $plain))) {
                return false;
            }

            $this->upgradeToBcrypt($user, $plain);

            return true;
        }

        return parent::validateCredentials($user, $credentials);
    }

    /** Legacy hashes are 64 lowercase hex characters; bcrypt starts with $2y$. */
    public static function isLegacySha256(string $hash): bool
    {
        return (bool) preg_match('/^[a-f0-9]{64}$/', $hash);
    }

    protected function upgradeToBcrypt(UserContract $user, string $plain): void
    {
        // `password` is cast to 'hashed', so assigning the plain value bcrypts it.
        $user->forceFill(['password' => $plain])->save();
    }
}
