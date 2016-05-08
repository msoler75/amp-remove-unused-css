<?php

/***************************************************************
* CSS REMOVE UNUSED RULES
* Only in AMP pages
* Author: Marcel Soler
* e-mail: msoler75@yahoo.es
* Version: 1.0 (2016)
* Project:  https://github.com/msoler75/amp-remove-unused-css
**************************************************************/

//HOW TO USE:
//call the main function: 
//amp_remove_inline_unused_css($fullhtml)

//TO-DO : process @media


global $include_selectors;
global $rules_to_remove;
global $htmlbodyamp;

$include_selectors = array('div','span','a','p','body','ul','li','nav');
$rules_to_remove = array();



function amp_remove_css_callback($matches)
{
	global $rules_to_remove;
	$css = $matches[2];
	preg_match_all("#@[a-z][^{]+{([^{}]+{[^}]*})*}|([\.\#a-z][^{]*{[^}]*})#im", $css, $rules);
	
	foreach($rules[0] as $x=>$rule)
	{			
		$media = strpos($rule, "@");
		if($media!==FALSE)
		{
			//TO-DO
		}
		else
		{
			$pos = strpos($rule, "{");		
			$leftrule = substr($rule, 0, $pos);
			$rightrule = substr($rule, $pos);
			$rtmp = preg_split("#[,]#m", $leftrule, -1, PREG_SPLIT_NO_EMPTY);
			$found = false;
			foreach($rtmp as $i=>$trule)
			{
				$trule = trim($trule);
				$r = preg_split("/(?:[\s>]*)([#\.:]?[a-z][a-z0-9\-_]*)([\[\(].*[\]\)])?/i", $trule, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
				foreach($r as $id)
				{
					if(isset($rules_to_remove[$id]))
					{
						$found = true;
						unset($rtmp[$i]);
					}
				}				
			}
			if(!$rtmp||!count($rtmp))
			{
				unset($rules[0][$x]);
			}
			else
			{
				if($found)
				{
					$rules[0][$x] = implode(",", $rtmp). $rightrule;
				}
			}
		}
	}
	
	//If you dont want new lines, just replace next "\n" to ""
	return $matches[1].implode("\n",$rules[0]).$matches[3];
}

function amp_css_find_rules_callback($matches)
{
	global $include_selectors;
	global $rules_to_remove;		
	global $htmlbodyamp;
	$css = $matches[2];
	$css = preg_replace("/\!important/", "", $css); //remove !important  because is not allowed in AMP
	$css = amp_minify_css( $css ); //we need minize here to get correct format
	
	preg_match_all("#@[^{]+{([^{}]+{[^}]*})*}|([\.\#a-z][^{]*{[^}]*})#m", $css, $rules);
	$selectors = array(
		'cls'=> array('prefix'=>".", 'regex_css'=>"/\.([a-z][a-z0-9\-_]*)[^a-z0-9\-_]/Usim", 'find_in_html'=>"/<[^>]*\s+class=['\"]\s*(?:([^'\"]+)\b\s*)*['\"]/ism"),
		'id'=> array('prefix'=>"#", 'regex_css'=>"/#([a-z][a-z0-9\-_]*)[^a-z0-9\-_]/im", 'find_in_html'=>"/<[^>]*\s+id=['\"]([^'\"]+)['\"]/im"),
		'tags'=> array('prefix'=>"", 'regex_css'=>"/^([^\.#@:][a-z0-9\-_]*)\b/im", 'find_in_html'=>"/<([a-z][^\s><]*)\b/i")
	);
	
		
	$items = array();	
	foreach($selectors as $selector=>$params)
	{		
		preg_match_all($params['find_in_html'], $htmlbodyamp, $ids);
		if($selector=='cls')
		{
			$items[$selector] = array();
			foreach($ids[1] as $classes)
			{
				$tmp = preg_split("/\s/", $classes, -1, PREG_SPLIT_NO_EMPTY);
				$items[$selector] = array_merge($items[$selector], $tmp);
			}
		}
		else
		$items[$selector] = $ids[1];
	}	
		
	//Remove count stats
	$n = array('tags'=>0, 'id'=>0, 'cls'=>0);
	$nrules = array();
	$ids = array();
	
	foreach($rules[0] as $x=>$rule)
	{
		$media = strpos($rule, "@");
		if($media!==FALSE) continue; //TO-DO
		$pos = strpos($rule, "{");
		if($pos===FALSE||$pos==0) continue;
		$leftrule = substr($rule, 0, $pos+1);
		
		$tmp = array();
		$sels = preg_split("#[,\s]#", $leftrule, -1, PREG_SPLIT_NO_EMPTY);
		foreach($sels as $sel)
		{				
			$sel .= "{";
			$nrules[$sel] = 1;
		}
	}
	
	$string = implode("\n", array_keys($nrules));
	foreach($selectors as $selector=>$params)
	{
		preg_match_all($params['regex_css'], $string, $tmp);
		
		$tmp[1] = array_unique($tmp[1]);
		$ids[$selector] = $tmp[1];
		foreach($ids[$selector] as $j=>$id)
		{	
			$token = $params['prefix'].$id;
			if(isset($rules_to_remove[$token])) continue;
			if(in_array($token, $include_selectors)) 
			{
				$rules_to_remove[$token] = 0;
				continue;
			}
			if(!in_array($id, $items[$selector]))
			{
				$rules_to_remove[$token] = 1;
				$n[$selector]++;
			}
			else
				$rules_to_remove[$token] = 0;
		}
	}
	
	return $matches[1].$css.$matches[3]; //return minimized css
}


function amp_minify_css( $css ) 
{
	$css = preg_replace( "#[\r\n]#m", ' ', $css );
	$css = preg_replace('!/\*.*?\*/!s', '', $css); //strip comments
	$css = preg_replace( '#\s+#m', ' ', $css );
	$css = preg_replace( '/-?\b0px/', '0', $css );
	$css = str_replace( '; ', ';', $css );
	$css = str_replace( ': ', ':', $css );
	$css = preg_replace( '#\s*>\s*#', '>', $css );
	$css = str_replace( ' {', '{', $css );
	$css = str_replace( '{ ', '{', $css );
	$css = str_replace( ', ', ',', $css );
	$css = str_replace( '} ', '}', $css );
	$css = str_replace( ' }', '}', $css );
	$css = str_replace( ';}', '}', $css );
	$css = str_replace(' )', ')', $css);
	$css = str_replace('( ', '(', $css);
	$css = preg_replace('/\bwhite([\s;}])\b/', '#fff$1', $css); //avoid change white-space keyword
	$css = preg_replace('/\bblack\b/', '#000', $css);	
	$css = preg_replace('#(}+)#', "$1\n", $css);
	$css = trim($css);
	return $css;
}


//MAIN FUNCTION


function amp_remove_inline_unused_css($fullhtml)
{
	global $rules_to_remove;
	$rules_to_remove = array();
	
	global $htmlbodyamp;
	$htmlbodyamp = $fullhtml;
	
	//getting style sections...
	$fullhtml = preg_replace_callback("#(<style amp-custom>)(.+)(</style>)#Usmi", 'amp_css_find_rules_callback', $fullhtml);	

	$tmp = array();
	foreach($rules_to_remove as $rule=>$remove)
	{
		if($remove)
			$tmp[$rule] = 1;
	}
	$rules_to_remove = $tmp;
	
	$fullhtml = preg_replace_callback("#(<style amp-custom>)(.+)(</style>)#Usmi", 'amp_remove_css_callback', $fullhtml);

	return $fullhtml;
}

