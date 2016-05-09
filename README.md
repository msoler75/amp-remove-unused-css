
This class can be used to take away those unnecessary rules within the CSS code pages AMP (Accelerated Mobile Pages) format.

In this format each single page contain inline the whole CSS styles that will need.

Ok, all CSS in AMP is embeded inline, but some people still use templates styles with a tons of CSS code, and most of pages can add large extra useless CSS code.

It's time to reduce useless CSS and remove all those CSS rules not being used, leaving usually a few lines in each single page.

¡Now we don't need to worry about big CSS templates that are being translated to AMP protocol!

These funcions will not significantly reduce the final download size once compressed, but will add clarity in code and will reduce the processor time, that it's useful in handheld devices.

Maybe in the future Google will mind the size of AMP pages and classify the smaller ones in a better position.

I tried some approaches to get the fastest algorithm, and now it's really fast. But I recommend to cache pages as usual.



More information about AMP: 
https://www.ampproject.org/



HOW TO USE THE CLASS
--------------------

First include the file  amp_remove_css.class.php


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
	
