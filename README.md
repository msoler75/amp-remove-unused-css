
"# amp-remove-unused-css"

I am providing these functions to eliminate unnecessary rules within the CSS code pages AMP (Accelerated Mobile Pages) format.

In this format each page contain inline the whole CSS styles that will need.

These funcions will not significantly reduce the final download size once compressed, but will add clarity in code and will reduce the processor time, that it's useful in mobile devices.

It also can really be useful if the template contains a lot of CSS.

Maybe in the future Google will mind the size of AMP pages and classify the smaller ones in a better position.

More information about AMP: 
https://www.ampproject.org/


HOW TO USE THE LIB
------------------

Just call the main function with the full html code of the AMP page as parameter.

amp_remove_inline_unused_css($fullhtml);



CLASS VERSION
-------------

amp_remove_css.class.php

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
	
	
