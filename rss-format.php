<?
//
// RSSiCal Version 0.8.3 by Noel David Jackson (noel@noeljackson.com)
// RSS Parser by Edward Swindelles (ed@readinged.com)
//

$url = preg_replace('/http:\/\//', '', $url);
$today = date(Ymd);
include("parseRSS.php");

function fix($data) {

$patterns = array(
			'/<br \/>/',
			'/<\/p>/',
			'/<p.*?>/',
			
			'/(.*?)<a href="(.*?http:\/\/.+?)".*?>(.*?)<\/a>(.*?)/',			
			
			'/<code.*?>(.*?)<\/code>/',
			'/<img.*?src=".*?".*?[\/]*>/',		
			
			'/<blockquote.*?cite="(http:\/\/.+?)".*?>(.*?)<\/blockquote>/',	
			'/<cite.*?>(.*?)<\/cite>/',
			
			'/<[ou]l*?>(.+?)<\/[ou]l>/',
			'/<li*?>(.+?)<\/li>/',

			
			'/<span.*?>(.*?)<\/span>/',
			'/<strong.*?>(.*?)<\/strong>/',
			'/<b.*?>(.*?)<\/b>/',
			'/<em.*?>(.*?)<\/em>/',
			'/<i.*?>(.*?)<\/i>/',
			'/,/',
			
			'/&lt;/',
			'/&gt;/',
			'/&amp;/',
			'/&#821[67];/',
			'/&#822[01];/'
			);
			
$replace = array(
//br and p	
			"\\n",
			"\\n\\n",
			"",
//a element			
			"\\1\\3 [link: \\2]\\4",
//code
			"\\1",
//img
			"[img]\\n",
//cite=,cite	
			"\\n\\2[cite: \\1]\\n\\n",			
			"[cite]\\1",
//ul/ol, li
			"\\n\\1",
			"* \\1\\n",

			"\\1",
			"\\1",
			"\\1",
			"\\1",
			"\\1",
			"\\,",
			
			"<",
			">",
			"&",
			"'",
			"\""
			);
			
		
$data = preg_replace($patterns, $replace, $data);

return $data;
}
if ($rssData = parseRSS ( "http://$url"))
{
print <<< END
BEGIN:VCALENDAR
CALSCALE:GREGORIAN
X-WR-TIMEZONE;VALUE=TEXT:America/Detroit
METHOD:PUBLISH
PRODID:-//Apple Computer\, Inc//iCal 1.0//EN
X-WR-CALNAME;VALUE=TEXT:
END
. $rssData["channel"]["title"] . "\n" .<<< END
VERSION:2.0
BEGIN:VEVENT
SUMMARY:
END
. ereg_replace(" ", "\\ ", $rssData["channel"]["title"]) . "\n" .<<< END
DESCRIPTION: 
END;
echo $rssData["channel"]["title"] . " - " .$rssData["channel"]["description"] . "\\n" . $rssData["channel"]["link"] . "\\n\\n\n";

   for ( $i = 0; isset ( $rssData[$i] ); $i++ )
   {
      echo " -[" . fix($rssData[$i][ "title" ]) . "]-\\n";
      echo $rssData[$i][ "link" ] . "\\n\\n\n";
      echo " " . fix($rssData[$i][ "description" ]) . "\\n\n";
   }
print <<< END
DTSTART;VALUE=DATE:$today
DTEND;VALUE=DATE:$today
END:VEVENT
END:VCALENDAR
END;
}
else
   echo 'Unable to parse RSS feed.';
   
   ?>