# REMOVE UNUSED CSS RULES IN AMP PAGES #

This library is for a quick optimization of the CSS rules of an AMP document, eliminating 95% of the rules that are not being used in the current document.

I tried some approaches to get the fastest algorithm, and now it's really fast, so you can use it in every page of your website. 

More information about AMP: 
https://www.ampproject.org/


The operation is simple: it is enough to process the final document in AMP format, including the <style amp-custom> tag and the <body>.

Version 2.0 have been completely rebuilt, and now supports @media rules, which tolerates and optimizes them, removing them completely when they are no longer needed.

It also respects the @keyframes leaving them unchanged.

If you want to preserve some CSS rules, you can also tell the library to keep them.


HOW TO USE THE CLASS
--------------------

First include the file  amp_remove_css.class.php



*HOW TO USE - BASIC*:
    $tmp = new AmpRemoveUnusedCss();
    $tmp->preserve_selectors[] = ".please-dont-delete-me"; // preserve this css rule anywhere it appears
	$tmp->process($htmlcode);  //must be full htmlcode, with <style amp-custom> tag and the <body> content
	echo $tmp->result();		
	
*HOW TO VIEW REPORT*:
	$tmp = new AmpRemoveUnusedCss(1);  //set 1 or TRUE to get full report, or void or 0 or FALSE to get simple report
	$tmp->process($htmlcode);  
    echo $tmp->report(); 
	
*ONLY MINIFY CSS* (can be used in no-AMP pages, too)
	You also can just only minify CSS by calling (it removes useless white spaces, but it does not remove unused CSS rules):
	$tmp = new AmpRemoveUnusedCss();
	$css_minified = $tmp->minify($css);	

