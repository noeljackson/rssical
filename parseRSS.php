<?php

/* Copyright 2002-2003 Edward Swindelles (ed@readinged.com)
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

/* Default settings, you may change them at your whim.  See README. */
$rss_cache_path         = '';
$rss_default_cache_time = 180;
$rss_debug_mode         = true;


/* Private variables, do not change. */
$rss_contents           = array();
$rss_cache_age          = 0;
$rss_tag                = '';
$rss_isItem             = false;
$rss_isChannel          = false;
$rss_isImage            = false;
$rss_isTextInput        = false;
$rss_index              = 0;

function stream_last_modified($url)
{
	if (function_exists('version_compare') && version_compare(phpversion(), '4.3.0') > 0)
	{
		if (!($fp = @fopen($url, 'r')))
			return NULL;

		$meta = stream_get_meta_data($fp);
		for ($j = 0; isset($meta['wrapper_data'][$j]); $j++)
		{
			if (strstr(strtolower($meta['wrapper_data'][$j]), 'last-modified'))
			{
				$modtime = substr($meta['wrapper_data'][$j], 15);
				break;
			}
		}
		fclose($fp);
	}
	else
	{
		$parts = parse_url($url);
		$host  = $parts['host'];
		$path  = $parts['path'];

		if (!($fp = @fsockopen($host, 80)))
			return NULL;

		$req = "HEAD $path HTTP/1.0\r\nUser-Agent: PHP/".phpversion()."\r\nHost: $host:80\r\nAccept: */*\r\n\r\n";
		fputs($fp, $req);

		while (!feof($fp))
		{
			$str = fgets($fp, 4096);
			if (strstr(strtolower($str), 'last-modified'))
			{
				$modtime = substr($str, 15);
				break;
			}
		}
		fclose($fp);
	}
	return isset($modtime) ? strtotime($modtime) : time();
}

function parseRSS($url, $cache_file=NULL, $cache_time=NULL)
{
	global   $rss_contents, $rss_default_cache_time, $rss_isTextInput,
		 $rss_cache_path, $rss_cache_age, $rss_tag, $rss_isImage,
		 $rss_isItem, $rss_isChannel, $rss_index, $rss_debug_mode;

	$rss_error = '<br /><strong>Error on line %s of '.__FILE__.'</strong>: %s<br />';

	if (!function_exists('xml_parser_create'))
	{
		if ($rss_debug_mode)
			printf($rss_error, (__LINE__-3), '<a href="http://www.php.net/manual/en/ref.xml.php">PHP\'s XML Extension</a> is not loaded or available.');

		return false;
	}

	$rss_contents = array();

	if (!is_null($cache_file))
	{
		if (!isset($rss_cache_path) || !strlen($rss_cache_path))
			$rss_cache_path = dirname(__FILE__);

		$cache_file = str_replace('//', '/', $rss_cache_path.'/'.$cache_file);

		if (is_null($cache_time))
			$cache_time = $rss_default_cache_time;

		$rss_cache_age = file_exists($cache_file) ? ceil((time() - filemtime($cache_file)) / 60) : 0;
		$remotemodtime = stream_last_modified($url);
		if (is_null($remotemodtime))
		{
			if ($rss_debug_mode)
				printf($rss_error, (__LINE__-4), 'Could not connect to remote RSS file ('.$url.').');

			return false;
		}
	}

	if (is_null($cache_file) ||
	   (!is_null($cache_file) && !file_exists($cache_file)) ||
	   (!is_null($cache_file) && file_exists($cache_file) && $rss_cache_age > $cache_time && $remotemodtime > ((time()) - ($rss_cache_age * 60))))
	{
		$rss_tag     = '';
		$rss_isItem  = false;
		$rss_isChannel = false;
		$rss_index   = 0;

		$saxparser = @xml_parser_create();
		if (!is_resource($saxparser))
		{
			if ($rss_debug_mode)
				printf($rss_error, (__LINE__-4), 'Could not create an instance of <a href="http://www.php.net/manual/en/ref.xml.php">PHP\'s XML parser</a>.');

			return false;
		}

		xml_parser_set_option($saxparser, XML_OPTION_CASE_FOLDING, false);
		xml_set_element_handler($saxparser, 'sax_start', 'sax_end');
		xml_set_character_data_handler($saxparser, 'sax_data');

		if (!($fp = @fopen($url, 'r')))
		{
			if ($rss_debug_mode)
				printf($rss_error, (__LINE__-3), 'Could not connect to remote RSS file ('.$url.').');

			return false;
		}

		while ($data = fread($fp, 4096))
		{
			$parsedOkay = xml_parse($saxparser, $data, feof($fp));

			if (!$parsedOkay && xml_get_error_code($saxparser) != XML_ERROR_NONE)
			{
				if ($rss_debug_mode)
					printf($rss_error, (__LINE__-3), 'File has an XML error (<em>'.xml_error_string(xml_get_error_code($saxparser)).'</em> at line <em>'.xml_get_current_line_number($saxparser).'</em>).');

				return false;
			}
		}

		xml_parser_free($saxparser);
		fclose($fp);

		if (!is_null($cache_file))
		{
			if (!($cache = @fopen($cache_file, 'w')))
			{
				if ($rss_debug_mode)
					printf($rss_error, (__LINE__-3), 'Could not right to cache file (<em>'.$cache_file.'</em>).  The path may be invalid or you may not have write permissions.');

				return false;
			}

			fwrite($cache, serialize($rss_contents));
			fclose($cache);
		}
	}
	else
	{
		if (!($fp = @fopen($cache_file, 'r')))
		{
			if ($rss_debug_mode)
				printf($rss_error, (__LINE__-3), 'Could not read contents of cache file (<em>'.$cache_file.'</em>).');

			return false;
		}

		$rss_contents = unserialize(fread($fp, filesize($cache_file)));
		fclose($fp);
	}

	return $rss_contents;
}

