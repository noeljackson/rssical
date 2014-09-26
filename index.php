<?php
//
// RSSiCal Version 0.8.3 by Noel David Jackson (noel@noeljackson.com)
// RSS Parser by Edward Swindelles (ed@readinged.com)
//
if($url == "http://" | $url == "") {
print <<< END
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
        "http://www.w3.org/TR/2000/REC-xhtml1-20000126/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
	<title>RSSiCal</title>
</head>
<body>
<h1><a href="http://noeljackson.com/code/rssical/">RSSiCal 0.8.0</a></h1>

<form action="http://
END
. $_SERVER['HTTP_HOST']. $_SERVER['PHP_SELF'] . <<< END
" method="get">
<p><input type="text" name="url" value="URL to RSS Document" size="35" />
<input type="submit" name="submit" value="Go!" /></p>
</form>

</body>
</html>
END;
} else { include("./rss-format.php"); }?>
