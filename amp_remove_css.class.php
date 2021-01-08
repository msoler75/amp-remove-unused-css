<?php

/***************************************************************
 * CSS REMOVE UNUSED RULES CLASS
 * Run only in AMP pages
 * Author: Marcel Soler (Pigmalión Tseyor)
 * e-mail: msoler75@yahoo.es / pigmalion@tseyor.org
 * Version: 1.1 (May-2016)
 * Version: 2.0 (Jan-2021)
 * Project:  https://github.com/msoler75/amp-remove-unused-css
 **************************************************************

 
This library is for a quick optimization of the CSS rules of an AMP document, eliminating 95% of the rules that are not being used in the current document.

The operation is simple: it is enough to process the final document in AMP format, including the <style amp-custom> tag and the <body>.

Version 2.0 have been completely rebuilt, and now supports @media rules, which tolerates and optimizes them, removing them completely when they are no longer needed.

It also respects the @keyframes leaving them unchanged.

If you want to preserve some CSS rules, you can also tell the library to keep them.


HOW TO USE - BASIC:
    $tmp = new AmpRemoveUnusedCss();
    $tmp->preserve_selectors[] = ".please-dont-delete-me"; // preserve this css rule anywhere it appears
    $tmp->process($htmlcode);  //must be full htmlcode, with <style amp-custom> tag and the <body> content
    echo $tmp->result();		
	
HOW TO VIEW REPORT:-
    $tmp = new AmpRemoveUnusedCss(1);  //set 1 or TRUE to get full report, or void or 0 or FALSE to get simple report
    $tmp->process($htmlcode);  
    echo $tmp->report(); 
	
ONLY MINIFY CSS (can be used in no-AMP pages, too)
    You also can just only minify CSS by calling (it removes useless white spaces, but it does not remove unused CSS rules):
    $tmp = new AmpRemoveUnusedCss();
    $css_minified = $tmp->minify($css);	


 **************************************************************/

class AmpRemoveUnusedCss
{
    public $preserve_selectors = array('[amp-custom]'); //this tags will be ignored in process
    private $rules_to_remove = array();
    private $amphtml = "";
    private $newline = "\n";
    private $result = "";
    private $stats = array();
    private $fullstats = false;
    private $selectors = array(
        'tags.attr' => array('prefix' => "", 'regex_css' => "/\b([a-z][a-z0-9\-_]*)\[([a-z][a-z0-9\-_]+([^\]]*)\])/im", 'find_in_html' => "/<([a-z][^>\s]*)(?:\s+([^\s=>]+)(?:\s*=\s*(?:['\"]([^'\"]+)['\"]|([^\s>]+)))?)+/im", "suffix" => "]"),
        'tags.cls' => array('prefix' => "", 'regex_css' => "/\b([a-z][a-z0-9\-_]*)\.([a-z][a-z0-9\-_]+)/im", 'find_in_html' => "/<([a-z][^>\s]*)\s+([^>]*\s)?class\s*=\s*['\"]\s*(?:([^'\"]+)*['\"])/im"),
        'cls' => array('prefix' => ".", 'regex_css' => "/\.([a-z][a-z0-9\-_]*)[^a-z0-9\-_]/Usim", 'find_in_html' => "/<[^>]*\s+class\s*=\s*['\"]\s*(?:([^'\"]+)\b\s*)*['\"]/ism"),
        'id' => array('prefix' => "#", 'regex_css' => "/#([a-z][a-z0-9\-_]*)[^a-z0-9\-_]/im", 'find_in_html' => "/<[^>]*\s+id=['\"]([^'\"]+)['\"]/im"),
        'tags' => array('prefix' => "", 'regex_css' => "/^([^\.#@:\[][a-z0-9\-_]*)\b/im", 'find_in_html' => "/<([a-z][^\s><]*)\b/i"),
        'attr' => array('prefix' => "[", 'regex_css' => "/\[([a-z0-9\-_]+([^\]]*)\])/im", 'find_in_html' => "/<[a-z][^>\s]*(\s+([^\s=>]+)(?:\s*=\s*(?:['\"]([^'\"]+)['\"]|([^\s>]+)))?)+/im", "suffix" => "]"),
    );

    private $escapechars;

    function __construct($fullstats = false)
    {
        $this->fullstats = $fullstats;
        $this->escapechars = str_split("?*:.()[]-");
    }

    
    public function process($html, $newline = "\n")
    {
        $bodypos = strpos($html, "<body");
        $this->amphtml = $bodypos !== FALSE ? substr($html, $bodypos) : $html;
        $this->newline = $newline;
        if ($this->fullstats)
            $this->stats['source_length'] = strlen($html);

        //do the process
        $html = preg_replace_callback("#(<style amp-custom[^>]*>)(.+?)(</style>)#Usmi", array($this, '_amp_css_find_rules_callback'), $html);

        $this->result = $html;
    }

