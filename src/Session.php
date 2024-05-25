<?php

namespace PhpProxyHunter;

use DateTime;
use DateTimeZone;
use Exception;

class Session
{
    private $session_prefix_name = "PHP_PROXY_HUNTER";

    /**
     * @throws Exception
     */
    public function __construct(int $timeout, $session_folder = null)
    {
        if (!empty($session_folder) && !file_exists($session_folder)) {
            mkdir($session_folder, 755, true);
        }
        if (!$this->is_session_started()) {
            // $this->handle($timeout, $session_folder);

            $name = md5($this->session_prefix_name . $timeout . Server::getRequestIP() . Server::useragent());
            if (empty(trim($session_folder))) {
                $session_folder = __DIR__ . '/../tmp/sessions';
                if (!file_exists($session_folder)) {
                    if (!mkdir($session_folder, 0755, true)) {
                        throw new Exception('Unable to create session folder.');
                    }
                }
            }

            // set sessions folder permission
            chmod($session_folder, 0777);
            session_save_path($session_folder);
            session_set_cookie_params($timeout);
            ini_set('session.gc_maxlifetime', $timeout);
            ini_set('session.cookie_lifetime', $timeout);
            ini_set('session.gc_probability', 100);
            ini_set('session.gc_divisor', 100);
            session_id($name);
            session_start();
            $path = ini_get('session.save_path');
            if (!file_exists($path . '/.htaccess')) {
                file_put_contents($path . '/.htaccess', 'deny from all');
            }
            if (!isset($_SESSION['session_started'])) {
                $_SESSION['session_started'] = $this->now();
                $_SESSION['session_timeout'] = $timeout;
                $_SESSION['cookie_timeout'] = $timeout;
                $_SESSION['id'] = session_id();
            }
        }
    }

    public function is_session_started(): bool
    {
        return PHP_SESSION_ACTIVE == session_status();
    }

    public static function dump(): array
    {
        return [
            'sessions' => [
                'active' => PHP_SESSION_NONE == session_status(),
                'id' => session_id(),
                'folder' => realpath(ini_get('session.save_path')),
                'session.gc_maxlifetime' => ini_get('session.gc_maxlifetime'),
                'session.cookie_lifetime' => ini_get('session.cookie_lifetime'),
                'session.gc_probability' => ini_get('session.gc_probability'),
                'session.gc_divisor' => ini_get('session.gc_divisor'),
                'session.hash_function' => ini_get('session.hash_function'),
            ],
        ];
    }

    /**
     * @throws Exception
     */
    public function now(): DateTime
    {
        return new DateTime(null, new DateTimeZone('Asia/Jakarta'));
    }
}
