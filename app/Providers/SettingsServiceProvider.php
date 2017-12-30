<?php

namespace Pterodactyl\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Pterodactyl\Contracts\Repository\SettingsRepositoryInterface;

class SettingsServiceProvider extends ServiceProvider
{
    /**
     * An array of configuration keys to override with database values
     * if they exist.
     *
     * @var array
     */
    protected $keys = [
        'app:name',
        'app:locale',
        'recaptcha:enabled',
        'recaptcha:secret_key',
        'recaptcha:website_key',
        'pterodactyl:guzzle:timeout',
        'pterodactyl:guzzle:connect_timeout',
        'pterodactyl:console:count',
        'pterodactyl:console:frequency',
        'pterodactyl:auth:2fa_required',
    ];

    /**
     * Keys specific to the mail driver that are only grabbed from the database
     * when using the SMTP driver.
     *
     * @var array
     */
    protected $emailKeys = [
        'mail:host',
        'mail:port',
        'mail:from:address',
        'mail:from:name',
        'mail:encryption',
        'mail:username',
        'mail:password',
    ];

    /**
     * Keys that are encrypted and should be decrypted when set in the
     * configuration array.
     *
     * @var array
     */
    protected static $encrypted = [
        'mail:password',
    ];

    /**
     * Boot the service provider.
     *
     * @param \Illuminate\Contracts\Config\Repository                       $config
     * @param \Illuminate\Contracts\Encryption\Encrypter                    $encrypter
     * @param \Pterodactyl\Contracts\Repository\SettingsRepositoryInterface $settings
     */
    public function boot(ConfigRepository $config, Encrypter $encrypter, SettingsRepositoryInterface $settings)
    {
        // Only set the email driver settings from the database if we
        // are configured using SMTP as the driver.
        if ($config->get('mail.driver') === 'smtp') {
            $this->keys = array_merge($this->keys, $this->emailKeys);
        }

        $values = $settings->all()->mapWithKeys(function ($setting) {
            return [$setting->key => $setting->value];
        })->toArray();

        foreach ($this->keys as $key) {
            $value = array_get($values, 'settings::' . $key, $config->get(str_replace(':', '.', $key)));
            if (in_array($key, self::$encrypted)) {
                try {
                    $value = $encrypter->decrypt($value);
                } catch (DecryptException $exception) {
                }
            }

            switch (strtolower($value)) {
                case 'true':
                case '(true)':
                    $value = true;
                    break;
                case 'false':
                case '(false)':
                    $value = false;
                    break;
                case 'empty':
                case '(empty)':
                    $value = '';
                    break;
                case 'null':
                case '(null)':
                    $value = null;
            }

            $config->set(str_replace(':', '.', $key), $value);
        }
    }

    /**
     * @return array
     */
    public static function getEncryptedKeys(): array
    {
        return self::$encrypted;
    }
}