    public function result()
    {
        return $this->result;
    }


    public function report($htmlformat = true)
    {
        $r = $htmlformat ? "<pre><ul>" : "";
        $tag = $htmlformat ? "<li>" : "";

        if (isset($this->stats['source_length']))
            $r .= $tag . "Source CSS length = " . $this->stats['source_length'] . "\n";

        if (isset($this->stats['source_minified_length']))
            $r .= $tag . "After minify length = " . $this->stats['source_minified_length'] . "\n";

        if (isset($this->stats['num_rules_found']))
            $r .= $tag . "Total rules in source = " . $this->stats['num_rules_found'] . "\n";

        if (isset($this->stats['keywords_removed']))
            $r .= $tag . "Keywords removed = " . $this->stats['keywords_removed'] . "\n";

        if (isset($this->stats['lines_removed']))
            $r .= $tag . "Complete CSS lines removed = " . $this->stats['lines_removed'] . "\n";

        if (isset($this->stats['final_length']))
            $r .= $tag . "Final length = " . $this->stats['final_length'] . "\n";

        if (isset($this->stats['source_minified_length']) && isset($this->stats['final_length'])) {
            $orig = $this->stats['source_minified_length'];
            $final = $this->stats['final_length'];

            $r .= $tag . "A total of " . ($orig - $final) . " characters removed (" . round(10000 * ($orig - $final) / ($orig + .00001)) / 100 . "%)\n";
        }

        if ($this->fullstats && $this->rules_to_remove) {
            $r .= $tag . "CSS Rules that has been removed: \n " . implode(", ", array_keys($this->rules_to_remove));
        }


        $r .= $htmlformat ? "</ul></pre>" : "";

        return $r;
    }


    private function existInHTML($firstch, $word, &$state, &$items)
    {
        // change of word
        switch ($firstch) {
            case "#":
                $token = "#" . $word;
                $state["id"] = $word;
                $this->last_token = $token;
                if (in_array($token, $this->preserve_selectors)) return true;
                if (isset($this->rules_to_remove[$token])) return false;
                return in_array($token, $items["id"]);

            case ".":
                $token = "." . $word;
                $state["cls"] = $word;
                if ($state["tag"]) {
                    $token = $state["tag"] . $token;
                    $this->last_token = $token;
                    if (in_array($token, $this->preserve_selectors)) return true;
                    if (isset($this->rules_to_remove[$token])) return false;
                    return in_array($token, $items["tags.cls"]);
                } else {
                    $this->last_token = $token;
                    if (in_array($token, $this->preserve_selectors)) return true;
                    if (isset($this->rules_to_remove[$token])) return false;
                    return in_array($token, $items["cls"]);
                }

            case "[":
                $token = "[" . $word . "]";
                $state["attr"] = $word;
                if ($state["tag"]) {
                    $token = $state["tag"] . $token;
                    $this->last_token = $token;
                    if (in_array($token, $this->preserve_selectors)) return true;
                    if (isset($this->rules_to_remove[$token])) return false;
                    return in_array($token, $items["tags.attr"]);
                } else {
                    $this->last_token = $token;
                    if (in_array($token, $this->preserve_selectors)) return true;
                    if (isset($this->rules_to_remove[$token])) return false;
                    return in_array($token, $items["attr"]);
                }



            case "=":
                if ($state["attr"]) {
                    $value = $word;
                    if (!$state["op"]) {
                        if ($state['tag']) {
                            $token = $state['tag'] . "[" . $state["attr"] . "='" . $value . "']";
                            if (in_array($token, $this->preserve_selectors)) return true;
                            if (in_array($token, $items['tags.attr']))
                                return true;
                        } else {
                            $token = "[" . $state["attr"] . "='" . $value . "']";
                            if (in_array($token, $this->preserve_selectors)) return true;
                            if (in_array($token, $items['attr']))
                                return true;
                        }
                        $tag = $state["tag"];
                        $attr = $state["attr"];
                        $regex = "/<{$tag}\b[^>]*\b{$attr}\s*=\s*['\"]?{$value}\b['\"]?/";
                        $this->last_token = $token;
                        if (in_array($token, $this->preserve_selectors)) return true;
                        if (isset($this->rules_to_remove[$token])) return false;
                        return preg_match($regex, $this->amphtml);
                    } else {
                        switch ($state["op"]) {
                            case "|":
                                $regex = "$value\b.*";
                                break;
                            case "^":
                                $regex = "$value.*";
                                break;
                            case "*":
                                $regex = ".*$value.*";
                                break;
                            case "~":
                                $regex = ".*\b$value\b.*";
                                break;
                            case "$":
                                $regex = ".*$value";
                                break;
                            default:
                                $regex = "^$value$";
                        }

                        $tag = $state["tag"];
                        $attr = $state["attr"];
                        $regex = "/<{$tag}\b[^>]*\b{$attr}\s*=\s*['\"]?{$regex}\b['\"]?/";
                        $token = $state['tag'] . "[" . $state["op"] . $state["attr"] . "='" . $value . "']";
                        $this->last_token = $token;
                        if (in_array($token, $this->preserve_selectors)) return true;
                        if (isset($this->rules_to_remove[$token])) return false;
                        return preg_match($regex, $this->amphtml);
                    }
                }
                break;

            case ":":
            case "(":
                return true;

            default:
                $state["tag"] = $word;
                $token = $word;
                $this->last_token = $token;
                if (in_array($token, $this->preserve_selectors)) return true;
                if (isset($this->rules_to_remove[$token])) return false;
                return in_array($token, $items["tags"]);
        }
        return true;
    }


