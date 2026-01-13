<?php
// core/Session.php
session_start();

class Session
{
    public static function set($key, $val)
    {
        $_SESSION[$key] = $val;
    }

    public static function get($key)
    {
        return isset($_SESSION[$key]) ? $_SESSION[$key] : null;
    }

    public static function checkLogin()
    {
        if (!self::get('user_id')) {
            header("Location: login.php");
            exit();
        }
    }

    public static function destroy()
    {
        session_destroy();
        header("Location: login.php");
        exit();
    }
}