function sax_start($parser, $name, $attribs)
{
	global $rss_tag, $rss_isItem, $rss_isChannel, $rss_isImage, $rss_index, $rss_isTextInput;

	$rss_tag = $name = strtolower($name);

	if ($name == 'channel')
	{
		$rss_isChannel = true;
		$rss_isImage = false;
		$rss_isItem = false;
	}
	elseif ($name == 'image')
	{
		$rss_isChannel = false;
		$rss_isImage = true;
		$rss_isItem = false;
	}
	elseif ($name == 'item')
	{
		$rss_index++;
		$rss_isChannel = false;
		$rss_isImage = false;
		$rss_isItem = true;
	}
	elseif ($name == 'textinput')
	{
		$rss_isChannel = false;
		$rss_isImage = false;
		$rss_isItem = false;
		$rss_isTextInput = true;
	}
}

function sax_end($parser, $name){}

function sax_data($parser, $data)
{
	global $rss_tag, $rss_isItem, $rss_isChannel, $rss_contents, $rss_isTextInput, $rss_isImage, $rss_index;
	if ($data != "\n")
	{
		if ($rss_isChannel && !$rss_isItem && strlen($data))
			(!isset($rss_contents['channel'][$rss_tag]) || !strlen($rss_contents['channel'][$rss_tag])) ?
				$rss_contents['channel'][$rss_tag] = $data :
				$rss_contents['channel'][$rss_tag].= $data ;
		elseif ($rss_isItem && strlen($data))
			(!isset($rss_contents[$rss_index-1][$rss_tag]) || !strlen($rss_contents[$rss_index-1][$rss_tag])) ? 
				$rss_contents[$rss_index-1][$rss_tag] = $data :
				$rss_contents[$rss_index-1][$rss_tag].= $data ;
		elseif ($rss_isImage && strlen($data))
			(!isset($rss_contents['image'][$rss_tag]) || !strlen($rss_contents['image'][$rss_tag])) ?
				$rss_contents['image'][$rss_tag] = $data :
				$rss_contents['image'][$rss_tag].= $data ;
		elseif ($rss_isTextInput && strlen($data))
			(!isset($rss_contents['textinput'][$rss_tag]) || !strlen($rss_contents['textinput'][$rss_tag])) ?
				$rss_contents['textinput'][$rss_tag] = $data :
				$rss_contents['textinput'][$rss_tag].= $data ;
	}
}

?>