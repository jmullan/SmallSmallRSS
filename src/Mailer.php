<?php
namespace SmallSmallRSS;

/**
 * @class Mailer
 * @brief A SmallSmallRSS extension to the PHPMailer class
 * Configures default values through the __construct() function
 * @author Derek Murawsky
 * @version .1 (alpha)
 */
class Mailer extends \PHPMailer
{
    //define all items that we want to override with defaults in PHPMailer
    public $CharSet = 'UTF-8';
    public $PluginDir = 'lib/phpmailer/';
    public $ContentType = 'text/html'; //default email type is HTML

    public function __construct()
    {
        $this->From = \SmallSmallRSS\Config::get('SMTP_FROM_ADDRESS');
        $this->FromName = \SmallSmallRSS\Config::get('SMTP_FROM_NAME');

        if (\SmallSmallRSS\Config::get('SMTP_SERVER')) {
            $pair = explode(':', \SmallSmallRSS\Config::get('SMTP_SERVER'), 2);
            $this->Mailer = 'smtp';
            $this->Host = $pair[0];
            $this->Port = $pair[1];

            if (!$this->Port) {
                $this->Port = 25;
            }
        } else {
            $this->Host = '';
            $this->Port = '';
        }


        //if SMTP_LOGIN is specified, set credentials and enable auth
        if (\SmallSmallRSS\Config::get('SMTP_LOGIN')) {
            $this->SMTPAuth = true;
            $this->Username = \SmallSmallRSS\Config::get('SMTP_LOGIN');
            $this->Password = \SmallSmallRSS\Config::get('SMTP_PASSWORD');
        }
        if (\SmallSmallRSS\Config::get('SMTP_SECURE')) {
            $this->SMTPSecure = \SmallSmallRSS\Config::get('SMTP_SECURE');
        }
    }

    /**
     * A simple mail function to send email using the defaults
     * This will send an HTML email using the configured defaults
     * @param $toAddress A string with the recipients email address
     * @param $toName A string with the recipients name
     * @param $subject A string with the emails subject
     * @param $body A string containing the body of the email
     */
    public function quickMail($toAddress, $toName, $subject, $body, $altbody = '')
    {
        $this->addAddress($toAddress, $toName);
        $this->Subject = $subject;
        $this->Body = $body;
        $this->IsHTML($altbody != '');
        $rc = $this->send();
        return $rc;
    }
}
