<?php

/***************************************************************
* CSS REMOVE UNUSED RULES CLASS
* Run only in AMP pages
* Author: Marcel Soler (Pigmalión Tseyor)
* e-mail: msoler75@yahoo.es / pigmalion@tseyor.org
* Version: 1.1 (May-2016)
* Project:  https://github.com/msoler75/amp-remove-unused-css
**************************************************************


HOW TO USE - BASIC:

	$tmp = new AmpRemoveUnusedCss();
	$stats = $tmp->process($htmlcode);  //must be full htmlcode, with <style amp-custom> tag and the <body> content
	$result = $tmp->result();		
	

HOW TO VIEW REPORT:

	$tmp = new AmpRemoveUnusedCss(1);  //set 1 or TRUE to get full report, or void or 0 or FALSE to get simple report
	$stats = $tmp->process($htmlcode);  
	echo $tmp->report(); 
	

ONLY MINIFY CSS (can be used in no-AMP pages, too)

	You also can just only minify CSS by calling (it removes useless white spaces, but it does not remove unused CSS rules):

	$tmp = new AmpRemoveUnusedCss();
	$css_minified = $tmp->minify($css);	

	
TO-DO : process @media

**************************************************************/

class AmpRemoveUnusedCss 
{
	private $include_selectors = array('div','span','a','p','body','ul','li','nav'); //this tags will be ignored in process
	private $rules_to_remove = array();
	private $amphtml = "";
	private $newline = "\n";
	private $result = "";
	private $stats = array();
	private $fullstats = false;

	function __construct($fullstats=false)
	{
		$this->fullstats = $fullstats;		
	}	
	
	

	public function process($html, $newline = "\n")
	{		
		$this->amphtml = $html;
		$this->newline = $newline;
		if($this->fullstats)
			$this->stats['source_length'] = strlen($html);
		
		//collect style rules that are not in html code	
		$html = preg_replace_callback("#(<style amp-custom>)(.+)(</style>)#Usmi", array($this, '_amp_css_find_rules_callback'), $html);	

		
		foreach($this->rules_to_remove as $rule=>$remove)
		{
			if(!$remove)
				unset($this->rules_to_remove[$rule]);
		}
		
		//remove only unused rules in css
		$html = preg_replace_callback("#(<style amp-custom>)(.+)(</style>)#Usmi", array($this, '_amp_remove_css_callback'), $html);

		$this->result = $html;
	}
	
	public function result()
	{		
		return $this->result;
	}

	
	public function report($htmlformat=true)
	{
		$r = $htmlformat?"<pre><ul>":"";
		$tag = $htmlformat?"<li>":"";
		
		if(isset($this->stats['source_length']))
			$r .= $tag."Source CSS length = ".$this->stats['source_length']."\n";
			
		if(isset($this->stats['source_minified_length']))
			$r .= $tag."After minify length = ".$this->stats['source_minified_length']."\n";
		
		if(isset($this->stats['src_rules_to_remove']))
			$r .= $tag."Removed rules by type = ".var_export($this->stats['src_rules_to_remove'], true)."\n";
		
		if(isset($this->stats['num_rules_found']))		
		$r .= $tag . "Total rules in source = ".$this->stats['num_rules_found']."\n";
		
		if(isset($this->stats['keywords_removed']))		
		$r .= $tag . "Keywords removed = ".$this->stats['keywords_removed']."\n";
		
		if(isset($this->stats['lines_removed']))		
		$r .= $tag . "Complete CSS lines removed = ".$this->stats['lines_removed']."\n";
		
		if(isset($this->stats['final_length']))		
		$r .= $tag . "Final length = ".$this->stats['final_length']."\n";

		if(isset($this->stats['source_minified_length'])&&isset($this->stats['final_length']))
		{
			$orig = $this->stats['source_minified_length'];
			$final = $this->stats['final_length'];
			
			$r .= $tag . "A total of ".($orig-$final)." characters removed (". round(10000*$final/($orig+.00001))/100 ."%)\n";
		}
		
		if($this->fullstats && $this->rules_to_remove)
		{			
			$r .= $tag . "CSS Rules that has been removed: \n " . implode("\n ", array_keys($this->rules_to_remove));
		}
		
		
		$r .= $htmlformat?"</ul></pre>":"";
		
		return $r;
	}
	
	
	
	
    //1st STEP: FIND CSS RULES, by .class, by tag, and by #id
	function _amp_css_find_rules_callback($matches)
	{		
		$css = $matches[2];
		$css = preg_replace("/\!important/", "", $css); //remove !important  because is not allowed in AMP
		$css = $this->minify( $css ); //we need minize here to get correct format
		
		if($this->fullstats)
			$this->stats['source_minified_length'] = strlen($css);
		
		
		preg_match_all("#@[^{]+{([^{}]+{[^}]*})*}|([\.\#a-z][^{]*{[^}]*})#m", $css, $rules);
		$selectors = array(
			'cls'=> array('prefix'=>".", 'regex_css'=>"/\.([a-z][a-z0-9\-_]*)[^a-z0-9\-_]/Usim", 'find_in_html'=>"/<[^>]*\s+class=['\"]\s*(?:([^'\"]+)\b\s*)*['\"]/ism"),
			'id'=> array('prefix'=>"#", 'regex_css'=>"/#([a-z][a-z0-9\-_]*)[^a-z0-9\-_]/im", 'find_in_html'=>"/<[^>]*\s+id=['\"]([^'\"]+)['\"]/im"),
			'tags'=> array('prefix'=>"", 'regex_css'=>"/^([^\.#@:][a-z0-9\-_]*)\b/im", 'find_in_html'=>"/<([a-z][^\s><]*)\b/i")
		);
		
					
		$items = array();	
		foreach($selectors as $selector=>$params)
		{		
			preg_match_all($params['find_in_html'], $this->amphtml, $ids);
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
				if(isset($this->rules_to_remove[$token])) continue;
				if(in_array($token, $this->include_selectors)) 
				{
					$this->rules_to_remove[$token] = 0;
					continue;
				}
				if(!in_array($id, $items[$selector]))
				{
					$this->rules_to_remove[$token] = 1;
					$n[$selector]++;
				}
				else
					$this->rules_to_remove[$token] = 0;
			}
		}
		
		$this->stats['src_rules_to_remove']=$n;
		$this->stats['num_rules_found']=count($nrules);
		
		return $matches[1].$css.$matches[3]; //return minimized css
	}

	
	
	
	//2nd STEP: remove unused rules in CSS source
	private function _amp_remove_css_callback($matches)
	{			
		$css = $matches[2];
		preg_match_all("#@[a-z][^{]+{([^{}]+{[^}]*})*}|([\.\#a-z][^{]*{[^}]*})#im", $css, $rules);
		
		//stats
		$lineremoved = 0;
		$keyremoved = 0;
		
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
						if(isset($this->rules_to_remove[$id]))
						{
							$found = true;
							unset($rtmp[$i]);
							$keyremoved++;
						}
					}				
				}
				if(!$rtmp||!count($rtmp))
				{
					unset($rules[0][$x]);
					$lineremoved++;
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
		
		$this->stats['lines_removed'] = $lineremoved;
		$this->stats['keywords_removed'] = $lineremoved;
				
		$r = implode($this->newline,$rules[0]);
		
		$this->stats['final_length'] = strlen($r);
		
		return $matches[1].$r.$matches[3];
	}

	private function minify( $css ) 
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

}


?>