<?php
class CleanText {
  public static function makeUTF8($content) {
    $content = html_entity_decode($content,ENT_NOQUOTES,'UTF-8');
		$clean_str = preg_replace("/[^[:space:][:alnum:][:punct:]]/","",$content);
    $content = htmlentities($content,ENT_NOQUOTES,'UTF-8');
    if(!mb_check_encoding($clean_str, 'UTF-8')
      OR !($content === mb_convert_encoding(mb_convert_encoding($clean_str, 'UTF-32', 'UTF-8' ), 'UTF-8', 'UTF-32'))) {
        $content = mb_convert_encoding($content, 'UTF-8');
        if (mb_check_encoding($content, 'UTF-8')) {
          // cool things worked
        }
        else {
          $content = $clean_str;
        }
    }
    else {
      $content = $clean_str;
    }
    return $content;
  }

	public static function forHash($str) {
		$clean_str = preg_replace("/[^[:space:][:alnum:][:punct:]]/","",$str);
		// remove spaces and make all lower case
		return str_replace(' ','',strtolower($clean_str));
	}

	public static function forQuery($str) {
		$clean_str = preg_replace("/[^[:space:][:alnum:][:punct:]]/","",strip_tags($str));
		// remove spaces and make all lower case
		return $clean_str;
	}

	// create md5 hash checksum
	public static function hash($str) {
		$hash_str = null;
		$str = CleanText::forHash($str);
		// create the hash
		$hash_str = md5( trim($str) );
		return $hash_str;
	}

  public static function stringToFileName($str) {
    // remove all non-alphanumeric chars at begin & end of string
    $tmp = preg_replace('/^\W+|\W+$/', '', $str); 
    // compress internal whitespace and replace with _
    $tmp = preg_replace('/\s+/', '_', $tmp); 
    // remove all non-alphanumeric chars except _ and -
    return strtolower(preg_replace('/\W-/', '', $tmp)); 
  }

  /**
  * Convert a string to the file/URL safe "slug" form
  *
  * @param string $string the string to clean
  * @param bool $is_filename TRUE will allow additional filename characters
  * @return string
  */
  public static function sanitize($string = '', $is_filename = FALSE)
  {
    // Replace all weird characters with dashes
    $string = preg_replace('/[^\w\-'. ($is_filename ? '~_\.' : ''). ']+/u', '-', trim($string)); 
    // Only allow one dash separator at a time (and make string lowercase)
    $string = mb_strtolower(preg_replace('/--+/u', '-', $string), 'UTF-8');
    //return preg_replace('/-[^a-zA-Z0-9]+$/','', $string);
    return trim($string,'-');
  }

  public static function makeExcerpt($text,$limit = 30, $include_hellip = TRUE) {
    if (strlen($text) > $limit) { 
      $text = substr($text, 0, $limit); 
      $text = substr($text,0,strrpos($text," ")); 
      $etc = " ...";  
      if ($include_hellip) {
        $text = $text.' &hellip;'; 
      }
      else {
        $text = $text; 
      }
    }
    return $text; 
  }

}
?>
