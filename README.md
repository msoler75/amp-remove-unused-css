
"# amp-remove-unused-css"

I am providing these functions to eliminate unnecessary rules within the CSS code pages AMP (Accelerated Mobile Pages) format.

In this format each page contain inline the whole CSS styles that will need.

These funcions will not significantly reduce the final download size once compressed, but will add clarity in code and will reduce the processor time, that it's useful in mobile devices.

It also can really be useful if the template contains a lot of CSS.

Maybe in the future Google will mind the size of AMP pages and classify the smaller ones in a better position.

More information: 
https://www.ampproject.org/


HOW TO USE THE LIB
------------------

Just call the main function with the full html code of the AMP page as parameter.

amp_remove_inline_unused_css($fullhtml);