    private function ruleInHTML($rule)
    {
        $found = false;
        $len = strlen($rule);
        $i = 0;
        $word = "";
        $firstch = "";
        $state = array("tag" => "", "id" => "", "cls" => "", "attr" => "", "op" => "",  "value" => "");
        $path = array();
        $beginword = true;
        while ($i < $len) {
            $ch = $rule[$i];
            //if(preg_match("/[a-z0-9\-_]/", $ch))
            if (($ch >= "a" && $ch <= "z") ||
                ($ch >= "A" && $ch <= "Z") ||
                ($ch >= "0" && $ch <= "9") ||
                ($ch == "-") ||
                ($ch == "_")
            ) {
                $word .= $ch;
            } else if ($ch == " " || $ch == "]" || $ch == "\"" || $ch == "'") {
                if ($word)
                    if (!$this->existInHTML($firstch, $word, $state, $this->items)) {
                        $this->rules_to_remove[$this->last_token] = true;
                        return false;
                    }
                $path[] = $state;
                $state = array("tag" => "", "id" => "", "cls" => "", "attr" => "", "op" => "", "value" => "");
                $word = "";
                $beginword = true;
                $firstch = "";
            } else if (in_array($ch, array("*", "~", "|", "^", "$"))) {
                $state["op"] = $ch;
            } else {
                if ($beginword) {
                    $firstch = $ch;
                    $i++;
                    continue;
                } else {
                    if ($word)
                        if (!$this->existInHTML($firstch, $word, $state, $this->items)) {
                            $this->rules_to_remove[$this->last_token] = true;
                            return false;
                        }
                    $word = "";
                    $beginword = true;
                    $firstch = "";
                    continue;
                }
            }
            $beginword = false;
            $i++;
        }
        if ($word)
            if (!$this->existInHTML($firstch, $word, $state, $this->items)) {
                $this->rules_to_remove[$this->last_token] = true;
                return false;
            }
        return true;
    }

