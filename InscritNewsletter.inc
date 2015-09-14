<?php

class InscritNewsletter extends MyObject {
  protected static $db_table_name = 'newsletter_registration';
  protected static $db_table_identifier = 'snr';
  protected static $default_data = array('status'=>1);
  public $snr;

  public $email;
  public $user;
  public $status;
  public $created;
  public $updated;
  public $data;


  public static function checkIfInscrit($email) {
    $res = self::_load(array(array('email',$email)));
    return isset($res->snr) && $res->snr > 0;
  }
}