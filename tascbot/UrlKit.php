<?php
class UrlKit {
	public static function getDomain($url,$with_scheme=TRUE)
	{
		if(filter_var($url, FILTER_VALIDATE_URL, FILTER_FLAG_HOST_REQUIRED) === FALSE)
		{
			return false;
		}
		/*** get the url parts ***/
		$parts = parse_url($url);
		/*** return the host domain ***/
    if ($with_scheme) {
		  return $parts['scheme'].'://'.$parts['host'];
    }
    else {
		  return $parts['host'];
    }
	}
}
?>
