<?php
class Regex {
  // ============================================================
  // CHARACTER CLASSES and SPECIFIC FORMATS
  // ============================================================
  const ALPHANUMERIC_ONLY = 'if ( (is_string($value)) && (preg_match("/^[a-zA-Z0-9]+$/",$value)) ) { return TRUE; } else { return FALSE; }';
  const ALPHANUMERIC_PLUS = 'if ( (is_string($value)) && (preg_match("/^[a-zA-Z0-9\s.\-]+$/",$value)) ) { return TRUE; } else { return FALSE; }';
  const EMAIL_ADDRESS = 'if ((is_string($value)) && (preg_match("/^([a-zA-Z0-9_\-\.]+)@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.)|(([a-zA-Z0-9\-]+\.)+))([a-zA-Z]{2,4}|[0-9]{1,3})(\]?)$/",$value))) { return TRUE; } else { return FALSE; }';
  const PHONE_NUMBER = 'if ((is_string($value)) && (preg_match("/^[2-9]\d{2}[-. ]\d{3}[-. ]\d{4}$/",$value))) { return TRUE; } else { return FALSE; }';
  const NUMBER_INTEGER = 'if ( (is_string($value)) && (preg_match("/^[0-9]*$/",$value)) ) { return TRUE; } else { return FALSE; }';
  // ============================================================
  // SITUATIONAL CONDITIONS
  // ============================================================
  const YES_OR_NO = 'if ( (is_string($value)) && (preg_match("/^yes|no$/i",$value)) ) { return TRUE; } else { return FALSE; }';
}
?>