    private function _amp_css_find_rules_callback($matches)
    {
        $css = $matches[2];
        $css = preg_replace("/\!important/", "", $css); //remove !important  because is not allowed in AMP
        $css = $this->minify($css, false); //we need minize here to get correct format

        $this->stats['source_minified_length'] = strlen($css);


        //FAST-FIND (SOME) ELEMENTS present IN HTML, by class, by tag, id, attributes, and combination of tag and class, and tag and attr

        $this->items = array();
        foreach ($this->selectors as $selector => $params) {
            $this->items[$selector] = array();
            if (preg_match_all($params['find_in_html'], $this->amphtml, $ids)) {
                if ($selector == 'cls') {
                    foreach ($ids[1] as $classes) {
                        $tmp = preg_split("/\s/", $classes, -1, PREG_SPLIT_NO_EMPTY);
                        foreach ($tmp as $cls)
                            $this->items[$selector][] = "." . $cls;
                    }
                } else if ($selector == 'attr') {
                    // attr and tags.attr are not matching all attributes, because of limits of regular expression capturing groups
                    foreach ($ids[2] as $i => $attr) {
                        if ($attr != "class") {
                            $this->items[$selector][] =  "[" . $attr . "]";
                            if ($ids[3][$i])
                                $this->items[$selector][] =  "[" . $attr . "='" . $ids[3][$i] . "']";
                            if ($ids[4][$i])
                                $this->items[$selector][] =  "[" . $attr . "='" . $ids[4][$i] . "']";
                        }
                    }
                } else if ($selector == "tags.attr") {
                    foreach ($ids[1] as $i => $tag) {
                        $attr = $ids[2][$i];
                        if ($attr != "class") {
                            $this->items["attr"][] = $tag . "[" . $attr . "]";
                            if ($ids[3][$i])
                                $this->items[$selector][] =  $tag . "[" . $attr . "='" . $ids[3][$i] . "']";
                            if ($ids[4][$i])
                                $this->items[$selector][] =  $tag . "[" . $attr . "='" . $ids[4][$i] . "']";
                        }
                    }
                } else if ($selector == "tags.cls") {
                    foreach ($ids[1] as $i => $tag) {
                        $terms = preg_split("/[\s+]/", $ids[3][$i]);
                        foreach ($terms as $term)
                            $this->items[$selector][] = $tag . "." . $term;
                    }
                } else if ($selector == "id") {
                    foreach ($ids[1] as $id)
                        $this->items[$selector][] = "#" . $id;
                } else {
                    $this->items[$selector] = $ids[1];
                }
            }
        }


        foreach ($this->items as $selector => $arr)
            $this->items[$selector] = array_unique($arr);

        // stats
        $nrules = 0;
        $ids = array();
        $lineremoved = 0;
        $keywordremoved = 0;

        $inkeyframes = false;

        // Check RULES AND REMOVE WHEN ARE NOT USED ANYMORE

        $rules = preg_split("/\n/", $css, -1, PREG_SPLIT_NO_EMPTY);
        foreach ($rules as $x => $rule) {
            if ($rule == "}") {
                $inkeyframes = false;
                continue;
            }
            if ($rule[0] == "@") {
                $inkeyframes = preg_match("/.*keyframes.*/", $rule);
                continue;
            }
            if ($inkeyframes)
                continue;

            $nrules++;

            $pos = strpos($rule, "{");
            $leftrule = substr($rule, 0, $pos);
            $rightrule = substr($rule, $pos);
            $rtmp = preg_split("#[,]#m", $leftrule, -1, PREG_SPLIT_NO_EMPTY);
            $numrules = count($rtmp);
            foreach ($rtmp as $i => $trule) {
                $found = 0;
                $trule = trim($trule);
                $found = $this->ruleInHTML($trule);
                if (!$found) {
                    unset($rtmp[$i]);
                    $keywordremoved++;
                }
            }

            if (!$rtmp || !count($rtmp)) {
                unset($rules[$x]);
                $lineremoved++;
            } else {
                if ($numrules != count($rtmp)) {
                    // rebuild group css rule with concrete rules that are alive
                    $rules[$x] = implode(",", $rtmp) . $rightrule;
                }
            }
        }


        $this->stats['num_rules_found'] = $nrules;
        $this->stats['lines_removed'] = $lineremoved;
        $this->stats['keywords_removed'] = $keywordremoved;

        $r = implode($this->newline, $rules);


        // remove emptied @media group rules
        $r = preg_replace("/@media[^{]+{[^a-z]+}\n?/", "", $r);

        //if you want to remove all eol
        //$r = str_replace("\n", "", $r);

        $this->stats['final_length'] = strlen($r);

        return $matches[1] . $r . $matches[3];
    }

    private function minify($css, $remove_eol = TRUE)
    {
        $css = preg_replace("/[\r\n]/m", ' ', $css);
        $css = preg_replace('!/\*.*?\*/!s', '', $css); //strip comments
        $css = preg_replace('/\s+/m', ' ', $css);
        $css = preg_replace('/-?\b0px/', '0', $css);
        $css = str_replace('; ', ';', $css);
        $css = str_replace(': ', ':', $css);
        $css = preg_replace('/\s*>\s*/', '>', $css);
        $css = str_replace(' {', '{', $css);
        $css = str_replace('{ ', '{', $css);
        $css = str_replace(', ', ',', $css);
        $css = str_replace('} ', '}', $css);
        $css = str_replace(' }', '}', $css);
        $css = str_replace(';}', '}', $css);
        $css = str_replace(' )', ')', $css);
        $css = str_replace('( ', '(', $css);
        $css = preg_replace('/\bwhite([\s;}])\b/', '#fff$1', $css); //avoid change white-space keyword
        $css = preg_replace('/\bblack\b/', '#000', $css);
        $css = preg_replace('/(}+)/', "$1\n", $css);
        if (!$remove_eol) {
            $css = preg_replace('/\s*@media[^{]*{/', "$0\n", $css);
            $css = str_replace("}}", "}\n}", $css);
        }
        $css = trim($css);
        return $css;
    }
}
